<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Payments\InvalidWebhook;
use App\Payments\MercadoPagoGateway;
use App\Payments\PaymentUnavailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The adapter is checked against simulated answers shaped like the ones the public API documents. It
 * has not been run against the real service.
 */
class MercadoPagoGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function gateway(?string $token = 'APP_USR-secret-token', ?string $secret = 'webhook-secret'): MercadoPagoGateway
    {
        return new MercadoPagoGateway($token, $secret);
    }

    private function order(float $price = 40, int $quantity = 2, string $shipping = '10.00'): Order
    {
        $product = Product::factory()->create(['name' => 'Crónicas marcianas', 'sku' => 'HB-9788412537109', 'price' => $price]);
        $order = Order::factory()->create(['email' => 'ana@example.com', 'tracking_code' => 'HB-261008-0042', 'shipping_address' => ['recipient_name' => 'Ana Quispe', 'phone' => '987654321']]);
        $order->items()->create(OrderItem::valuesFor($product, $quantity));
        $order->update(Order::totalsFor(bcmul((string) $price, (string) $quantity, 2), $shipping));

        return $order->fresh()->load('items');
    }

    public function test_it_opens_a_checkout_with_the_items_the_shipping_and_the_order_code(): void
    {
        Http::fake(['api.mercadopago.com/*' => Http::response(['id' => 'PREF-1', 'init_point' => 'https://www.mercadopago.com.pe/checkout/v1/redirect?pref_id=PREF-1', 'sandbox_init_point' => 'https://sandbox.example/PREF-1'], 201)]);
        $order = $this->order();

        $checkout = $this->gateway()->createCheckout($order, Payment::factory()->for($order)->create());

        $this->assertSame('https://www.mercadopago.com.pe/checkout/v1/redirect?pref_id=PREF-1', $checkout->url);
        $this->assertSame('PREF-1', $checkout->reference);

        Http::assertSent(function (HttpRequest $request) {
            $data = $request->data();

            return $request->url() === 'https://api.mercadopago.com/checkout/preferences'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer APP_USR-secret-token')
                && $data['external_reference'] === 'HB-261008-0042'
                && $data['items'][0] === ['id' => 'HB-9788412537109', 'title' => 'Crónicas marcianas', 'quantity' => 2, 'unit_price' => 40.0, 'currency_id' => 'PEN']
                && $data['items'][1] === ['id' => 'envio', 'title' => 'Envío', 'quantity' => 1, 'unit_price' => 10.0, 'currency_id' => 'PEN']
                && $data['payer'] === ['email' => 'ana@example.com', 'name' => 'Ana Quispe']
                && $data['notification_url'] === route('payments.webhook')
                && $data['auto_return'] === 'approved'
                && str_contains($data['back_urls']['success'], '/pago/retorno/HB-261008-0042?')
                && $data['back_urls']['failure'] === $data['back_urls']['success'];
        });
    }

    public function test_the_items_add_up_to_the_order_total(): void
    {
        Http::fake(['*' => Http::response(['id' => 'P', 'init_point' => 'https://x'], 201)]);
        $order = $this->order(price: 33.33, quantity: 3, shipping: '10.00');

        $this->gateway()->createCheckout($order, Payment::factory()->for($order)->create());

        Http::assertSent(function (HttpRequest $request) use ($order) {
            $sum = collect($request->data()['items'])->sum(fn ($i) => $i['unit_price'] * $i['quantity']);

            return abs($sum - (float) $order->total) < 0.001;
        });
    }

    public function test_if_the_lines_do_not_match_the_total_the_total_is_charged_as_one_item(): void
    {
        Http::fake(['*' => Http::response(['id' => 'P', 'init_point' => 'https://x'], 201)]);
        $order = $this->order();
        $order->update(['total' => 999]);

        $this->gateway()->createCheckout($order->fresh()->load('items'), Payment::factory()->for($order)->create());

        Http::assertSent(fn (HttpRequest $request) => count($request->data()['items']) === 1
            && $request->data()['items'][0]['unit_price'] === 999.0
            && $request->data()['items'][0]['title'] === 'Pedido HB-261008-0042');
    }

    public function test_test_credentials_use_the_sandbox_address(): void
    {
        Http::fake(['*' => Http::response(['id' => 'P', 'init_point' => 'https://real', 'sandbox_init_point' => 'https://sandbox'], 201)]);
        $order = $this->order();

        $this->assertSame('https://sandbox', $this->gateway('TEST-123')->createCheckout($order, Payment::factory()->for($order)->create())->url);
    }

    public function test_without_a_token_nothing_is_sent(): void
    {
        Http::fake();
        $order = $this->order();

        try {
            $this->gateway(token: null)->createCheckout($order, Payment::factory()->for($order)->create());
            $this->fail('It should have refused.');
        } catch (PaymentUnavailable $e) {
            $this->assertStringContainsString('not configured', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_a_gateway_error_keeps_only_the_status_and_never_the_token(): void
    {
        Http::fake(['*' => Http::response(['message' => 'bad request with APP_USR-secret-token inside', 'cause' => []], 400)]);
        $order = $this->order();

        try {
            $this->gateway()->createCheckout($order, Payment::factory()->for($order)->create());
            $this->fail('It should have failed.');
        } catch (PaymentUnavailable $e) {
            $this->assertSame('Mercado Pago answered with status 400.', $e->getMessage());
            $this->assertStringNotContainsString('APP_USR', $e->getMessage());
        }
    }

    public function test_an_unreachable_gateway_is_reported_as_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $order = $this->order();

        $this->expectException(PaymentUnavailable::class);
        $this->expectExceptionMessage('could not be reached');
        $this->gateway()->createCheckout($order, Payment::factory()->for($order)->create());
    }

    public function test_an_answer_without_an_address_is_refused(): void
    {
        Http::fake(['*' => Http::response(['id' => 'P'], 201)]);
        $order = $this->order();

        $this->expectException(PaymentUnavailable::class);
        $this->gateway()->createCheckout($order, Payment::factory()->for($order)->create());
    }

    #[DataProvider('statuses')]
    public function test_the_statuses_of_the_gateway_become_ours(string $remote, PaymentStatus $expected): void
    {
        Http::fake(['*' => Http::response(['id' => 1234567890, 'status' => $remote, 'transaction_amount' => 142.5, 'currency_id' => 'PEN', 'external_reference' => 'HB-261008-0042'])]);

        $payment = $this->gateway()->fetchPayment('1234567890');

        $this->assertSame($expected, $payment->status);
        $this->assertSame('142.50', $payment->amount);
        $this->assertSame('PEN', $payment->currency);
        $this->assertSame('HB-261008-0042', $payment->orderCode);
        $this->assertSame('1234567890', $payment->id);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.mercadopago.com/v1/payments/1234567890' && $r->method() === 'GET');
    }

    /**
     * @return array<string, array{string, PaymentStatus}>
     */
    public static function statuses(): array
    {
        return [
            'approved' => ['approved', PaymentStatus::COMPLETED],
            'pending' => ['pending', PaymentStatus::PROCESSING],
            'in process' => ['in_process', PaymentStatus::PROCESSING],
            'in mediation' => ['in_mediation', PaymentStatus::PROCESSING],
            'authorized' => ['authorized', PaymentStatus::PROCESSING],
            'rejected' => ['rejected', PaymentStatus::FAILED],
            'cancelled' => ['cancelled', PaymentStatus::CANCELLED],
            'refunded' => ['refunded', PaymentStatus::REFUNDED],
            'charged back' => ['charged_back', PaymentStatus::REFUNDED],
            'unknown' => ['something_new', PaymentStatus::PROCESSING],
        ];
    }

    public function test_an_unknown_payment_is_null(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Payment not found'], 404)]);

        $this->assertNull($this->gateway()->fetchPayment('123'));
    }

    public function test_a_refund_asks_for_the_amount_with_a_key_that_makes_it_safe_to_repeat(): void
    {
        Http::fake(['*' => Http::response(['id' => 99], 201)]);

        $this->assertTrue($this->gateway()->refund('1234567890', '142.50'));

        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.mercadopago.com/v1/payments/1234567890/refunds'
            && $r->method() === 'POST'
            && $r->data() === ['amount' => 142.5]
            && $r->hasHeader('X-Idempotency-Key', 'hardcover-refund-1234567890'));
    }

    public function test_a_refund_that_was_already_made_counts_as_done(): void
    {
        Http::fake([
            '*/refunds' => Http::response(['message' => 'already refunded'], 400),
            '*/v1/payments/1234567890' => Http::response(['id' => 1234567890, 'status' => 'refunded', 'transaction_amount' => 10]),
        ]);

        $this->assertTrue($this->gateway()->refund('1234567890', '10.00'));
    }

    public function test_a_refund_that_really_failed_is_reported(): void
    {
        Http::fake([
            '*/refunds' => Http::response(['message' => 'cannot refund'], 400),
            '*/v1/payments/1234567890' => Http::response(['id' => 1234567890, 'status' => 'approved', 'transaction_amount' => 10]),
        ]);

        $this->expectException(PaymentUnavailable::class);
        $this->gateway()->refund('1234567890', '10.00');
    }

    /** @return array{Request, string} */
    private function webhook(string $id = '1234567890', array $overrides = []): Request
    {
        $ts = '1704908010';
        $requestId = 'req-abc';
        $secret = $overrides['secret'] ?? 'webhook-secret';
        $signed = $overrides['signed_id'] ?? $id;
        $v1 = $overrides['v1'] ?? hash_hmac('sha256', "id:{$signed};request-id:{$requestId};ts:{$ts};", $secret);

        $request = Request::create("/webhooks/mercadopago?data.id={$id}&type=".($overrides['type'] ?? 'payment'), 'POST', [], [], [], [], json_encode(['type' => $overrides['type'] ?? 'payment', 'data' => ['id' => $id]]));
        $request->headers->set('Content-Type', 'application/json');
        if (! ($overrides['no_signature'] ?? false)) {
            $request->headers->set('x-signature', "ts={$ts},v1={$v1}");
        }
        $request->headers->set('x-request-id', $requestId);

        return $request;
    }

    public function test_a_correctly_signed_payment_notice_gives_the_payment_id(): void
    {
        $this->assertSame('1234567890', $this->gateway()->paymentIdFromWebhook($this->webhook()));
    }

    #[DataProvider('badSignatures')]
    public function test_a_notice_that_is_not_signed_by_the_gateway_is_refused(array $overrides): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->gateway()->paymentIdFromWebhook($this->webhook(overrides: $overrides));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function badSignatures(): array
    {
        return [
            'wrong signature' => [['v1' => str_repeat('0', 64)]],
            'signed with another secret' => [['secret' => 'someone-elses-secret']],
            'signature of another payment id' => [['signed_id' => '999']],
            'no signature header' => [['no_signature' => true]],
        ];
    }

    public function test_without_a_configured_secret_every_notice_is_refused(): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->gateway(secret: null)->paymentIdFromWebhook($this->webhook());
    }

    public function test_other_kinds_of_notice_are_ignored_but_still_need_a_valid_signature(): void
    {
        $this->assertNull($this->gateway()->paymentIdFromWebhook($this->webhook(overrides: ['type' => 'merchant_order'])));

        $this->expectException(InvalidWebhook::class);
        $this->gateway()->paymentIdFromWebhook($this->webhook(overrides: ['type' => 'merchant_order', 'v1' => 'bad']));
    }
}
