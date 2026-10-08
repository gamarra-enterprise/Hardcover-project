<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_follows_the_stock_count(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $this->assertSame(ProductStatus::ACTIVE, $product->fresh()->status);

        $product->update(['stock' => 0]);
        $this->assertSame(ProductStatus::OUT_OF_STOCK, $product->fresh()->status);

        $product->update(['stock' => 5]);
        $this->assertSame(ProductStatus::ACTIVE, $product->fresh()->status);
    }

    public function test_a_product_created_without_stock_is_out_of_stock(): void
    {
        $this->assertSame(ProductStatus::OUT_OF_STOCK, Product::factory()->outOfStock()->create()->fresh()->status);
    }

    public function test_hidden_stays_hidden_whatever_the_stock_does(): void
    {
        $product = Product::factory()->hidden()->create(['stock' => 10]);
        $product->update(['stock' => 0]);
        $product->update(['stock' => 4]);

        $this->assertSame(ProductStatus::HIDDEN, $product->fresh()->status);
    }

    public function test_the_visible_scope_keeps_active_and_out_of_stock_but_not_hidden(): void
    {
        $active = Product::factory()->create();
        $soldOut = Product::factory()->outOfStock()->create();
        Product::factory()->hidden()->create();

        $this->assertEqualsCanonicalizing([$active->id, $soldOut->id], Product::visible()->pluck('id')->all());
        $this->assertTrue($soldOut->isVisible());
    }

    public function test_the_shop_marks_sold_out_products_and_hides_hidden_ones(): void
    {
        Product::factory()->outOfStock()->create(['name' => 'Libro agotado']);
        Product::factory()->hidden()->create(['name' => 'Libro secreto']);

        $this->get('/')->assertSee('Libro agotado')->assertSee('Sin stock')->assertDontSee('Libro secreto');
    }

    public function test_status_labels_are_in_spanish(): void
    {
        $this->assertSame(
            ['Activo', 'Oculto', 'Sin stock'],
            array_map(fn (ProductStatus $s) => $s->label(), ProductStatus::cases()),
        );
    }
}
