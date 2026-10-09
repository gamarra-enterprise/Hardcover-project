<?php

namespace Tests\Feature\Shop;

use App\Livewire\Shop\QuickView;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuickViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_opens_a_visible_product_and_closes(): void
    {
        $product = Product::factory()->create(['name' => 'Rayuela', 'stock' => 4]);

        Livewire::test(QuickView::class)
            ->assertDontSee('Rayuela')
            ->dispatch('quick-view', id: $product->id)
            ->assertSee('Rayuela')->assertSee('Añadir al carrito')->assertSee('Ver ficha completa')
            ->call('close')
            ->assertDontSee('Rayuela');
    }

    public function test_a_hidden_product_does_not_open(): void
    {
        $hidden = Product::factory()->hidden()->create(['name' => 'Secreto']);

        Livewire::test(QuickView::class)->dispatch('quick-view', id: $hidden->id)->assertDontSee('Secreto');
    }

    public function test_the_shop_pages_carry_the_window_and_cards_have_the_button(): void
    {
        Product::factory()->create(['name' => 'Ficciones', 'stock' => 2]);

        $this->get('/catalogo')->assertOk()->assertSee('Vista rápida: Ficciones')->assertSee('quick-view', false);
    }

    public function test_the_home_shows_the_club_form_and_the_menu_links(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Club de lectores Bookery')->assertSee(route('club.subscribe'), false)
            ->assertSee(route('collection.masvendidos'), false)->assertSee('Papelería y regalos');
    }
}
