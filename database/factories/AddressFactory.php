<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'shipping',
            'recipient_name' => fake()->name(),
            'phone' => fake()->numerify('9## ### ###'),
            'line1' => fake()->streetAddress(),
            'city' => 'Miraflores',
            'state' => 'Lima',
            'ubigeo' => '150122',
            'country' => 'PE',
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
