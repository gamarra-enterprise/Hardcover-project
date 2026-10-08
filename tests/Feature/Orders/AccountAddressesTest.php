<?php

namespace Tests\Feature\Orders;

use App\Livewire\Account\Addresses;
use App\Models\Address;
use App\Models\ShippingDistrict;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountAddressesTest extends TestCase
{
    use RefreshDatabase;

    private function form(string $ubigeo): array
    {
        return ['name' => 'Ana Ruiz', 'phone' => '987 654 321', 'ubigeo' => $ubigeo, 'line1' => 'Av. Lima 123', 'line2' => ''];
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('account.addresses'))->assertRedirect(route('login'));
    }

    public function test_customer_adds_addresses_and_the_first_is_default(): void
    {
        $district = ShippingDistrict::where('is_active', true)->first() ?? ShippingDistrict::factory()->create(['is_active' => true]);
        $user = User::factory()->create();

        $t = Livewire::actingAs($user)->test(Addresses::class)->call('add');
        foreach ($this->form($district->ubigeo) as $k => $v) {
            $t->set($k, $v);
        }
        $t->call('save')->assertHasNoErrors();
        $t->call('add')->set('name', 'B')->set('phone', '987654321')->set('ubigeo', $district->ubigeo)->set('line1', 'Otra 5')->call('save');

        $this->assertSame(2, $user->addresses()->count());
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
        $this->assertSame('987654321', $user->addresses()->first()->phone);
    }

    public function test_rejects_bad_phone_and_unserved_district(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Addresses::class)->call('add')
            ->set('name', 'A')->set('phone', '123')->set('ubigeo', '999999')->set('line1', 'x')
            ->call('save')->assertHasErrors(['phone']);
    }

    public function test_cannot_touch_someone_elses_address_and_default_moves_on_delete(): void
    {
        $user = User::factory()->create();
        $foreign = Address::factory()->create(['user_id' => User::factory()->create()->id]);
        $a = Address::factory()->create(['user_id' => $user->id, 'type' => 'shipping', 'is_default' => true]);
        $b = Address::factory()->create(['user_id' => $user->id, 'type' => 'shipping', 'is_default' => false]);

        $t = Livewire::actingAs($user)->test(Addresses::class);
        try {
            $t->call('remove', $foreign->id);
        } catch (ModelNotFoundException) {
        }
        $this->assertDatabaseHas('addresses', ['id' => $foreign->id]);

        $t->call('remove', $a->id);
        $this->assertTrue($b->fresh()->is_default);
    }
}
