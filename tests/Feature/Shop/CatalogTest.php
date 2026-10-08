<?php

namespace Tests\Feature\Shop;

use App\Livewire\Shop\ProductList;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private function book(string $name, string $author = 'Autor', array $attributes = [], string $genres = 'novela'): Product
    {
        $product = Product::factory()->create(['name' => $name] + $attributes);
        $product->bookDetail()->create(['author' => $author, 'genres' => $genres, 'isbn_13' => fake()->unique()->isbn13()]);

        return $product;
    }

    /** @return list<string> names shown, in order */
    private function names($component): array
    {
        return $component->instance()->products->pluck('name')->all();
    }

    public function test_the_catalog_page_renders_and_lists_visible_products_only(): void
    {
        $this->book('Libro visible');
        $this->book('Libro agotado', attributes: ['stock' => 0]);
        Product::factory()->hidden()->create(['name' => 'Libro oculto']);

        $this->get('/catalogo')
            ->assertOk()
            ->assertSee('Libro visible')
            ->assertSee('Libro agotado')
            ->assertDontSee('Libro oculto');
    }

    public function test_search_ignores_accents_and_case(): void
    {
        $this->book('Crónicas marcianas', 'Ray Bradbury');
        $this->book('Otro libro', 'Alguien');

        $component = Livewire::test(ProductList::class)->set('search', 'CRONICAS');

        $this->assertSame(['Crónicas marcianas'], $this->names($component));
    }

    public function test_search_finds_by_author_genre_and_isbn(): void
    {
        $a = $this->book('Uno', 'Gabriel García Márquez', genres: 'realismo mágico');
        $this->book('Dos', 'Otra persona');
        $a->bookDetail->update(['isbn_13' => '9788412537109']);

        $this->assertSame(['Uno'], $this->names(Livewire::test(ProductList::class)->set('search', 'garcia marquez')));
        $this->assertSame(['Uno'], $this->names(Livewire::test(ProductList::class)->set('search', 'REALISMO')));
        $this->assertSame(['Uno'], $this->names(Livewire::test(ProductList::class)->set('search', '978-8412-5371')));
    }

    public function test_a_search_with_wildcard_characters_is_taken_literally(): void
    {
        $this->book('Cien por ciento');

        $this->assertSame([], $this->names(Livewire::test(ProductList::class)->set('search', '%')));
    }

    public function test_filters_by_category_and_counts_products_per_category(): void
    {
        $novela = Category::factory()->create(['name' => 'Novela', 'slug' => 'novela']);
        $cuentos = Category::factory()->create(['name' => 'Cuentos', 'slug' => 'cuentos']);
        $this->book('A')->categories()->attach([$novela->id, $cuentos->id]);
        $this->book('B')->categories()->attach($novela->id);
        Category::factory()->create(['name' => 'Vacía', 'slug' => 'vacia']);

        $component = Livewire::test(ProductList::class)->set('category', 'cuentos');

        $this->assertSame(['A'], $this->names($component));
        $counts = $component->instance()->categories->pluck('visible_count', 'slug')->all();
        $this->assertSame(['novela' => 2, 'cuentos' => 1], $counts);
    }

    public function test_price_sale_and_stock_filters(): void
    {
        $this->book('Barato', attributes: ['price' => 20]);
        $this->book('Caro', attributes: ['price' => 100]);
        $this->book('Oferta', attributes: ['price' => 90, 'sale_price' => 30]);
        $this->book('Agotado', attributes: ['price' => 25, 'stock' => 0]);

        $this->assertEqualsCanonicalizing(['Barato', 'Oferta', 'Agotado'], $this->names(Livewire::test(ProductList::class)->set('maxPrice', 35)));
        $this->assertSame(['Oferta'], $this->names(Livewire::test(ProductList::class)->set('onSale', true)));
        $this->assertEqualsCanonicalizing(['Barato', 'Caro', 'Oferta'], $this->names(Livewire::test(ProductList::class)->set('inStock', true)));
    }

    public function test_a_price_filter_at_the_ceiling_does_not_filter(): void
    {
        $this->book('Barato', attributes: ['price' => 20]);
        $this->book('Caro', attributes: ['price' => 100]);

        $component = Livewire::test(ProductList::class);
        $component->set('maxPrice', $component->instance()->priceCeiling);

        $this->assertCount(2, $this->names($component));
        $this->assertSame([], $component->instance()->activeFilters);
    }

    public function test_sorting_uses_the_sale_price_and_ignores_accents(): void
    {
        $this->book('Ñandú', attributes: ['price' => 50]);
        $this->book('Álvaro', attributes: ['price' => 80, 'sale_price' => 10]);
        $this->book('Zorro', attributes: ['price' => 30]);

        $this->assertSame(['Álvaro', 'Zorro', 'Ñandú'], $this->names(Livewire::test(ProductList::class)->set('sort', 'asc')));
        $this->assertSame(['Ñandú', 'Zorro', 'Álvaro'], $this->names(Livewire::test(ProductList::class)->set('sort', 'desc')));
        $this->assertSame(['Álvaro', 'Ñandú', 'Zorro'], $this->names(Livewire::test(ProductList::class)->set('sort', 'az')));
    }

    public function test_recommended_order_puts_sold_out_products_last(): void
    {
        $this->book('Aaa agotado', attributes: ['stock' => 0]);
        $this->book('Bbb');

        $this->assertSame(['Bbb', 'Aaa agotado'], $this->names(Livewire::test(ProductList::class)));
    }

    public function test_results_are_paginated_and_a_filter_goes_back_to_page_one(): void
    {
        foreach (range(1, 14) as $i) {
            $this->book(sprintf('Libro %02d', $i));
        }

        $component = Livewire::test(ProductList::class)->set('sort', 'az');
        $this->assertCount(12, $this->names($component));

        $component->call('nextPage');
        $this->assertSame(['Libro 13', 'Libro 14'], $this->names($component));

        $component->set('inStock', true);
        $this->assertSame(1, $component->instance()->products->currentPage());
    }

    public function test_active_filters_can_be_removed_one_by_one_or_all_together(): void
    {
        $this->book('Oferta', attributes: ['sale_price' => 5]);

        $component = Livewire::test(ProductList::class)->set('search', 'x')->set('onSale', true)->set('inStock', true);
        $this->assertSame(['search', 'onSale', 'inStock'], array_keys($component->instance()->activeFilters));

        $component->call('clear', 'search');
        $this->assertSame(['onSale', 'inStock'], array_keys($component->instance()->activeFilters));

        $component->call('resetFilters');
        $this->assertSame([], $component->instance()->activeFilters);
    }

    public function test_the_filters_come_from_the_url(): void
    {
        $this->book('Crónicas marcianas');
        $this->book('Otro');

        $this->get('/catalogo?q=cronicas')->assertSee('Crónicas marcianas')->assertDontSee('Otro');
    }

    public function test_an_empty_result_offers_to_clear_the_filters(): void
    {
        Livewire::test(ProductList::class)->set('search', 'nada')->assertSee('No encontramos nada');
    }

    public function test_the_header_has_a_search_form_and_a_catalog_link(): void
    {
        $this->get('/')
            ->assertSee('action="'.route('catalog').'"', false)
            ->assertSee('Título, autor o ISBN');
    }
}
