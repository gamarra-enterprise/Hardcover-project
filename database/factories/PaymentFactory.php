<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => fake()->randomElement(['stripe', 'mercadopago']),
            'external_reference' => fake()->unique()->uuid(),
            'amount' => fake()->randomFloat(2, 20, 300),
            'currency' => 'PEN',
            'status' => PaymentStatus::PENDING,
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => PaymentStatus::COMPLETED, 'paid_at' => now()]);
    }
}
