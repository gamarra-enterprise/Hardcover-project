<?php

namespace Tests\Feature\Shipping;

use App\Exceptions\ShippingCostBelowMinimum;
use App\Models\ShippingDistrict;
use App\Models\ShippingZone;
use App\Services\ShippingService;
use Database\Seeders\ShippingSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ShippingService
    {
        return app(ShippingService::class);
    }

    public function test_the_seeder_loads_the_two_zones_and_the_43_districts_of_lima_metropolitana(): void
    {
        $this->seed(ShippingSeeder::class);
        $this->seed(ShippingSeeder::class);

        $this->assertSame(['10.00', '15.00'], ShippingZone::orderBy('position')->pluck('min_cost')->map(fn ($c) => (string) $c)->all());
        $this->assertSame(43, ShippingDistrict::count());
        $this->assertSame('Miraflores', ShippingDistrict::where('ubigeo', '150122')->value('name'));
        $this->assertSame('10.00', (string) ShippingDistrict::where('ubigeo', '150122')->value('cost'));
    }

    public function test_the_seeder_keeps_the_rates_that_were_edited(): void
    {
        $this->seed(ShippingSeeder::class);
        ShippingDistrict::where('ubigeo', '150122')->first()->update(['cost' => 12]);

        $this->seed(ShippingSeeder::class);

        $this->assertSame('12.00', (string) ShippingDistrict::where('ubigeo', '150122')->value('cost'));
    }

    public function test_a_district_cannot_cost_less_than_the_minimum_of_its_zone(): void
    {
        $zone = ShippingZone::factory()->create(['name' => 'Lima Metropolitana', 'min_cost' => 10]);
        $district = ShippingDistrict::factory()->for($zone, 'zone')->create(['cost' => 10]);

        $this->expectException(ShippingCostBelowMinimum::class);
        $this->expectExceptionMessage('El costo de envío de Lima Metropolitana no puede ser menor a S/ 10.00.');

        $district->update(['cost' => 9.99]);
    }

    public function test_a_new_district_below_the_minimum_is_rejected_and_a_higher_one_is_accepted(): void
    {
        $zone = ShippingZone::factory()->create(['min_cost' => 15]);

        $this->assertSame('18.50', (string) ShippingDistrict::factory()->for($zone, 'zone')->create(['cost' => 18.5])->cost);

        $this->expectException(ShippingCostBelowMinimum::class);
        ShippingDistrict::factory()->for($zone, 'zone')->create(['cost' => 14]);
    }

    public function test_raising_the_zone_minimum_lifts_the_districts_below_it(): void
    {
        $zone = ShippingZone::factory()->create(['min_cost' => 10]);
        $cheap = ShippingDistrict::factory()->for($zone, 'zone')->create(['cost' => 10]);
        $dear = ShippingDistrict::factory()->for($zone, 'zone')->create(['cost' => 20]);

        $zone->update(['min_cost' => 12]);

        $this->assertSame('12.00', (string) $cheap->fresh()->cost);
        $this->assertSame('20.00', (string) $dear->fresh()->cost);
    }

    public function test_a_zone_with_districts_cannot_be_deleted(): void
    {
        $zone = ShippingZone::factory()->create();
        ShippingDistrict::factory()->for($zone, 'zone')->create();

        $this->expectException(QueryException::class);
        $zone->delete();
    }

    public function test_the_quote_uses_the_district_rate(): void
    {
        $zone = ShippingZone::factory()->create(['min_cost' => 15]);
        ShippingDistrict::factory()->for($zone, 'zone')->create(['ubigeo' => '150501', 'cost' => 17]);

        $quote = $this->service()->quote('150501', '80.00');

        $this->assertSame('17.00', $quote->cost);
        $this->assertFalse($quote->isFree);
    }

    public function test_shipping_is_free_from_the_configured_amount(): void
    {
        config(['shop.free_shipping_from' => 150]);
        $zone = ShippingZone::factory()->create(['min_cost' => 10]);
        ShippingDistrict::factory()->for($zone, 'zone')->create(['ubigeo' => '150122', 'cost' => 10]);

        $below = $this->service()->quote('150122', '149.99');
        $exact = $this->service()->quote('150122', '150.00');

        $this->assertSame('10.00', $below->cost);
        $this->assertSame('0.00', $exact->cost);
        $this->assertTrue($exact->isFree);
        $this->assertSame('10.00', $exact->baseCost);
    }

    public function test_there_is_no_quote_for_unknown_or_inactive_destinations(): void
    {
        $zone = ShippingZone::factory()->create();
        ShippingDistrict::factory()->for($zone, 'zone')->create(['ubigeo' => '150101', 'is_active' => false]);
        $off = ShippingZone::factory()->create(['is_active' => false]);
        ShippingDistrict::factory()->for($off, 'zone')->create(['ubigeo' => '150102']);

        $this->assertNull($this->service()->quote('999999', '50'));
        $this->assertNull($this->service()->quote('150101', '50'));
        $this->assertNull($this->service()->quote('150102', '50'));
    }

    public function test_the_checkout_list_groups_active_districts_by_zone_and_skips_empty_zones(): void
    {
        $lima = ShippingZone::factory()->create(['name' => 'Lima Metropolitana', 'position' => 0]);
        ShippingZone::factory()->create(['name' => 'Zona vacía', 'position' => 1]);
        ShippingDistrict::factory()->for($lima, 'zone')->create(['name' => 'Surco']);
        ShippingDistrict::factory()->for($lima, 'zone')->create(['name' => 'Ate']);
        ShippingDistrict::factory()->for($lima, 'zone')->create(['name' => 'Oculto', 'is_active' => false]);

        $zones = $this->service()->zonesWithDistricts();

        $this->assertSame(['Lima Metropolitana'], $zones->pluck('name')->all());
        $this->assertSame(['Ate', 'Surco'], $zones->first()->districts->pluck('name')->all());
    }
}
