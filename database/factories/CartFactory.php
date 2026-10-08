<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'session_id' => null];
    }

    public function guest(): static
    {
        return $this->state(['user_id' => null, 'session_id' => Str::random(40)]);
    }
}
