<?php

namespace Database\Factories;

use App\Models\ShippingDistrict;
use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingDistrict>
 */
class ShippingDistrictFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shipping_zone_id' => ShippingZone::factory(),
            'name' => fake()->unique()->city(),
            'ubigeo' => fake()->unique()->numerify('15####'),
            'cost' => 10,
            'is_active' => true,
        ];
    }
}
