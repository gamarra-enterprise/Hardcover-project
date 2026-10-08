<?php

namespace Tests\Feature\Cart;

use App\Livewire\Cart\AddToCart;
use App\Livewire\Cart\CartBadge;
use App\Livewire\Cart\CartDrawer;
use App\Livewire\Cart\CartPage;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class CartComponentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_adds_from_the_product_page_without_touching_the_stock(): void
    {
        $product = Product::factory()->create(['stock' => 8]);

        Livewire::test(AddToCart::class, ['product' => $product])
            ->call('increment')
            ->call('increment')
            ->assertSet('quantity', 3)
            ->call('add')
            ->assertDispatched('cart-updated')
            ->assertDispatched('cart-open')
            ->assertSee('Añadido a tu carrito')
            ->assertSet('quantity', 1);

        $this->assertSame(3, app(CartService::class)->count());
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_the_quantity_selector_stops_at_the_units_still_available_to_this_buyer(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $this->actingAs(User::factory()->create());
        app(CartService::class)->add($product, 2);

        Livewire::test(AddToCart::class, ['product' => $product])
            ->assertSee('Ya tienes 2 en tu carrito')
            ->call('increment')
            ->call('increment')
            ->assertSet('quantity', 1);
    }

    public function test_when_the_buyer_holds_every_unit_the_page_offers_the_cart_instead(): void
    {
        $product = Product::factory()->create(['stock' => 1]);
        $this->actingAs(User::factory()->create());
        app(CartService::class)->add($product);

        Livewire::test(AddToCart::class, ['product' => $product])
            ->assertSee('Ya tienes en tu carrito todas las unidades disponibles')
            ->assertSee('Ver mi carrito')
            ->assertDontSee('Añadir al carrito');
    }

    public function test_the_last_unit_can_be_in_many_carts_and_the_page_never_blocks_the_next_buyer(): void
    {
        $product = Product::factory()->create(['stock' => 1]);

        foreach ([User::factory()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user);
            Livewire::test(AddToCart::class, ['product' => $product])->call('add')->assertSee('Añadido a tu carrito');
        }

        $this->assertSame(2, CartItem::count());
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_a_sold_out_product_cannot_be_added(): void
    {
        $product = Product::factory()->outOfStock()->create();

        Livewire::test(AddToCart::class, ['product' => $product])
            ->assertSee('Sin stock')
            ->assertDontSee('Añadir al carrito');
    }

    public function test_the_product_id_cannot_be_changed_from_the_browser(): void
    {
        $product = Product::factory()->create();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(AddToCart::class, ['product' => $product])->set('productId', 999);
    }

    public function test_the_cart_page_lists_lines_and_changes_quantities(): void
    {
        $product = Product::factory()->create(['name' => 'Libro uno', 'price' => 40, 'stock' => 5]);
        $this->actingAs(User::factory()->create());
        app(CartService::class)->add($product);
        $item = CartItem::first();

        Livewire::test(CartPage::class)
            ->assertSee('Libro uno')
            ->assertSee('S/ 40.00')
            ->call('changeBy', $item->id, 2)
            ->assertSee('S/ 120.00')
            ->call('changeBy', $item->id, -1)
            ->assertSee('S/ 80.00')
            ->call('remove', $item->id)
            ->assertSee('Tu carrito está vacío');

        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_the_cart_page_works_for_guests_through_the_route(): void
    {
        $this->get('/carrito')->assertOk()->assertSee('Tu carrito está vacío');

        app(CartService::class)->add(Product::factory()->create(['name' => 'Libro de invitado']));

        $this->get('/carrito')->assertOk()->assertSee('Libro de invitado');
    }

    public function test_a_line_that_no_longer_fits_the_stock_is_flagged_and_can_be_adjusted(): void
    {
        $product = Product::factory()->create(['price' => 10, 'stock' => 5]);
        $this->actingAs(User::factory()->create());
        app(CartService::class)->add($product, 4);
        $product->update(['stock' => 2]);
        $item = CartItem::first();

        Livewire::test(CartPage::class)
            ->assertSee('Solo quedan 2 unidades.')
            ->assertSee('Ajustar a 2')
            ->assertSee('El subtotal no incluye los productos con aviso')
            ->call('setTo', $item->id, 2)
            ->assertDontSee('Solo quedan')
            ->assertSee('S/ 20.00');
    }

    public function test_the_page_warns_that_saving_in_the_cart_does_not_reserve_stock(): void
    {
        $this->actingAs(User::factory()->create());
        app(CartService::class)->add(Product::factory()->create());

        Livewire::test(CartPage::class)->assertSee('no lo reserva');
    }

    public function test_the_cart_actions_ignore_lines_of_other_carts(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $owner = User::factory()->create();
        $this->actingAs($owner);
        app(CartService::class)->add($product);
        $foreign = CartItem::first();

        $this->actingAs(User::factory()->create());
        Livewire::test(CartPage::class)
            ->call('changeBy', $foreign->id, 3)
            ->call('setTo', $foreign->id, 5)
            ->call('remove', $foreign->id);

        $this->assertSame(1, $foreign->fresh()->quantity);
    }

    public function test_the_drawer_and_the_badge_show_the_cart(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(CartDrawer::class)->assertSee('Tu carrito está vacío');
        Livewire::test(CartBadge::class)->assertDontSee('en el carrito');

        app(CartService::class)->add(Product::factory()->create(['name' => 'Libro del panel', 'stock' => 9]), 3);

        Livewire::test(CartDrawer::class)->assertSee('Libro del panel')->assertSee('Ver carrito completo');
        Livewire::test(CartBadge::class)->assertSee('3 productos en el carrito');
    }

    public function test_the_shop_pages_carry_the_cart_button_and_the_add_form(): void
    {
        $product = Product::factory()->create(['slug' => 'libro']);

        $this->get('/')->assertSee('Abrir carrito');
        $this->get('/producto/libro')->assertSee('Añadir al carrito')->assertSee('wire:click="add"', false);
    }
}
