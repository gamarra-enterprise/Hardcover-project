<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Livewire\Orders\TrackOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class OrderPageTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attributes = []): Order
    {
        $order = Order::factory()->create(array_merge(['email' => 'ana@example.com', 'tracking_code' => 'HB-261008-0042'], $attributes));
        $order->items()->create(OrderItem::valuesFor(Product::factory()->book()->create(['name' => 'Crónicas marcianas', 'price' => 66]), 2));
        $order->update(['subtotal' => 132, 'shipping_cost' => 10, 'total' => 142, 'tax' => 21.66]);

        return $order;
    }

    public function test_the_signed_link_opens_the_order_for_a_guest(): void
    {
        $order = $this->order();

        $this->get($order->signedUrl())
            ->assertOk()
            ->assertSee('HB-261008-0042')
            ->assertSee('Pendiente de pago')
            ->assertSee('2 × Crónicas marcianas', false)
            ->assertSee('S/ 142.00')
            ->assertSee('Pedido creado')
            ->assertSee('pago en línea estará disponible');
    }

    public function test_the_order_page_shows_the_delivery_address(): void
    {
        $order = $this->order(['shipping_address' => ['recipient_name' => 'Ana Quispe', 'phone' => '987654321', 'line1' => 'Av. Larco 1234', 'line2' => null, 'city' => 'Miraflores', 'state' => 'Lima']]);

        $this->get($order->signedUrl())->assertSee('Ana Quispe')->assertSee('Av. Larco 1234')->assertSee('Miraflores, Lima');
    }

    public function test_the_bare_code_is_not_enough_to_open_an_order(): void
    {
        $order = $this->order();

        $this->get('/pedido/'.$order->tracking_code)->assertNotFound();
        $this->get('/pedido/HB-000000-0000')->assertNotFound();
    }

    public function test_a_tampered_or_expired_link_does_not_open_it(): void
    {
        $order = $this->order();
        $url = $order->signedUrl();

        $this->get($url.'x')->assertNotFound();
        $this->get(str_replace('HB-261008-0042', 'HB-261008-0043', $url))->assertNotFound();
        $this->get(URL::temporarySignedRoute('orders.show', now()->subMinute(), ['order' => $order->tracking_code]))->assertNotFound();
    }

    public function test_the_owner_opens_it_without_a_signature_but_other_customers_cannot(): void
    {
        $owner = User::factory()->create();
        $order = $this->order(['user_id' => $owner->id]);

        $this->actingAs($owner)->get('/pedido/'.$order->tracking_code)->assertOk();
        $this->actingAs(User::factory()->create())->get('/pedido/'.$order->tracking_code)->assertNotFound();
    }

    public function test_staff_can_open_any_order(): void
    {
        $order = $this->order();

        $this->actingAs(User::factory()->admin()->create())->get('/pedido/'.$order->tracking_code)->assertOk();
        $this->actingAs(User::factory()->superAdmin()->create())->get('/pedido/'.$order->tracking_code)->assertOk();
    }

    public function test_a_cancelled_order_shows_the_refund_and_no_timeline(): void
    {
        $order = $this->order(['status' => OrderStatus::CANCELLED, 'refund_amount' => 142]);

        $this->get($order->signedUrl())->assertSee('Cancelado')->assertSee('Te devolveremos S/ 142.00')->assertDontSee('aria-label="Estado del pedido"', false);
    }

    public function test_the_timeline_marks_the_stages_reached(): void
    {
        $order = $this->order(['status' => OrderStatus::SHIPPED]);

        $html = $this->get($order->signedUrl())->assertSee('Enviado')->getContent();

        $this->assertSame(4, substr_count($html, 'class="on"'));
        $this->assertStringContainsString('aria-current="step"', $html);
    }

    public function test_tracking_with_the_right_code_and_email_leads_to_the_order(): void
    {
        $order = $this->order();

        $component = Livewire::test(TrackOrder::class)->set('code', ' hb-261008-0042 ')->set('email', 'ANA@Example.com')->call('search');

        $component->assertHasNoErrors();
        $this->assertStringContainsString('/pedido/HB-261008-0042?', $component->effects['redirect']);
        $this->get($component->effects['redirect'])->assertOk();
    }

    public function test_tracking_also_accepts_the_email_of_the_account(): void
    {
        $user = User::factory()->create(['email' => 'cuenta@example.com']);
        $this->order(['user_id' => $user->id, 'email' => null]);

        Livewire::test(TrackOrder::class)->set('code', 'HB-261008-0042')->set('email', 'cuenta@example.com')->call('search')->assertHasNoErrors()->assertRedirect();
    }

    public function test_a_wrong_email_or_a_wrong_code_get_the_same_answer(): void
    {
        $this->order();

        $wrongEmail = Livewire::test(TrackOrder::class)->set('code', 'HB-261008-0042')->set('email', 'otra@example.com')->call('search');
        $wrongCode = Livewire::test(TrackOrder::class)->set('code', 'HB-261008-9999')->set('email', 'ana@example.com')->call('search');

        foreach ([$wrongEmail, $wrongCode] as $component) {
            $component->assertHasErrors('code')->assertSee('No encontramos un pedido con esos datos')->assertNoRedirect();
        }
    }

    public function test_tracking_is_limited_to_a_few_tries_a_minute(): void
    {
        foreach (range(1, 6) as $_) {
            RateLimiter::hit('track-order:127.0.0.1', 60);
        }
        $this->order();

        Livewire::test(TrackOrder::class)->set('code', 'HB-261008-0042')->set('email', 'ana@example.com')->call('search')
            ->assertSee('Demasiados intentos')->assertNoRedirect();
    }

    public function test_the_footer_links_to_the_tracking_page(): void
    {
        $this->get('/')->assertSee(route('orders.track'), false);
        $this->get('/seguimiento')->assertOk()->assertSee('Sigue tu pedido');
    }

    public function test_dates_are_shown_in_lima_time_not_utc(): void
    {
        Carbon::setTestNow('2026-10-08 17:19:00 UTC');
        $order = $this->order();

        $this->get($order->signedUrl())->assertSee('8 de octubre de 2026, 12:19')->assertSee('08/10/2026 12:19');

        Carbon::setTestNow();
    }
}
