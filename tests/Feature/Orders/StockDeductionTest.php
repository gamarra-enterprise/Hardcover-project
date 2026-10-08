<?php

namespace Tests\Feature\Orders;

use App\Enums\ProductStatus;
use App\Exceptions\InsufficientStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockDeductionTest extends TestCase
{
    use RefreshDatabase;

    private function orderOf(array $quantities): Order
    {
        $order = Order::factory()->create();

        foreach ($quantities as [$product, $quantity]) {
            $order->items()->create(OrderItem::valuesFor($product, $quantity));
        }

        return $order;
    }

    private function inventory(): InventoryService
    {
        return app(InventoryService::class);
    }

    public function test_paying_an_order_takes_its_units_out_of_stock_and_nothing_else(): void
    {
        $a = Product::factory()->create(['stock' => 10]);
        $b = Product::factory()->create(['stock' => 4]);
        $untouched = Product::factory()->create(['stock' => 7]);
        $order = $this->orderOf([[$a, 3], [$b, 1]]);

        $this->inventory()->deductForOrder($order);

        $this->assertSame(7, $a->fresh()->stock);
        $this->assertSame(3, $b->fresh()->stock);
        $this->assertSame(7, $untouched->fresh()->stock);
        $this->assertNotNull($order->fresh()->stock_deducted_at);
    }

    public function test_taking_the_last_unit_marks_the_product_as_out_of_stock(): void
    {
        $product = Product::factory()->create(['stock' => 2]);

        $this->inventory()->deductForOrder($this->orderOf([[$product, 2]]));

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertSame(ProductStatus::OUT_OF_STOCK, $product->fresh()->status);
    }

    public function test_deducting_twice_takes_the_units_only_once(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->orderOf([[$product, 3]]);

        $this->inventory()->deductForOrder($order);
        $this->inventory()->deductForOrder($order);
        $this->inventory()->deductForOrder($order->fresh());

        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_when_the_last_unit_was_sold_to_someone_else_nothing_is_deducted(): void
    {
        $plenty = Product::factory()->create(['stock' => 10]);
        $scarce = Product::factory()->create(['stock' => 1, 'name' => 'Último libro']);
        $first = $this->orderOf([[$scarce, 1]]);
        $second = $this->orderOf([[$plenty, 2], [$scarce, 1]]);

        $this->inventory()->deductForOrder($first);

        try {
            $this->inventory()->deductForOrder($second);
            $this->fail('The second order should have been refused.');
        } catch (InsufficientStock $e) {
            $this->assertCount(1, $e->shortages);
            $this->assertStringContainsString('quedan 0', $e->shortages[0]);
        }

        // All or nothing: the product that did have stock was not touched either.
        $this->assertSame(10, $plenty->fresh()->stock);
        $this->assertNull($second->fresh()->stock_deducted_at);
    }

    public function test_an_order_with_a_deleted_product_cannot_be_deducted(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $order = $this->orderOf([[$product, 1]]);
        $product->delete();

        $this->expectException(InsufficientStock::class);
        $this->inventory()->deductForOrder($order);
    }

    public function test_restoring_gives_the_units_back_once(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = $this->orderOf([[$product, 3]]);
        $this->inventory()->deductForOrder($order);
        $this->assertSame(ProductStatus::OUT_OF_STOCK, $product->fresh()->status);

        $this->inventory()->restoreForOrder($order);
        $this->inventory()->restoreForOrder($order);

        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(ProductStatus::ACTIVE, $product->fresh()->status);
        $this->assertNull($order->fresh()->stock_deducted_at);
    }

    public function test_restoring_an_order_that_never_took_stock_changes_nothing(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $this->inventory()->restoreForOrder($this->orderOf([[$product, 2]]));

        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_a_hidden_product_is_still_deducted_for_an_order_already_paid(): void
    {
        $product = Product::factory()->hidden()->create(['stock' => 5]);

        $this->inventory()->deductForOrder($this->orderOf([[$product, 2]]));

        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(ProductStatus::HIDDEN, $product->fresh()->status);
    }
}
