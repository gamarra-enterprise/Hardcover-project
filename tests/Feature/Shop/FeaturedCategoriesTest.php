<?php

namespace Tests\Feature\Shop;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturedCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function withProduct(string $name, bool $featured): Category
    {
        $category = Category::factory()->create(['name' => $name, 'featured' => $featured]);
        $category->products()->attach(Product::factory()->create(['stock' => 3]));

        return $category;
    }

    public function test_the_catalog_puts_non_featured_genres_under_more_genres(): void
    {
        $this->withProduct('Narrativa', true);
        $this->withProduct('Rareza', false);

        $this->get('/catalogo')->assertOk()->assertSee('Narrativa')->assertSee('Más géneros')->assertSee('Rareza');
    }

    public function test_without_featured_ones_everything_is_a_chip_and_there_is_no_more_button(): void
    {
        $this->withProduct('Uno', false);
        $this->withProduct('Dos', false);

        $this->get('/catalogo')->assertOk()->assertSee('Uno')->assertSee('Dos')->assertDontSee('Más géneros');
    }

    public function test_staff_toggle_a_category_and_customers_cannot(): void
    {
        $category = $this->withProduct('Poesía', false);

        $this->actingAs(User::factory()->create())->patch(route('admin.categories.featured', $category))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.categories.featured', $category))->assertSessionHas('notice');
        $this->assertTrue($category->fresh()->featured);
        $this->assertDatabaseHas('activity_logs', ['action' => 'category.featured_changed']);
    }

    public function test_cover_ratio_follows_the_book_within_limits(): void
    {
        $this->assertSame(0.667, round((new Product(['width_mm' => 140, 'height_mm' => 210]))->coverRatio(), 3));
        $this->assertSame(1.3, (new Product(['width_mm' => 400, 'height_mm' => 200]))->coverRatio());
        $this->assertSame(0.55, (new Product(['width_mm' => 50, 'height_mm' => 300]))->coverRatio());
        $this->assertEqualsWithDelta(2 / 3, (new Product)->coverRatio(), 0.001);
    }
}
