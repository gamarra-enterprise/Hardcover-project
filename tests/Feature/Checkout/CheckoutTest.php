<?php

namespace Tests\Feature\Checkout;

use App\Enums\OrderStatus;
use App\Livewire\Cart\CartDrawer;
use App\Livewire\Cart\CartPage;
use App\Livewire\Checkout\Checkout;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingDistrict;
use App\Models\ShippingZone;
use App\Models\User;
use App\Notifications\OrderPlaced;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Signed addresses carry an expiry time; with the clock frozen two of them made a moment apart are equal.
        $this->freezeTime();

        config(['shop.free_shipping_from' => 150]);

        $lima = ShippingZone::factory()->create(['name' => 'Lima Metropolitana', 'slug' => 'lima-metropolitana', 'min_cost' => 10, 'position' => 0]);
        ShippingZone::factory()->create(['name' => 'Lima Provincia', 'slug' => 'lima-provincia', 'min_cost' => 15, 'position' => 1]);
        ShippingDistrict::factory()->for($lima, 'zone')->create(['name' => 'Miraflores', 'ubigeo' => '150122', 'cost' => 10]);
        ShippingDistrict::factory()->for($lima, 'zone')->create(['name' => 'Barranco', 'ubigeo' => '150104', 'cost' => 12]);
        ShippingDistrict::factory()->for($lima, 'zone')->create(['name' => 'Oculto', 'ubigeo' => '150199', 'is_active' => false]);
    }

    private function cartWith(int $quantity = 1, array $attributes = []): Product
    {
        $product = Product::factory()->book()->create(array_merge(['price' => 40, 'stock' => 20], $attributes));
        app(CartService::class)->add($product, $quantity);

        return $product;
    }

    private function fill($component, array $overrides = [])
    {
        $data = array_merge([
            'name' => 'Ana Quispe', 'email' => 'ana@example.com', 'phone' => '987 654 321',
            'ubigeo' => '150122', 'line1' => 'Av. Larco 1234, dpto 502', 'line2' => 'Frente al parque',
        ], $overrides);

        foreach ($data as $property => $value) {
            $component->set($property, $value);
        }

        return $component;
    }

    public function test_an_empty_cart_goes_back_to_the_cart(): void
    {
        Livewire::test(Checkout::class)->assertRedirect(route('cart'));

        $this->get('/checkout')->assertRedirect(route('cart'))->assertSessionHas('notice', 'Tu carrito está vacío.');
    }

    public function test_a_cart_with_warnings_cannot_start_the_checkout(): void
    {
        $product = $this->cartWith(3);
        $product->update(['stock' => 1]);

        Livewire::test(Checkout::class)->assertRedirect(route('cart'));
        $this->get('/checkout')->assertRedirect(route('cart'));
    }

    public function test_the_form_lists_districts_by_zone_and_shows_the_rest_as_coming_soon(): void
    {
        $this->cartWith();

        $html = Livewire::test(Checkout::class)
            ->assertSee('Miraflores')
            ->assertSee('Barranco')
            ->assertDontSee('Oculto')
            ->assertSee('Lima Provincia')
            ->assertSee('Otras regiones')
            ->assertSee('Por ahora enviamos solo a Lima')
            ->html();

        // Lima Provincia has no active district yet and, like the other regions, says "Próximamente".
        $this->assertSame(2, substr_count($html, 'Próximamente</option>'));
    }

    public function test_a_zone_with_districts_no_longer_says_coming_soon(): void
    {
        $this->cartWith();
        ShippingDistrict::factory()->for(ShippingZone::where('slug', 'lima-provincia')->first(), 'zone')->create(['name' => 'Chilca', 'ubigeo' => '150505', 'cost' => 15]);

        $html = Livewire::test(Checkout::class)->assertSee('Chilca')->html();

        $this->assertSame(1, substr_count($html, 'Próximamente</option>'));
    }

    public function test_the_shipping_cost_follows_the_chosen_district(): void
    {
        $this->cartWith(1);

        Livewire::test(Checkout::class)
            ->assertSee('Elige tu distrito')
            ->set('ubigeo', '150104')
            ->assertSee('S/ 12.00')
            ->assertSee('S/ 52.00')
            ->set('ubigeo', '150122')
            ->assertSee('S/ 50.00');
    }

    public function test_shipping_is_free_from_the_configured_amount(): void
    {
        $this->cartWith(4);

        Livewire::test(Checkout::class)->set('ubigeo', '150104')->assertSee('Gratis')->assertSee('S/ 160.00');
    }

    public function test_a_logged_in_customer_finds_their_data_and_last_address_filled_in(): void
    {
        $user = User::factory()->create(['name' => 'Cuenta Nombre', 'email' => 'cuenta@example.com']);
        Address::factory()->for($user)->create(['recipient_name' => 'Ana Quispe', 'phone' => '987654321', 'line1' => 'Calle 1', 'ubigeo' => '150122', 'is_default' => true]);
        $this->actingAs($user);
        $this->cartWith();

        Livewire::test(Checkout::class)
            ->assertSet('name', 'Ana Quispe')
            ->assertSet('email', 'cuenta@example.com')
            ->assertSet('phone', '987654321')
            ->assertSet('line1', 'Calle 1')
            ->assertSet('ubigeo', '150122');
    }

    public function test_a_saved_address_in_a_district_that_is_no_longer_served_is_not_preselected(): void
    {
        $user = User::factory()->create();
        Address::factory()->for($user)->create(['ubigeo' => '150199', 'is_default' => true]);
        $this->actingAs($user);
        $this->cartWith();

        Livewire::test(Checkout::class)->assertSet('ubigeo', '');
    }

    public function test_every_field_is_checked_with_spanish_messages(): void
    {
        $this->cartWith();

        Livewire::test(Checkout::class)
            ->call('place')
            ->assertHasErrors(['name' => 'required', 'email' => 'required', 'phone' => 'required', 'ubigeo' => 'required', 'line1' => 'required'])
            ->assertSee('El campo dirección es obligatorio.')
            ->assertSee('Elige el distrito de entrega.');
    }

    public function test_the_phone_is_cleaned_and_must_be_a_peruvian_mobile(): void
    {
        $this->cartWith();

        foreach (['987654321', '987 654 321', '987-654-321', '+51 987 654 321', '51987654321'] as $valid) {
            $this->fill(Livewire::test(Checkout::class), ['phone' => $valid])->call('place')->assertHasNoErrors('phone');
            app(CartService::class)->add(Product::factory()->create(['stock' => 5]));
        }

        foreach (['12345', '887654321', '98765432', 'abcdefghi'] as $invalid) {
            $this->fill(Livewire::test(Checkout::class), ['phone' => $invalid])
                ->call('place')
                ->assertHasErrors('phone')
                ->assertSee('celular de 9 dígitos');
        }
    }

    public function test_the_email_must_be_valid(): void
    {
        $this->cartWith();

        $this->fill(Livewire::test(Checkout::class), ['email' => 'no-es-un-correo'])->call('place')->assertHasErrors(['email' => 'email']);
    }

    #[DataProvider('unservedDistricts')]
    public function test_a_district_the_shop_does_not_serve_is_refused(string $ubigeo): void
    {
        $this->cartWith();

        $this->fill(Livewire::test(Checkout::class), ['ubigeo' => $ubigeo])
            ->call('place')
            ->assertHasErrors('ubigeo')
            ->assertSee('Todavía no enviamos a ese distrito');

        $this->assertSame(0, Order::count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unservedDistricts(): array
    {
        return ['inactive district' => ['150199'], 'unknown code' => ['999999'], 'another department' => ['040101']];
    }

    public function test_a_guest_places_an_order_waiting_for_payment_and_the_stock_does_not_move(): void
    {
        $product = $this->cartWith(2, ['price' => 40, 'sale_price' => 30, 'stock' => 7]);

        $response = $this->fill(Livewire::test(Checkout::class))->call('place');

        $order = Order::with('items')->firstOrFail();
        $response->assertRedirect($order->signedUrl());

        $this->assertNull($order->user_id);
        $this->assertSame('ana@example.com', $order->email);
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertMatchesRegularExpression('/^HB-\d{6}-\d{4}$/', $order->tracking_code);
        // 2 × S/ 30 + S/ 10 shipping, with the IGV contained in the total.
        $this->assertSame(['60.00', '10.00', '70.00', '10.68'], [(string) $order->subtotal, (string) $order->shipping_cost, (string) $order->total, (string) $order->tax]);
        // jsonb does not keep the order of the keys, so only the content is compared.
        $this->assertEquals(
            ['recipient_name' => 'Ana Quispe', 'phone' => '987654321', 'line1' => 'Av. Larco 1234, dpto 502', 'line2' => 'Frente al parque', 'city' => 'Miraflores', 'state' => 'Lima', 'ubigeo' => '150122', 'postal_code' => null, 'country' => 'PE', 'zone' => 'Lima Metropolitana'],
            $order->shipping_address,
        );
        $this->assertCount(1, $order->items);
        $this->assertSame(2, $order->items[0]->quantity);
        $this->assertSame('30.00', (string) $order->items[0]->unit_price);
        $this->assertSame($product->name, $order->items[0]->product_snapshot['name']);
        $this->assertSame(['Pedido creado'], $order->statusHistories->pluck('note')->all());

        // No reservation and no deduction: that happens only when the payment is confirmed.
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertNull($order->stock_deducted_at);
        $this->assertSame(0, app(CartService::class)->count());
    }

    public function test_the_customer_gets_an_email_with_the_items_total_and_a_link_to_the_order(): void
    {
        Notification::fake();
        $this->cartWith(1, ['price' => 40, 'stock' => 7]);

        $this->fill(Livewire::test(Checkout::class))->call('place');

        $order = Order::firstOrFail();
        Notification::assertSentOnDemand(OrderPlaced::class, function (OrderPlaced $n, array $channels, object $notifiable) use ($order) {
            $mail = $n->toMail($notifiable);

            return $notifiable->routes['mail'] === 'ana@example.com'
                && str_contains($mail->subject, $order->tracking_code)
                && str_starts_with($mail->actionUrl, route('orders.show', $order).'?') && str_contains($mail->actionUrl, 'signature=');
        });
    }

    public function test_the_order_keeps_its_copy_when_the_product_changes_afterwards(): void
    {
        $product = $this->cartWith(1, ['name' => 'Nombre original', 'price' => 40]);
        $this->fill(Livewire::test(Checkout::class))->call('place');

        $product->update(['name' => 'Nombre nuevo', 'price' => 99]);
        $item = Order::firstOrFail()->items->first();

        $this->assertSame('Nombre original', $item->product_snapshot['name']);
        $this->assertSame('40.00', (string) $item->unit_price);
    }

    public function test_a_logged_in_customer_gets_the_order_in_their_account_and_the_address_saved_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->cartWith();
        $this->fill(Livewire::test(Checkout::class))->call('place');
        $this->cartWith();
        $this->fill(Livewire::test(Checkout::class))->call('place');

        $this->assertSame(2, $user->orders()->count());
        $this->assertSame(1, $user->addresses()->count());
        $this->assertTrue($user->addresses->first()->is_default);
        $this->assertSame('Miraflores', $user->addresses->first()->city);
    }

    public function test_the_address_is_not_saved_when_the_customer_opts_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->cartWith();

        $this->fill(Livewire::test(Checkout::class))->set('saveAddress', false)->call('place');

        $this->assertSame(1, $user->orders()->count());
        $this->assertSame(0, $user->addresses()->count());
    }

    public function test_if_the_stock_drops_while_the_buyer_fills_the_form_nothing_is_ordered(): void
    {
        $product = $this->cartWith(3);
        $component = $this->fill(Livewire::test(Checkout::class));

        $product->update(['stock' => 1]);
        $component->call('place')->assertRedirect(route('cart'));

        $this->assertSame(0, Order::count());
        $this->assertSame(3, app(CartService::class)->count());
        $this->assertSame('Algunos productos de tu carrito cambiaron. Revisa los avisos antes de continuar.', session('notice'));
    }

    public function test_pressing_confirm_twice_does_not_create_two_orders(): void
    {
        $this->cartWith();
        $component = $this->fill(Livewire::test(Checkout::class));

        $component->call('place');
        $component->call('place')->assertRedirect(route('cart'));

        $this->assertSame(1, Order::count());
    }

    public function test_too_many_orders_in_an_hour_are_refused(): void
    {
        $this->cartWith();
        $key = 'checkout:127.0.0.1';
        foreach (range(1, 10) as $_) {
            RateLimiter::hit($key, 3600);
        }

        $this->fill(Livewire::test(Checkout::class))->call('place')->assertSee('demasiados pedidos');

        $this->assertSame(0, Order::count());
    }

    public function test_the_cart_and_drawer_lead_to_the_checkout_only_when_there_are_no_warnings(): void
    {
        $product = $this->cartWith(3);

        Livewire::test(CartPage::class)->assertSee(route('checkout'), false);
        Livewire::test(CartDrawer::class)->assertSee(route('checkout'), false);

        $product->update(['stock' => 1]);

        Livewire::test(CartPage::class)->assertDontSee(route('checkout'), false)->assertSee('Resuelve los avisos');
        Livewire::test(CartDrawer::class)->assertDontSee(route('checkout'), false);
    }
}
