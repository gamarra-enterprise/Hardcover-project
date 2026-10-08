<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Checkout\Checkout;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingDistrict;
use App\Models\ShippingZone;
use App\Models\User;
use App\Payments\FakeGateway;
use App\Payments\MercadoPagoGateway;
use App\Payments\PaymentGateway;
use App\Services\CartService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        // Signed addresses carry an expiry time; with the clock frozen two of them made a moment apart are equal.
        $this->freezeTime();
    }

    private function pendingOrder(Product $product, int $quantity = 1, array $attributes = []): Order
    {
        $order = Order::factory()->create(array_merge(['email' => 'ana@example.com', 'tracking_code' => 'HB-261008-0042', 'status' => OrderStatus::PENDING], $attributes));
        $order->items()->create(OrderItem::valuesFor($product, $quantity));
        $order->update(Order::totalsFor(bcmul($product->currentPrice(), (string) $quantity, 2), '0'));

        return $order->fresh();
    }

    private function useMercadoPago(): void
    {
        $this->app->instance(PaymentGateway::class, new MercadoPagoGateway('APP_USR-token', self::SECRET));
    }

    /** @return array{string, array<string, string>} the address and the headers of a signed notice */
    private function notice(string $paymentId, string $secret = self::SECRET, string $type = 'payment'): array
    {
        $ts = '1704908010';
        $v1 = hash_hmac('sha256', "id:{$paymentId};request-id:req-1;ts:{$ts};", $secret);

        return ["/webhooks/mercadopago?data.id={$paymentId}&type={$type}", ['x-signature' => "ts={$ts},v1={$v1}", 'x-request-id' => 'req-1']];
    }

    private function mercadoPagoPayment(string $status, string $amount = '50.00', string $code = 'HB-261008-0042'): void
    {
        Http::fake(['api.mercadopago.com/v1/payments/777' => Http::response(['id' => 777, 'status' => $status, 'transaction_amount' => (float) $amount, 'currency_id' => 'PEN', 'external_reference' => $code])]);
    }

    public function test_a_signed_notice_about_an_approved_payment_confirms_the_order(): void
    {
        $this->useMercadoPago();
        $product = Product::factory()->create(['price' => 50, 'stock' => 4]);
        $order = $this->pendingOrder($product);
        $this->mercadoPagoPayment('approved');

        [$uri, $headers] = $this->notice('777');
        $this->postJson($uri, ['type' => 'payment', 'data' => ['id' => '777']], $headers)->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertSame(3, $product->fresh()->stock);
        $payment = $order->payments()->sole();
        $this->assertSame(['mercadopago', '777', PaymentStatus::COMPLETED], [$payment->provider, $payment->external_reference, $payment->status]);
    }

    public function test_the_same_notice_arriving_again_does_nothing_more(): void
    {
        $this->useMercadoPago();
        $product = Product::factory()->create(['price' => 50, 'stock' => 4]);
        $order = $this->pendingOrder($product);
        $this->mercadoPagoPayment('approved');
        [$uri, $headers] = $this->notice('777');

        foreach (range(1, 3) as $_) {
            $this->postJson($uri, ['type' => 'payment', 'data' => ['id' => '777']], $headers)->assertOk();
        }

        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_a_notice_that_is_not_signed_is_refused_and_nothing_changes(): void
    {
        $this->useMercadoPago();
        $product = Product::factory()->create(['price' => 50, 'stock' => 4]);
        $order = $this->pendingOrder($product);
        $this->mercadoPagoPayment('approved');

        [$uri, $headers] = $this->notice('777', secret: 'not-the-secret');
        $this->postJson($uri, ['type' => 'payment', 'data' => ['id' => '777']], $headers)->assertUnauthorized();
        $this->postJson($uri, ['type' => 'payment', 'data' => ['id' => '777']])->assertUnauthorized();

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);
        Http::assertNothingSent();
    }

    public function test_a_notice_cannot_confirm_what_the_gateway_does_not_say_is_approved(): void
    {
        $this->useMercadoPago();
        $product = Product::factory()->create(['price' => 50, 'stock' => 4]);
        $order = $this->pendingOrder($product);
        // The notice is perfectly signed, but the payment was rejected.
        $this->mercadoPagoPayment('rejected');

        [$uri, $headers] = $this->notice('777');
        $this->postJson($uri, ['type' => 'payment', 'data' => ['id' => '777']], $headers)->assertOk();

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(PaymentStatus::FAILED, $order->payments()->sole()->status);
    }

    public function test_other_kinds_of_notice_and_payments_that_are_not_ours_get_a_quiet_ok(): void
    {
        $this->useMercadoPago();
        Http::fake(['*' => Http::response(['message' => 'not found'], 404)]);

        [$uri, $headers] = $this->notice('123456', type: 'merchant_order');
        $this->postJson($uri, ['type' => 'merchant_order', 'data' => ['id' => '123456']], $headers)->assertOk();

        [$uri, $headers] = $this->notice('123456');
        $this->postJson($uri, ['type' => 'payment', 'data' => ['id' => '123456']], $headers)->assertOk();
    }

    public function test_when_the_gateway_cannot_be_asked_the_notice_is_answered_with_an_error_so_it_comes_again(): void
    {
        $this->useMercadoPago();
        Http::fake(['*' => Http::response([], 500)]);

        [$uri, $headers] = $this->notice('777');

        $this->postJson($uri, ['type' => 'payment', 'data' => ['id' => '777']], $headers)->assertStatus(503);
    }

    public function test_the_notification_address_exists_only_for_mercado_pago(): void
    {
        // With the stand-in gateway the endpoint answers as if it did not exist.
        $this->postJson('/webhooks/mercadopago', ['type' => 'payment'])->assertNotFound();
    }

    public function test_the_whole_mercado_pago_flow_from_the_pay_button_to_the_confirmation(): void
    {
        $this->useMercadoPago();
        $product = Product::factory()->create(['price' => 50, 'stock' => 4]);
        $order = $this->pendingOrder($product);
        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response(['id' => 'PREF-9', 'init_point' => 'https://www.mercadopago.com.pe/checkout/v1/redirect?pref_id=PREF-9'], 201),
            'api.mercadopago.com/v1/payments/777' => Http::response(['id' => 777, 'status' => 'approved', 'transaction_amount' => 50.0, 'currency_id' => 'PEN', 'external_reference' => 'HB-261008-0042']),
        ]);

        $this->post($order->payUrl())->assertRedirect('https://www.mercadopago.com.pe/checkout/v1/redirect?pref_id=PREF-9');
        $this->assertSame('pref:PREF-9', $order->payments()->sole()->external_reference);

        [$uri, $headers] = $this->notice('777');
        $this->postJson($uri, ['type' => 'payment', 'data' => ['id' => '777']], $headers)->assertOk();

        // The attempt opened at checkout became the confirmed payment: one row, not two.
        $payment = $order->payments()->sole();
        $this->assertSame(['777', PaymentStatus::COMPLETED], [$payment->external_reference, $payment->status]);
        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
    }

    public function test_only_whoever_holds_the_signed_button_can_start_a_payment(): void
    {
        $order = $this->pendingOrder(Product::factory()->create());

        $this->post('/pedido/'.$order->tracking_code.'/pagar')->assertNotFound();
        $this->post($order->payUrl().'x')->assertNotFound();
        $this->actingAs(User::factory()->create())->post('/pedido/'.$order->tracking_code.'/pagar')->assertNotFound();
        $this->assertSame(0, Payment::count());
    }

    public function test_the_owner_can_pay_without_the_signature(): void
    {
        $owner = User::factory()->create();
        $order = $this->pendingOrder(Product::factory()->create(), 1, ['user_id' => $owner->id]);

        $this->actingAs($owner)->post('/pedido/'.$order->tracking_code.'/pagar')->assertRedirect();
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_a_failing_gateway_sends_the_customer_back_with_a_friendly_message(): void
    {
        $this->useMercadoPago();
        Http::fake(['*' => Http::response([], 500)]);
        $order = $this->pendingOrder(Product::factory()->create());

        $this->post($order->payUrl())
            ->assertRedirect($order->signedUrl())
            ->assertSessionHas('payment_error', 'No pudimos iniciar el pago en este momento. Inténtalo de nuevo en unos minutos.');
    }

    public function test_paying_something_that_is_gone_or_already_paid_sends_the_customer_back_with_the_reason(): void
    {
        $product = Product::factory()->create(['stock' => 1]);
        $order = $this->pendingOrder($product, 1);
        $product->update(['stock' => 0]);

        $this->post($order->payUrl())->assertRedirect($order->signedUrl())->assertSessionHas('payment_error');
        $this->assertSame(0, Payment::count());
    }

    public function test_the_order_page_offers_to_pay_only_while_it_is_pending_and_nothing_is_being_verified(): void
    {
        $order = $this->pendingOrder(Product::factory()->create(['price' => 50]));

        $this->get($order->signedUrl())->assertSee('Pagar S/ 50.00')->assertSee('Modo de prueba');

        Payment::factory()->for($order)->create(['status' => PaymentStatus::PROCESSING]);
        $this->get($order->signedUrl())->assertDontSee('Pagar S/ 50.00')->assertSee('Estamos verificando tu pago');

        $order->update(['status' => OrderStatus::CONFIRMED]);
        $this->get($order->signedUrl())->assertDontSee('Pagar S/ 50.00')->assertDontSee('pendiente de pago');
    }

    public function test_the_return_address_checks_its_signature_but_tolerates_what_the_gateway_adds(): void
    {
        $order = $this->pendingOrder(Product::factory()->create());
        $url = URL::temporarySignedRoute('payments.return', now()->addHour(), ['order' => $order->tracking_code]);

        $this->get($url.'&collection_id=1&collection_status=approved&payment_id=1&status=approved&external_reference=x&payment_type=credit_card&merchant_order_id=2&preference_id=3&site_id=MPE&processing_mode=aggregator&merchant_account_id=null')
            ->assertRedirect($order->signedUrl());

        $this->get('/pago/retorno/'.$order->tracking_code)->assertNotFound();
        $this->get($url.'&unexpected=1')->assertNotFound();
        $this->get(str_replace($order->tracking_code, 'HB-000000-0000', $url))->assertNotFound();
    }

    public function test_coming_back_from_the_gateway_looks_the_payment_up_and_tells_the_truth(): void
    {
        $product = Product::factory()->create(['price' => 50, 'stock' => 4]);
        $order = $this->pendingOrder($product);
        $checkout = app(PaymentService::class)->start($order);
        preg_match('#/pago/prueba/(FAKE-[A-Z0-9]+)#', $checkout->url, $m);
        app(PaymentGateway::class)->decide($m[1], true);

        // The notice never arrived; the customer's return is enough to confirm.
        $url = URL::temporarySignedRoute('payments.return', now()->addHour(), ['order' => $order->tracking_code]);
        $this->get($url.'&payment_id='.$m[1])
            ->assertRedirect($order->signedUrl())
            ->assertSessionHas('payment_notice', '¡Pago confirmado! Gracias por tu compra.');

        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
    }

    public function test_the_message_after_returning_comes_from_what_the_shop_recorded_not_from_the_url(): void
    {
        $order = $this->pendingOrder(Product::factory()->create());
        $url = URL::temporarySignedRoute('payments.return', now()->addHour(), ['order' => $order->tracking_code]);

        // The browser claims "approved", but nothing was paid.
        $this->get($url.'&status=approved&collection_status=approved')
            ->assertSessionHas('payment_notice', 'Todavía no recibimos la confirmación de tu pago. Si ya pagaste, te avisaremos por correo.');
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_coming_back_cannot_be_used_to_process_the_payment_of_another_order(): void
    {
        $mine = $this->pendingOrder(Product::factory()->create(['price' => 50, 'stock' => 4]));
        $theirs = $this->pendingOrder(Product::factory()->create(['price' => 50, 'stock' => 4]), 1, ['tracking_code' => 'HB-261008-0099']);
        $checkout = app(PaymentService::class)->start($theirs);
        preg_match('#/pago/prueba/(FAKE-[A-Z0-9]+)#', $checkout->url, $m);
        app(PaymentGateway::class)->decide($m[1], true);

        $url = URL::temporarySignedRoute('payments.return', now()->addHour(), ['order' => $mine->tracking_code]);
        $this->get($url.'&payment_id='.$m[1]);

        $this->assertSame(OrderStatus::PENDING, $theirs->fresh()->status);
        $this->assertSame(OrderStatus::PENDING, $mine->fresh()->status);
    }

    public function test_from_the_cart_to_a_confirmed_order_with_the_stand_in_gateway(): void
    {
        config(['shop.free_shipping_from' => 500]);
        $zone = ShippingZone::factory()->create(['name' => 'Lima Metropolitana', 'min_cost' => 10]);
        ShippingDistrict::factory()->for($zone, 'zone')->create(['name' => 'Miraflores', 'ubigeo' => '150122', 'cost' => 10]);
        $product = Product::factory()->book()->create(['price' => 40, 'stock' => 3]);
        app(CartService::class)->add($product, 2);

        Livewire::test(Checkout::class)
            ->set('name', 'Ana Quispe')->set('email', 'ana@example.com')->set('phone', '987654321')
            ->set('ubigeo', '150122')->set('line1', 'Av. Larco 1234')
            ->call('place');
        $order = Order::firstOrFail();
        $this->assertSame(3, $product->fresh()->stock);

        // The order page, the pay button, the gateway's page and the way back.
        $this->get($order->signedUrl())->assertSee('Pagar S/ 90.00');
        $response = $this->post($order->payUrl());
        $this->assertTrue($response->isRedirect(), 'pay button answered '.$response->status());
        $redirect = $response->headers->get('Location');
        $page = $this->get($redirect);
        $this->assertSame(200, $page->status(), 'fake page answered '.$page->status().' for '.$redirect);
        $page->assertSee('Pasarela de prueba')->assertSee('S/ 90.00');

        preg_match('#/pago/prueba/(FAKE-[A-Z0-9]+)#', $redirect, $m);
        $decideUrl = URL::temporarySignedRoute('payments.fake.decide', now()->addHour(), ['paymentId' => $m[1]]);
        $decided = $this->post($decideUrl, ['result' => 'approve']);
        $this->assertTrue($decided->isRedirect(), 'decide answered '.$decided->status());
        $back = $decided->headers->get('Location');
        $returned = $this->get($back);
        $this->assertTrue($returned->isRedirect(), 'return answered '.$returned->status().' for '.$back);
        $returned->assertRedirect($order->signedUrl());

        $this->get($order->signedUrl())->assertOk()->assertSee('Confirmado')->assertSee('¡Pago confirmado! Gracias por tu compra.');
        $this->assertSame(1, $product->fresh()->stock);
        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
    }

    public function test_the_stand_in_page_needs_its_signature_and_the_stand_in_gateway(): void
    {
        $order = $this->pendingOrder(Product::factory()->create());
        $checkout = app(PaymentService::class)->start($order);
        preg_match('#/pago/prueba/(FAKE-[A-Z0-9]+)#', $checkout->url, $m);

        $this->get('/pago/prueba/'.$m[1])->assertNotFound();
        $this->get($checkout->url)->assertOk();

        $this->useMercadoPago();
        $this->get($checkout->url)->assertNotFound();
    }

    public function test_the_stand_in_gateway_is_refused_in_production(): void
    {
        $this->app->forgetInstance(PaymentGateway::class);
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not allowed in production');
        app(PaymentGateway::class);
    }

    public function test_an_unknown_gateway_name_is_refused(): void
    {
        $this->app->forgetInstance(PaymentGateway::class);
        config(['shop.payment_gateway' => 'paypal']);

        $this->expectException(\RuntimeException::class);
        app(PaymentGateway::class);
    }

    public function test_the_gateway_is_chosen_by_configuration(): void
    {
        $this->app->forgetInstance(PaymentGateway::class);
        config(['shop.payment_gateway' => 'mercadopago']);
        $this->assertInstanceOf(MercadoPagoGateway::class, app(PaymentGateway::class));

        $this->app->forgetInstance(PaymentGateway::class);
        config(['shop.payment_gateway' => 'fake']);
        $this->assertInstanceOf(FakeGateway::class, app(PaymentGateway::class));
    }
}
