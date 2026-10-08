<?php

namespace App\Livewire\Account;

use App\Models\Address;
use App\Models\ShippingDistrict;
use App\Models\ShippingZone;
use App\Services\ShippingService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.shop-layout')]
#[Title('Mis direcciones')]
class Addresses extends Component
{
    /** The address being edited; null while adding a new one. */
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $name = '';

    public string $phone = '';

    public string $ubigeo = '';

    public string $line1 = '';

    public string $line2 = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^9\d{8}$/'],
            'ubigeo' => ['required', 'string', 'size:6'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'phone.regex' => 'Ingresa un celular de 9 dígitos que empiece con 9.',
            'ubigeo.required' => 'Elige el distrito de entrega.',
            'ubigeo.size' => 'Elige el distrito de entrega.',
        ];
    }

    /** @return Collection<int, Address> */
    #[Computed]
    public function addresses(): Collection
    {
        return auth()->user()->addresses()->where('type', 'shipping')->orderByDesc('is_default')->latest()->get();
    }

    /** @return Collection<int, ShippingZone> */
    #[Computed]
    public function zones(): Collection
    {
        return app(ShippingService::class)->zonesWithDistricts(includeEmpty: true);
    }

    public function add(): void
    {
        $this->resetForm();
        $this->name = auth()->user()->name;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $address = $this->owned($id);

        $this->resetForm();
        $this->editingId = $address->id;
        $this->name = $address->recipient_name;
        $this->phone = (string) $address->phone;
        $this->ubigeo = (string) $address->ubigeo;
        $this->line1 = $address->line1;
        $this->line2 = (string) $address->line2;
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->phone = preg_replace('/^(\+?51)(?=9\d{8}$)/', '', preg_replace('/[\s\-().]/', '', $this->phone)) ?? '';
        $data = $this->validate();

        $district = ShippingDistrict::with('zone')->where('ubigeo', $data['ubigeo'])->where('is_active', true)->first();
        if (! $district) {
            $this->addError('ubigeo', 'Todavía no enviamos a ese distrito.');

            return;
        }

        $values = [
            'recipient_name' => $data['name'],
            'phone' => $data['phone'],
            'line1' => $data['line1'],
            'line2' => $data['line2'] ?: null,
            'city' => $district->name,
            'state' => 'Lima',
            'ubigeo' => $district->ubigeo,
            'country' => 'PE',
        ];

        $user = auth()->user();

        if ($this->editingId) {
            $this->owned($this->editingId)->update($values);
        } else {
            $user->addresses()->create([...$values, 'type' => 'shipping', 'is_default' => ! $user->addresses()->where('type', 'shipping')->exists()]);
        }

        $this->resetForm();
    }

    public function makeDefault(int $id): void
    {
        $address = $this->owned($id);

        auth()->user()->addresses()->where('type', 'shipping')->update(['is_default' => false]);
        $address->update(['is_default' => true]);
    }

    public function remove(int $id): void
    {
        $address = $this->owned($id);
        $wasDefault = $address->is_default;
        $address->delete();

        // Never leave the customer without a default while they still have addresses.
        if ($wasDefault && ($next = auth()->user()->addresses()->where('type', 'shipping')->latest()->first())) {
            $next->update(['is_default' => true]);
        }

        if ($this->editingId === $id) {
            $this->resetForm();
        }
    }

    private function owned(int $id): Address
    {
        return auth()->user()->addresses()->where('type', 'shipping')->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'showForm', 'name', 'phone', 'ubigeo', 'line1', 'line2']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.account.addresses');
    }
}
