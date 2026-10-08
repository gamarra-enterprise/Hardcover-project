<?php

namespace App\Livewire\Checkout;

use App\Exceptions\CheckoutBlocked;
use App\Models\ShippingDistrict;
use App\Models\ShippingZone;
use App\Services\CartService;
use App\Services\CartSummary;
use App\Services\CheckoutService;
use App\Services\ShippingQuote;
use App\Services\ShippingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.shop-layout')]
#[Title('Finalizar compra')]
class Checkout extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $ubigeo = '';

    public string $line1 = '';

    public string $line2 = '';

    public bool $saveAddress = true;

    public function mount(CartService $cart): mixed
    {
        $summary = $cart->summary();

        if ($summary->isEmpty()) {
            return $this->backToCart('Tu carrito está vacío.');
        }

        if ($summary->hasIssues()) {
            return $this->backToCart('Revisa los avisos de tu carrito antes de continuar.');
        }

        // A logged-in customer finds their data and last delivery address already filled in.
        if ($user = auth()->user()) {
            $this->name = $user->name;
            $this->email = $user->email;

            if ($address = $user->addresses()->where('type', 'shipping')->orderByDesc('is_default')->latest()->first()) {
                $this->name = $address->recipient_name;
                $this->phone = (string) $address->phone;
                $this->line1 = $address->line1;
                $this->line2 = (string) $address->line2;
                $this->ubigeo = $this->servedUbigeo((string) $address->ubigeo);
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
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

    #[Computed]
    public function summary(): CartSummary
    {
        return app(CartService::class)->summary();
    }

    /** @return Collection<int, ShippingZone> zones with their active districts; empty ones show as "próximamente" */
    #[Computed]
    public function zones(): Collection
    {
        return app(ShippingService::class)->zonesWithDistricts(includeEmpty: true);
    }

    /** @return array{subtotal: string, shipping_cost: string, tax: string, total: string, quote: ?ShippingQuote} */
    #[Computed]
    public function totals(): array
    {
        return app(CheckoutService::class)->totals($this->summary, $this->ubigeo ?: null);
    }

    public function place(CheckoutService $checkout): mixed
    {
        // Spaces, dashes and the +51 prefix are accepted and dropped before checking the number.
        $this->phone = preg_replace('/^(\+?51)(?=9\d{8}$)/', '', preg_replace('/[\s\-().]/', '', $this->phone)) ?? '';

        $data = $this->validate();

        $key = 'checkout:'.(auth()->id() ?? request()->ip());
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('name', 'Hiciste demasiados pedidos seguidos. Inténtalo de nuevo en un rato.');

            return null;
        }

        try {
            $order = $checkout->placeOrder([...$data, 'save_address' => $this->saveAddress], auth()->user());
        } catch (CheckoutBlocked $e) {
            if ($this->summary->isEmpty() || $this->summary->hasIssues()) {
                return $this->backToCart($e->getMessage());
            }

            $this->addError('ubigeo', $e->getMessage());

            return null;
        }

        RateLimiter::hit($key, 3600);

        return $this->redirect($order->signedUrl(), navigate: false);
    }

    private function servedUbigeo(string $ubigeo): string
    {
        return ShippingDistrict::where('ubigeo', $ubigeo)->where('is_active', true)->exists() ? $ubigeo : '';
    }

    private function backToCart(string $message): mixed
    {
        session()->flash('notice', $message);

        return $this->redirectRoute('cart', navigate: false);
    }

    public function render()
    {
        return view('livewire.checkout.checkout');
    }
}
