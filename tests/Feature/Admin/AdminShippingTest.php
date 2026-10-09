<?php

namespace Tests\Feature\Admin;

use App\Models\ShippingDistrict;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShippingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_customer_cannot_open_shipping(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.shipping.index'))->assertForbidden();
    }

    public function test_admin_sees_zones_and_districts(): void
    {
        $zone = ShippingZone::factory()->create(['name' => 'Zona Norte', 'min_cost' => 8]);
        ShippingDistrict::factory()->create(['shipping_zone_id' => $zone->id, 'name' => 'Comas', 'cost' => 10]);

        $this->actingAs($this->admin())->get(route('admin.shipping.index'))->assertOk()->assertSee('Zona Norte')->assertSee('Comas');
    }

    public function test_district_cost_is_saved_and_cannot_go_below_the_zone_minimum(): void
    {
        $zone = ShippingZone::factory()->create(['min_cost' => 8]);
        $district = ShippingDistrict::factory()->create(['shipping_zone_id' => $zone->id, 'cost' => 10]);

        $this->actingAs($this->admin())->put(route('admin.shipping.districts.update', $district), ['cost' => '12.50', 'is_active' => 1])
            ->assertSessionHas('notice');
        $this->assertSame('12.50', $district->fresh()->cost);

        $this->actingAs($this->admin())->put(route('admin.shipping.districts.update', $district), ['cost' => '5', 'is_active' => 1])
            ->assertSessionHas('error');
        $this->assertSame('12.50', $district->fresh()->cost);
    }

    public function test_raising_the_zone_minimum_lifts_cheaper_districts_and_zone_can_be_deactivated(): void
    {
        $zone = ShippingZone::factory()->create(['min_cost' => 8]);
        $district = ShippingDistrict::factory()->create(['shipping_zone_id' => $zone->id, 'cost' => 9]);

        $this->actingAs($this->admin())->put(route('admin.shipping.zones.update', $zone), ['min_cost' => '11']);

        $this->assertSame('11.00', $district->fresh()->cost);
        $this->assertFalse($zone->fresh()->is_active);
    }

    public function test_admin_adds_a_district_with_a_unique_ubigeo(): void
    {
        $zone = ShippingZone::factory()->create(['min_cost' => 8]);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.shipping.districts.store', $zone), ['name' => 'Ate', 'ubigeo' => '150103', 'cost' => '9'])
            ->assertSessionHas('notice');
        $this->assertDatabaseHas('shipping_districts', ['ubigeo' => '150103', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.shipping.districts.store', $zone), ['name' => 'Otro', 'ubigeo' => '150103', 'cost' => '9'])
            ->assertSessionHasErrors('ubigeo');
        $this->actingAs($admin)->post(route('admin.shipping.districts.store', $zone), ['name' => 'Barato', 'ubigeo' => '150104', 'cost' => '3'])
            ->assertSessionHas('error');
    }
}
