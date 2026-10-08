<?php

namespace Tests\Feature\Shop;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_lists_active_products_and_hides_inactive_ones(): void
    {
        Product::factory()->book()->create(['name' => 'Libro visible']);
        Product::factory()->hidden()->create(['name' => 'Libro oculto']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Libro visible')
            ->assertDontSee('Libro oculto');
    }

    public function test_home_renders_without_products(): void
    {
        $this->get('/')->assertOk()->assertSee('Pronto tendremos libros nuevos');
    }

    public function test_product_image_is_used_when_present_and_a_placeholder_otherwise(): void
    {
        Product::factory()->create(['name' => 'Con portada', 'image_path' => 'products/con-portada.webp']);
        Product::factory()->create(['name' => 'Sin portada']);

        $this->get('/')
            ->assertSee('storage/products/con-portada.webp', false)
            ->assertSee('cover-ph', false);
    }

    public function test_header_changes_with_the_role(): void
    {
        $this->get('/')->assertSee('Ingresar')->assertDontSee('Panel');

        $this->actingAs(User::factory()->create())->get('/')->assertDontSee('Panel');
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertSee('Panel')->assertDontSee('Super admin');
        $this->actingAs(User::factory()->superAdmin()->create())->get('/')->assertSee('Super admin');
    }
}
