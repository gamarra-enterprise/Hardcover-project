<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::PENDING,
            'shipping_address' => Address::factory()->make()->toSnapshot(),
            ...Order::totalsFor('0', '0'),
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function guest(): static
    {
        return $this->state(['user_id' => null]);
    }

    /**
     * Add items for the given products (one unit each) and set the totals from them.
     *
     * @param  iterable<Product>  $products
     */
    public function withItems(iterable $products, string $shippingCost = '0'): static
    {
        return $this->afterCreating(function (Order $order) use ($products, $shippingCost) {
            $subtotal = '0.00';

            foreach ($products as $product) {
                $item = $order->items()->create(OrderItem::valuesFor($product->loadMissing('bookDetail'), 1));
                $subtotal = bcadd($subtotal, $item->lineTotal(), 2);
            }

            $order->update(Order::totalsFor($subtotal, $shippingCost));
        });
    }
}
