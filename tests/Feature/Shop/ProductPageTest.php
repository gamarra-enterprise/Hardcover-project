<?php

namespace Tests\Feature\Shop;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    private function book(array $attributes = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'name' => 'Crónicas marcianas', 'slug' => 'cronicas-marcianas', 'price' => 66,
            'height_mm' => 240, 'width_mm' => 165,
        ], $attributes));
        $product->bookDetail()->create([
            'author' => 'Ray Bradbury', 'publisher' => 'Zorro Rojo', 'isbn_13' => '9788412537109',
            'pages' => 136, 'format' => 'Tapa blanda', 'author_bio' => 'Escritor norteamericano.',
        ]);

        return $product;
    }

    public function test_it_shows_the_book_details(): void
    {
        $this->book();

        $this->get('/producto/cronicas-marcianas')
            ->assertOk()
            ->assertSee('Crónicas marcianas')
            ->assertSee('Ray Bradbury')
            ->assertSee('S/ 66.00')
            ->assertSee('IGV incluido')
            ->assertSeeInOrder(['Editorial', 'Zorro Rojo'])
            ->assertSeeInOrder(['ISBN', '9788412537109'])
            ->assertSeeInOrder(['Páginas', '136'])
            ->assertSeeInOrder(['Tamaño', '24 × 16.5 cm'])
            ->assertSee('Escritor norteamericano.')
            ->assertSee('Disponible');
    }

    public function test_the_product_page_never_shows_the_cost_price(): void
    {
        $this->book(['cost_price' => 36.3]);

        $this->get('/producto/cronicas-marcianas')->assertDontSee('36.30')->assertDontSee('cost_price');
    }

    public function test_missing_data_is_left_out_instead_of_shown_empty(): void
    {
        Product::factory()->create(['name' => 'Llavero', 'slug' => 'llavero', 'height_mm' => null, 'width_mm' => null]);

        $this->get('/producto/llavero')->assertOk()->assertDontSee('Tamaño')->assertDontSee('Ficha técnica')->assertDontSee('Sobre el autor');
    }

    public function test_it_marks_sale_price_and_stock_levels(): void
    {
        $this->book(['price' => 100, 'sale_price' => 80, 'stock' => 2]);

        $this->get('/producto/cronicas-marcianas')
            ->assertSee('S/ 80.00')
            ->assertSee('S/ 100.00')
            ->assertSee('-20%')
            ->assertSee('Últimas 2 unidades');
    }

    public function test_the_last_copy_is_announced_in_the_singular(): void
    {
        $this->book(['stock' => 1]);

        $this->get('/producto/cronicas-marcianas')->assertSee('Última unidad')->assertDontSee('Últimas 1');
    }

    public function test_a_sold_out_product_stays_visible_and_says_so(): void
    {
        $this->book(['stock' => 0]);

        $this->get('/producto/cronicas-marcianas')->assertOk()->assertSee('Sin stock');
    }

    public function test_a_hidden_product_is_a_404_for_the_public_but_open_to_staff(): void
    {
        $this->book(['status' => 'hidden']);

        $this->get('/producto/cronicas-marcianas')->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/producto/cronicas-marcianas')->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get('/producto/cronicas-marcianas')->assertOk();
    }

    public function test_an_unknown_slug_is_a_404(): void
    {
        $this->get('/producto/no-existe')->assertNotFound();
    }

    public function test_it_links_to_the_catalog_by_category_and_suggests_related_books(): void
    {
        $category = Category::factory()->create(['name' => 'Ficción', 'slug' => 'ficcion']);
        $this->book()->categories()->attach($category);
        Product::factory()->create(['name' => 'Libro parecido'])->categories()->attach($category);
        Product::factory()->create(['name' => 'Libro distinto']);

        $this->get('/producto/cronicas-marcianas')
            ->assertSee(route('catalog', ['genero' => 'ficcion']), false)
            ->assertSee('También te puede gustar')
            ->assertSee('Libro parecido')
            ->assertDontSee('Libro distinto');
    }

    public function test_the_cards_link_to_the_product_page(): void
    {
        $this->book();

        $this->get('/')->assertSee(route('products.show', 'cronicas-marcianas'), false);
    }
}
