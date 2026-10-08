<?php

namespace Tests\Feature\Cart;

use App\Enums\CartLineIssue;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private function cart(): CartService
    {
        return app(CartService::class);
    }

    public function test_adding_to_the_cart_does_not_change_the_stock(): void
    {
        $product = Product::factory()->create(['stock' => 3]);

        $this->actingAs(User::factory()->create());
        $this->cart()->add($product, 2);

        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_several_buyers_can_hold_the_last_unit_at_the_same_time(): void
    {
        $product = Product::factory()->create(['stock' => 1]);

        foreach (User::factory()->count(3)->create() as $user) {
            $this->actingAs($user);
            $result = $this->cart()->add($product);

            $this->assertSame(1, $result['added']);
            $this->assertFalse($result['limited']);
        }

        $this->assertSame(3, CartItem::where('product_id', $product->id)->count());
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_one_buyer_cannot_take_more_units_than_exist(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $this->actingAs(User::factory()->create());

        $first = $this->cart()->add($product, 2);
        $second = $this->cart()->add($product, 5);

        $this->assertSame(['added' => 2, 'in_cart' => 2, 'limited' => false], $first);
        $this->assertSame(['added' => 1, 'in_cart' => 3, 'limited' => true], $second);
        $this->assertSame(3, $this->cart()->count());
    }

    public function test_sold_out_and_hidden_products_cannot_be_added_and_leave_no_cart_behind(): void
    {
        $soldOut = Product::factory()->outOfStock()->create();
        $hidden = Product::factory()->hidden()->create();

        $this->assertSame(0, $this->cart()->add($soldOut)['added']);
        $this->assertSame(0, $this->cart()->add($hidden)['added']);
        $this->assertSame(0, Cart::count());
    }

    public function test_a_guest_cart_is_kept_between_requests_with_a_session_token(): void
    {
        $a = Product::factory()->create();
        $b = Product::factory()->create();

        $this->cart()->add($a);
        $this->cart()->add($a);
        $this->cart()->add($b);

        $this->assertSame(1, Cart::count());
        $cart = Cart::first();
        $this->assertNull($cart->user_id);
        $this->assertSame(session('cart_token'), $cart->session_id);
        $this->assertSame(3, $this->cart()->count());
        $this->assertCount(2, $cart->items);
    }

    public function test_a_guest_who_adds_nothing_has_no_cart(): void
    {
        $this->assertSame(0, $this->cart()->count());
        $this->assertTrue($this->cart()->summary()->isEmpty());
        $this->assertSame(0, Cart::count());
    }

    public function test_a_user_cart_is_stored_in_the_database_and_is_unique(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user);
        $this->cart()->add($product);
        $this->cart()->add($product);

        $this->assertSame(1, Cart::where('user_id', $user->id)->count());
        $this->assertSame(2, $user->cart->items->first()->quantity);
    }

    public function test_logging_in_merges_the_guest_cart_into_the_user_cart(): void
    {
        $user = User::factory()->create();
        $shared = Product::factory()->create(['stock' => 3]);
        $onlyGuest = Product::factory()->create();

        $this->actingAs($user);
        $this->cart()->add($shared, 2);
        Auth::logout();

        $this->cart()->add($shared, 2);
        $this->cart()->add($onlyGuest);
        $guestCartId = Cart::whereNull('user_id')->value('id');

        Auth::login($user);

        $lines = $user->fresh()->cart->items->pluck('quantity', 'product_id')->all();
        // 2 + 2 would be 4, but only 3 exist.
        $this->assertSame([$shared->id => 3, $onlyGuest->id => 1], $lines);
        $this->assertNull(Cart::find($guestCartId));
        $this->assertNull(session('cart_token'));
    }

    public function test_the_login_screen_keeps_the_guest_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->cart()->add($product);

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertSame($product->id, $user->fresh()->cart->items->first()->product_id);
    }

    public function test_quantity_changes_are_limited_by_the_stock_and_zero_removes_the_line(): void
    {
        $product = Product::factory()->create(['stock' => 4]);
        $this->actingAs(User::factory()->create());
        $this->cart()->add($product);
        $item = CartItem::first();

        $this->cart()->setQuantity($item->id, 10);
        $this->assertSame(4, $item->fresh()->quantity);

        $this->cart()->setQuantity($item->id, 2);
        $this->assertSame(2, $item->fresh()->quantity);

        $this->cart()->remove($item->id);
        $this->assertNull(CartItem::find($item->id));
    }

    public function test_a_line_of_another_cart_cannot_be_changed(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create());
        $this->cart()->add($product);
        $foreign = CartItem::first();

        $this->actingAs(User::factory()->create());
        $this->cart()->setQuantity($foreign->id, 5);
        $this->cart()->remove($foreign->id);

        $this->assertSame(1, $foreign->fresh()->quantity);
    }

    public function test_the_summary_flags_lines_that_can_no_longer_be_bought_and_leaves_them_alone(): void
    {
        $ok = Product::factory()->create(['price' => 50, 'stock' => 5]);
        $low = Product::factory()->create(['price' => 20, 'stock' => 5]);
        $gone = Product::factory()->create(['price' => 10, 'stock' => 5]);
        $hidden = Product::factory()->create(['price' => 10, 'stock' => 5]);

        $this->actingAs(User::factory()->create());
        foreach ([$ok, $low, $gone, $hidden] as $product) {
            $this->cart()->add($product, 3);
        }

        // Stock changes after the items were put in the cart.
        $low->update(['stock' => 2]);
        $gone->update(['stock' => 0]);
        $hidden->update(['status' => 'hidden']);

        $summary = $this->cart()->summary();
        $issues = collect($summary->lines)->mapWithKeys(fn ($l) => [$l->product->id => $l->issue]);

        $this->assertNull($issues[$ok->id]);
        $this->assertSame(CartLineIssue::EXCEEDS_STOCK, $issues[$low->id]);
        $this->assertSame(CartLineIssue::OUT_OF_STOCK, $issues[$gone->id]);
        $this->assertSame(CartLineIssue::HIDDEN, $issues[$hidden->id]);
        $this->assertTrue($summary->hasIssues());
        $this->assertSame('Solo quedan 2 unidades.', collect($summary->lines)->firstWhere('product.id', $low->id)->issueMessage());
        // Only the line that can be bought counts, and the quantities in the cart are untouched.
        $this->assertSame('150.00', $summary->subtotal);
        $this->assertSame(12, $summary->count);
        $this->assertSame(3, CartItem::where('product_id', $low->id)->value('quantity'));
    }

    public function test_the_summary_uses_the_current_sale_price_and_tracks_free_shipping(): void
    {
        config(['shop.free_shipping_from' => 100]);
        $product = Product::factory()->create(['price' => 90, 'sale_price' => 30, 'stock' => 10]);
        $this->actingAs(User::factory()->create());

        $this->cart()->add($product, 2);
        $summary = $this->cart()->summary();

        $this->assertSame('60.00', $summary->subtotal);
        $this->assertSame('40.00', $summary->missingForFreeShipping());
        $this->assertSame(60, $summary->freeShippingProgress());

        $product->update(['sale_price' => null]);
        $this->assertSame('180.00', $this->cart()->summary()->subtotal);
        $this->assertSame('0.00', $this->cart()->summary()->missingForFreeShipping());
    }
}
