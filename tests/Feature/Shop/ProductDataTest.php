<?php

namespace Tests\Feature\Shop;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_can_exist_without_weight_or_depth(): void
    {
        $product = Product::factory()->create([
            'weight_grams' => null,
            'width_mm' => 165,
            'height_mm' => 240,
            'depth_mm' => null,
        ]);

        $product->refresh();
        $this->assertNull($product->weight_grams);
        $this->assertNull($product->depth_mm);
        $this->assertSame(165, $product->width_mm);
    }

    public function test_cost_price_is_stored_but_never_serialized(): void
    {
        $product = Product::factory()->create(['price' => 66, 'cost_price' => 36.3]);

        $this->assertSame('36.30', (string) $product->fresh()->cost_price);
        $this->assertArrayNotHasKey('cost_price', $product->fresh()->toArray());
        $this->assertStringNotContainsString('cost_price', $product->fresh()->toJson());
    }

    public function test_book_details_keep_the_full_genre_text_and_author_bio(): void
    {
        $product = Product::factory()->book()->create();
        $product->bookDetail->update(['genres' => 'ficción / novela / clásicos', 'author_bio' => 'Biografía.']);

        $this->assertSame('ficción / novela / clásicos', $product->fresh()->bookDetail->genres);
        $this->assertSame('Biografía.', $product->fresh()->bookDetail->author_bio);
    }
}
