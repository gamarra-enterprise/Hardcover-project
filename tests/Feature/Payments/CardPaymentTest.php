<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Payments\CardCharge;
use App\Payments\FakeGateway;
use App\Payments\MercadoPagoGateway;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CardPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
    }

    private function pendingOrder(int $stock = 5, float $price = 50, array $attributes = []): Order
    {
        $product = Product::factory()->create(['price' => $price, 'stock' => $stock]);
        $order = Order::factory()->create(array_merge(['email' => 'ana@example.com', 'tracking_code' => 'HB-261008-0042', 'status' => OrderStatus::PENDING, 'shipping_address' => ['recipient_name' => 'Ana Quispe', 'phone' => '987654321', 'line1' => 'Av. Larco 1234', 'city' => 'Miraflores', 'state' => 'Lima']], $attributes));
        $order->items()->create(OrderItem::valuesFor($product, 1));
        $order->update(Order::totalsFor((string) $price, '0'));

        return $order->fresh();
    }

    /** What the browser sends: a token and who pays, never a card. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'token' => 'FAKE-TOK-OK-ABCD1234',
            'payment_method_id' => 'visa',
            'issuer_id' => null,
            'installments' => 1,
            'email' => 'ana@example.com',
            'identification_type' => 'DNI',
            'identification_number' => '12345678',
            'cardholder_name' => 'Ana Quispe Rojas',
        ], $overrides);
    }

    private function useMercadoPago(?string $publicKey = 'APP_USR-public-key'): void
    {
        $this->app->instance(PaymentGateway::class, new MercadoPagoGateway('APP_USR-token', 'webhook-secret', publicKey: $publicKey));
    }

    // ---- the page

    public function test_the_card_page_for_the_stand_in_gateway_shows_plain_fields_without_names_and_the_test_cards(): void
    {
        $order = $this->pendingOrder();

        $html = $this->get($order->cardUrl())
            ->assertOk()
            ->assertSee('Pagar con tarjeta')
            ->assertSee('4111 1111 1111 1111')
            ->assertSee('Modo de prueba')
            ->assertSee('Nombres y apellidos del titular')
            ->assertSee('value="Ana Quispe"', false)
            ->assertSee('value="ana@example.com"', false)
            ->assertSee('data-provider="fake"', false)
            ->assertSee('Pagar S/ 50.00')
            ->getContent();

        // Nothing typed in the card fields can be posted by the form: they carry no "name".
        foreach (['card-number-input', 'card-expiry-input', 'card-cvv-input'] as $id) {
            preg_match('/<input[^>]*id="'.$id.'"[^>]*>/', $html, $m);
            $this->assertNotEmpty($m, $id);
            $this->assertStringNotContainsString(' name=', $m[0]);
        }
        $this->assertStringNotContainsString('sdk.mercadopago.com', $html);
    }

    public function test_the_card_page_for_mercado_pago_uses_the_gateways_own_fields_and_loads_its_script(): void
    {
        $this->useMercadoPago();
        $order = $this->pendingOrder();

        $html = $this->get($order->cardUrl())
            ->assertOk()
            ->assertSee('data-provider="mercadopago"', false)
            ->assertSee('data-public-key="APP_USR-public-key"', false)
            ->assertSee('id="card-number"', false)
            ->assertSee('id="card-expiry"', false)
            ->assertSee('id="card-cvv"', false)
            ->assertSee('Nuestra tienda nunca los ve ni los guarda')
            ->assertDontSee('Modo de prueba: no se cobra nada')
            ->getContent();

        $this->assertStringContainsString('<script src="https://sdk.mercadopago.com/js/v2"></script>', $html);
        // No plain field for the card exists on the page, and the secret token is never printed.
        $this->assertStringNotContainsString('card-number-input', $html);
        $this->assertStringNotContainsString('APP_USR-token', $html);
        $this->assertStringContainsString('/pedido/HB-261008-0042/tarjeta?', $html);
    }

    public function test_without_the_public_key_the_card_page_is_not_offered(): void
    {
        $this->useMercadoPago(publicKey: null);
        $order = $this->pendingOrder();

        $this->get($order->cardUrl())->assertRedirect($order->signedUrl())->assertSessionHas('payment_error');
        $this->get($order->signedUrl())->assertDontSee('Pagar con tarjeta')->assertSee('Otros medios de pago');
    }

    public function test_the_order_page_offers_the_card_first_and_the_gateway_as_the_other_way(): void
    {
        $order = $this->pendingOrder();

        $this->get($order->signedUrl())
            ->assertSee('Pagar con tarjeta')
            ->assertSee(e($order->cardUrl()), false)
            ->assertSee('Otros medios de pago (Mercado Pago)');
    }

    public function test_the_card_page_needs_the_signed_link_or_the_owner_and_a_pending_order(): void
    {
        $owner = User::factory()->create();
        $order = $this->pendingOrder(attributes: ['user_id' => $owner->id]);

        $this->get('/pedido/'.$order->tracking_code.'/tarjeta')->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/pedido/'.$order->tracking_code.'/tarjeta')->assertNotFound();
        $this->actingAs($owner)->get('/pedido/'.$order->tracking_code.'/tarjeta')->assertOk();

        $order->update(['status' => OrderStatus::CONFIRMED]);
        $this->actingAs($owner)->get('/pedido/'.$order->tracking_code.'/tarjeta')->assertRedirect()->assertSessionHas('payment_error');
    }

    // ---- the charge

    public function test_an_approved_card_confirms_the_order_and_takes_the_stock_only_then(): void
    {
        $order = $this->pendingOrder(stock: 5);
        $product = $order->items->first()->product;

        $this->postJson($order->cardPayUrl(), $this->payload())
            ->assertOk()
            ->assertJson(['status' => 'ok', 'redirect' => $order->signedUrl()]);

        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(PaymentStatus::COMPLETED, $order->payments()->sole()->status);
        $this->assertSame('¡Pago confirmado! Gracias por tu compra.', session('payment_notice'));
    }

    public function test_a_rejected_card_says_why_leaves_everything_as_it_was_and_can_be_retried(): void
    {
        $order = $this->pendingOrder(stock: 5);
        $product = $order->items->first()->product;

        $this->postJson($order->cardPayUrl(), $this->payload(['token' => 'FAKE-TOK-NO-ZZZZ']))
            ->assertOk()
            ->assertJson(['status' => 'rejected', 'message' => 'Tu tarjeta no tiene fondos suficientes.']);

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock);

        $this->postJson($order->cardPayUrl(), $this->payload())->assertOk()->assertJson(['status' => 'ok']);
        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);
    }

    public function test_the_last_unit_goes_to_whoever_pays_first_also_with_a_card(): void
    {
        $product = Product::factory()->create(['price' => 40, 'stock' => 1]);
        $make = function (string $code, string $email) use ($product) {
            $order = Order::factory()->create(['email' => $email, 'tracking_code' => $code, 'status' => OrderStatus::PENDING]);
            $order->items()->create(OrderItem::valuesFor($product, 1));
            $order->update(Order::totalsFor('40.00', '0'));

            return $order->fresh();
        };
        $ana = $make('HB-261008-0001', 'ana@example.com');
        $luis = $make('HB-261008-0002', 'luis@example.com');

        $this->postJson($luis->cardPayUrl(), $this->payload(['email' => 'luis@example.com']))->assertOk()->assertJson(['status' => 'ok']);
        // Ana's card form was already open; the unit is gone and nothing is charged.
        $this->postJson($ana->cardPayUrl(), $this->payload())->assertStatus(409)->assertJsonFragment(['message' => 'Algunos productos de tu pedido ya no están disponibles. Puedes cancelar el pedido y volver a elegir.']);

        $this->assertSame(OrderStatus::CONFIRMED, $luis->fresh()->status);
        $this->assertSame(OrderStatus::PENDING, $ana->fresh()->status);
        $this->assertSame(0, $ana->payments()->count());
    }

    public function test_the_same_order_cannot_be_charged_twice(): void
    {
        $order = $this->pendingOrder(stock: 5);

        $this->postJson($order->cardPayUrl(), $this->payload())->assertOk();
        $this->postJson($order->cardPayUrl(), $this->payload())->assertStatus(409);

        $this->assertSame(1, $order->payments()->where('status', PaymentStatus::COMPLETED->value)->count());
    }

    public function test_a_second_click_while_the_first_is_still_running_is_turned_away(): void
    {
        $order = $this->pendingOrder();
        $lock = Cache::lock('pay-order:'.$order->id, 30);
        $this->assertTrue($lock->get());

        $this->postJson($order->cardPayUrl(), $this->payload())
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'Estamos procesando tu pago. Espera un momento.']);

        $this->assertSame(0, $order->payments()->count());
        $lock->release();
    }

    public function test_sending_the_same_charge_twice_to_the_gateway_answers_with_the_first_result(): void
    {
        $order = $this->pendingOrder();
        $card = new CardCharge('FAKE-TOK-OK-1', 'visa', null, 1, 'a@b.pe', 'DNI', '12345678');
        $gateway = new FakeGateway;

        $first = $gateway->chargeCard($order, $card, 'key-1');
        $again = $gateway->chargeCard($order, $card, 'key-1');
        $other = $gateway->chargeCard($order, $card, 'key-2');

        $this->assertSame($first->id, $again->id);
        $this->assertNotSame($first->id, $other->id);
    }

    // ---- what the server refuses to take

    #[DataProvider('cardData')]
    public function test_a_request_that_carries_card_data_is_refused_and_never_logged(array $extra): void
    {
        Log::spy();
        $order = $this->pendingOrder();

        $response = $this->postJson($order->cardPayUrl(), $this->payload($extra))->assertStatus(422);

        $this->assertStringContainsString('No enviamos datos de tarjeta', $response->json('message'));
        $this->assertSame(0, $order->payments()->count());
        // The warning says that it happened, not what was sent.
        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message) => ! str_contains($message, '4111') && ! str_contains($message, '123'));
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function cardData(): array
    {
        return [
            'a card number field' => [['card_number' => '4111 1111 1111 1111']],
            'a cvv field' => [['cvv' => '123']],
            'a security code field' => [['security_code' => '123']],
            'a card number hidden in another field' => [['cardholder_name' => '4111-1111-1111-1111']],
            'a card number without spaces in the email field' => [['email' => '4111111111111111']],
            'a card number nested in another field' => [['payer' => ['card_number' => '4111 1111 1111 1111']]],
            'a cvv nested deeper' => [['extra' => ['card' => ['cvv' => '123']]]],
            'a card number inside a list' => [['notes' => ['4111111111111111']]],
        ];
    }

    #[DataProvider('badInput')]
    public function test_the_input_is_checked(array $overrides, string $field): void
    {
        $order = $this->pendingOrder();

        $this->postJson($order->cardPayUrl(), $this->payload($overrides))->assertStatus(422)->assertJsonValidationErrors($field);

        $this->assertSame(0, $order->payments()->count());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function badInput(): array
    {
        return [
            'no token' => [['token' => ''], 'token'],
            'a token with odd characters' => [['token' => 'abc def;drop'], 'token'],
            'no payment method' => [['payment_method_id' => ''], 'payment_method_id'],
            'zero installments' => [['installments' => 0], 'installments'],
            'a bad email' => [['email' => 'no-es-un-correo'], 'email'],
            'a document type that does not exist' => [['identification_type' => 'RUC'], 'identification_type'],
            'a dni that is too short' => [['identification_number' => '1234567'], 'identification_number'],
            'a dni with letters' => [['identification_number' => '1234567a'], 'identification_number'],
            'a foreigner card that is too short' => [['identification_type' => 'CE', 'identification_number' => '12345'], 'identification_number'],
            'an issuer that is not a number' => [['issuer_id' => 'abc'], 'issuer_id'],
        ];
    }

    public function test_a_foreigner_card_document_is_accepted(): void
    {
        $order = $this->pendingOrder();

        $this->postJson($order->cardPayUrl(), $this->payload(['identification_type' => 'CE', 'identification_number' => '001234567']))->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_only_whoever_holds_the_signed_address_or_the_owner_can_charge(): void
    {
        $order = $this->pendingOrder();

        $this->postJson('/pedido/'.$order->tracking_code.'/tarjeta', $this->payload())->assertNotFound();
        $this->postJson($order->cardPayUrl().'x', $this->payload())->assertNotFound();
        $this->actingAs(User::factory()->create())->postJson('/pedido/'.$order->tracking_code.'/tarjeta', $this->payload())->assertNotFound();
        $this->assertSame(0, Payment::count());
    }

    public function test_too_many_tries_are_stopped_to_slow_down_card_testing(): void
    {
        $order = $this->pendingOrder();

        foreach (range(1, 5) as $_) {
            $this->postJson($order->cardPayUrl(), $this->payload(['token' => 'FAKE-TOK-NO-X']))->assertOk();
        }

        $this->postJson($order->cardPayUrl(), $this->payload())->assertStatus(429);
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        RateLimiter::clear('card:'.$order->id.':127.0.0.1');
    }

    // ---- with Mercado Pago

    public function test_the_charge_to_mercado_pago_carries_the_token_and_never_a_card(): void
    {
        $this->useMercadoPago();
        $order = $this->pendingOrder(price: 50);
        Http::fake(['api.mercadopago.com/v1/payments' => Http::response([
            'id' => 555, 'status' => 'approved', 'status_detail' => 'accredited', 'transaction_amount' => 50.0, 'currency_id' => 'PEN',
            'external_reference' => 'HB-261008-0042', 'card' => ['first_six_digits' => '411111', 'last_four_digits' => '1111'],
        ], 201)]);

        $this->postJson($order->cardPayUrl(), $this->payload(['token' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90', 'payment_method_id' => 'visa', 'issuer_id' => '25']))
            ->assertOk()->assertJson(['status' => 'ok']);

        Http::assertSent(function (HttpRequest $request) {
            $data = $request->data();

            return $request->url() === 'https://api.mercadopago.com/v1/payments'
                && $request->hasHeader('Authorization', 'Bearer APP_USR-token')
                && ! empty($request->header('X-Idempotency-Key')[0])
                && $data['transaction_amount'] === 50.0
                && $data['token'] === 'a1b2c3d4e5f60718293a4b5c6d7e8f90'
                && $data['payment_method_id'] === 'visa'
                && $data['issuer_id'] === 25
                && $data['installments'] === 1
                && $data['binary_mode'] === true
                && $data['external_reference'] === 'HB-261008-0042'
                && $data['payer'] === ['email' => 'ana@example.com', 'identification' => ['type' => 'DNI', 'number' => '12345678']]
                && $data['notification_url'] === route('payments.webhook')
                && ! str_contains(json_encode($data), '4111');
        });

        $payment = $order->payments()->sole();
        $this->assertSame(['555', PaymentStatus::COMPLETED], [$payment->external_reference, $payment->status]);
        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
    }

    public function test_a_rejection_of_mercado_pago_is_explained_in_spanish(): void
    {
        $this->useMercadoPago();
        $order = $this->pendingOrder();

        $reasons = [
            'cc_rejected_bad_filled_security_code' => 'Revisa el código de seguridad (CVV).',
            'cc_rejected_bad_filled_date' => 'Revisa la fecha de vencimiento.',
            'cc_rejected_call_for_authorize' => 'Tu banco necesita que autorices este pago. Llámalo e inténtalo de nuevo.',
            'cc_rejected_other_reason' => 'Tu banco rechazó el pago. Prueba con otra tarjeta o medio de pago.',
        ];

        // One answer after another, in the order of the reasons.
        $sequence = Http::sequence();
        foreach (array_keys($reasons) as $i => $detail) {
            $sequence->push(['id' => 9000 + $i, 'status' => 'rejected', 'status_detail' => $detail, 'transaction_amount' => 50.0, 'currency_id' => 'PEN', 'external_reference' => 'HB-261008-0042'], 201);
        }
        Http::fake(['api.mercadopago.com/v1/payments' => $sequence]);

        foreach ($reasons as $message) {
            $this->postJson($order->cardPayUrl(), $this->payload())->assertOk()->assertJson(['status' => 'rejected', 'message' => $message]);
        }

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_the_personal_data_in_the_gateways_answer_is_not_kept(): void
    {
        $this->useMercadoPago();
        $order = $this->pendingOrder(price: 50);
        Http::fake(['api.mercadopago.com/v1/payments' => Http::response([
            'id' => 555, 'status' => 'approved', 'status_detail' => 'accredited', 'transaction_amount' => 50.0, 'currency_id' => 'PEN', 'external_reference' => 'HB-261008-0042',
            'payer' => ['id' => '77', 'email' => 'ana@example.com', 'identification' => ['type' => 'DNI', 'number' => '12345678'], 'phone' => ['number' => '987654321'], 'first_name' => 'Ana', 'last_name' => 'Quispe'],
            'card' => ['first_six_digits' => '411111', 'last_four_digits' => '1111', 'cardholder' => ['name' => 'ANA QUISPE ROJAS', 'identification' => ['number' => '12345678']]],
            'additional_info' => ['payer' => ['first_name' => 'Ana'], 'items' => []],
        ], 201)]);

        $this->postJson($order->cardPayUrl(), $this->payload())->assertOk();

        $kept = json_encode($order->payments()->sole()->payload);
        foreach (['12345678', '987654321', 'ANA QUISPE', 'Quispe', '"first_name"'] as $personal) {
            $this->assertStringNotContainsString($personal, $kept, $personal);
        }
        // What audits the payment is still there.
        $this->assertStringContainsString('"last_four_digits":"1111"', $kept);
        $this->assertStringContainsString('"status":"approved"', $kept);
    }

    public function test_when_mercado_pago_does_not_answer_the_customer_is_told_not_to_pay_again(): void
    {
        $this->useMercadoPago();
        $order = $this->pendingOrder();
        Http::fake(['*' => Http::response([], 500)]);

        $this->postJson($order->cardPayUrl(), $this->payload())
            ->assertStatus(502)
            ->assertJsonFragment(['message' => 'No pudimos confirmar tu pago. Si se te cobró, lo confirmaremos y te avisaremos por correo; no vuelvas a pagar todavía.']);

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_if_the_answer_was_lost_the_notice_that_follows_settles_the_payment_on_the_same_attempt(): void
    {
        $this->useMercadoPago();
        $order = $this->pendingOrder(price: 50);
        Http::fake(['api.mercadopago.com/v1/payments' => Http::response([], 500)]);
        $this->postJson($order->cardPayUrl(), $this->payload())->assertStatus(502);

        // The charge had gone through; the notification arrives afterwards.
        Http::fake(['api.mercadopago.com/v1/payments/888' => Http::response(['id' => 888, 'status' => 'approved', 'transaction_amount' => 50.0, 'currency_id' => 'PEN', 'external_reference' => 'HB-261008-0042'])]);
        $ts = '1704908010';
        $v1 = hash_hmac('sha256', "id:888;request-id:req-1;ts:{$ts};", 'webhook-secret');
        $this->postJson('/webhooks/mercadopago?data.id=888&type=payment', ['type' => 'payment', 'data' => ['id' => '888']], ['x-signature' => "ts={$ts},v1={$v1}", 'x-request-id' => 'req-1'])->assertOk();

        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_without_card_support_the_charge_is_refused(): void
    {
        $this->useMercadoPago(publicKey: null);
        $order = $this->pendingOrder();

        $this->postJson($order->cardPayUrl(), $this->payload())->assertStatus(409)->assertJsonFragment(['message' => 'El pago con tarjeta no está disponible por ahora.']);
    }
}
