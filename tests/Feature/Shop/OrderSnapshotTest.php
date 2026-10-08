<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_items_keep_their_snapshot_when_the_product_changes(): void
    {
        $book = Product::factory()->book()->create(['name' => 'Original', 'price' => 50]);
        $keyring = Product::factory()->create(['price' => 20, 'sale_price' => 16]);

        $order = Order::factory()->withItems([$book, $keyring], '7')->create();

        $book->update(['name' => 'Renamed', 'price' => 99]);
        $keyring->delete();

        $items = $order->fresh()->items;
        $this->assertSame('Original', $items[0]->product_snapshot['name']);
        $this->assertNotNull($items[0]->product_snapshot['author']);
        $this->assertSame('50.00', (string) $items[0]->unit_price);
        $this->assertSame('16.00', (string) $items[1]->unit_price);
        $this->assertNull($items[1]->product_id);
    }

    public function test_totals_include_igv_already_contained_in_the_prices(): void
    {
        $order = Order::factory()
            ->withItems([Product::factory()->create(['price' => 100])], '18')
            ->create();

        $order->refresh();
        $this->assertSame('100.00', (string) $order->subtotal);
        $this->assertSame('118.00', (string) $order->total);
        $this->assertSame('18.00', (string) $order->tax);
    }

    public function test_tracking_code_is_generated_and_guests_can_order(): void
    {
        $order = Order::factory()->guest()->status(OrderStatus::CONFIRMED)->create();

        $this->assertMatchesRegularExpression('/^HB-\d{6}-\d{4}$/', $order->tracking_code);
        $this->assertNull($order->user_id);
        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
    }
}
