<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentNotAllowed;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Notifications\OrderStatusChanged;
use App\Payments\FakeGateway;
use App\Payments\GatewayCheckout;
use App\Payments\PaymentGateway;
use App\Payments\PaymentUnavailable;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function payments(): PaymentService
    {
        return app(PaymentService::class);
    }

    private function gateway(): FakeGateway
    {
        return app(PaymentGateway::class);
    }

    private function pendingOrder(Product $product, int $quantity = 1, string $email = 'ana@example.com'): Order
    {
        $order = Order::factory()->create(['email' => $email, 'status' => OrderStatus::PENDING]);
        $order->items()->create(OrderItem::valuesFor($product, $quantity));
        $order->update(Order::totalsFor(bcmul($product->currentPrice(), (string) $quantity, 2), '0'));

        return $order->fresh();
    }

    /** Open the checkout and return the id the gateway gave to the payment. */
    private function open(Order $order): string
    {
        $checkout = $this->payments()->start($order);
        preg_match('#/pago/prueba/(FAKE-[A-Z0-9]+)#', $checkout->url, $m);

        return $m[1];
    }

    /** The customer pays (or is rejected) on the gateway and the shop is told. */
    private function pay(Order $order, bool $approve = true): ?Payment
    {
        $id = $this->open($order);
        $this->gateway()->decide($id, $approve);

        return $this->payments()->sync($id);
    }

    public function test_starting_a_payment_opens_the_checkout_and_leaves_one_pending_attempt(): void
    {
        $order = $this->pendingOrder(Product::factory()->create(['price' => 50]));

        $checkout = $this->payments()->start($order);

        $this->assertStringContainsString('/pago/prueba/FAKE-', $checkout->url);
        $payment = $order->payments()->sole();
        $this->assertSame(PaymentStatus::PENDING, $payment->status);
        $this->assertSame('fake', $payment->provider);
        $this->assertSame('50.00', (string) $payment->amount);
        $this->assertSame('pref:'.$checkout->reference, $payment->external_reference);
    }

    public function test_opening_the_checkout_again_cancels_the_abandoned_attempt(): void
    {
        $order = $this->pendingOrder(Product::factory()->create());

        $this->payments()->start($order);
        $this->payments()->start($order);

        $this->assertSame([PaymentStatus::CANCELLED, PaymentStatus::PENDING], $order->payments()->orderBy('id')->get()->pluck('status')->all());
    }

    public function test_an_order_that_is_not_pending_cannot_be_paid(): void
    {
        $order = $this->pendingOrder(Product::factory()->create());
        $order->transitionTo(OrderStatus::CANCELLED);

        $this->expectException(PaymentNotAllowed::class);
        $this->expectExceptionMessage('ya no está pendiente de pago');
        $this->payments()->start($order);
    }

    public function test_it_will_not_open_a_second_payment_while_one_is_being_verified(): void
    {
        $order = $this->pendingOrder(Product::factory()->create());
        Payment::factory()->for($order)->create(['status' => PaymentStatus::PROCESSING]);

        $this->expectException(PaymentNotAllowed::class);
        $this->expectExceptionMessage('verificando tu pago');
        $this->payments()->start($order);
    }

    public function test_it_will_not_charge_for_products_that_are_already_gone(): void
    {
        $product = Product::factory()->create(['stock' => 2]);
        $order = $this->pendingOrder($product, 2);
        $product->update(['stock' => 1]);

        try {
            $this->payments()->start($order);
            $this->fail('It should have refused.');
        } catch (PaymentNotAllowed $e) {
            $this->assertStringContainsString('ya no están disponibles', $e->getMessage());
        }

        $this->assertSame(0, $order->payments()->count());
    }

    public function test_it_will_not_charge_for_a_product_that_was_deleted(): void
    {
        $product = Product::factory()->create();
        $order = $this->pendingOrder($product);
        $product->delete();

        $this->expectException(PaymentNotAllowed::class);
        $this->payments()->start($order);
    }

    public function test_a_gateway_failure_marks_the_attempt_failed_and_is_passed_on(): void
    {
        $this->app->instance(PaymentGateway::class, new class extends FakeGateway
        {
            public function createCheckout(Order $order, Payment $payment): GatewayCheckout
            {
                throw new PaymentUnavailable('down');
            }
        });
        $order = $this->pendingOrder(Product::factory()->create());

        try {
            app(PaymentService::class)->start($order);
            $this->fail('It should have failed.');
        } catch (PaymentUnavailable) {
            // expected
        }

        $this->assertSame(PaymentStatus::FAILED, $order->payments()->sole()->status);
    }

    public function test_an_approved_payment_confirms_the_order_and_only_then_takes_the_stock(): void
    {
        Notification::fake();
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);
        $order = $this->pendingOrder($product, 2);
        $id = $this->open($order);

        // Before the payment is approved nothing moves.
        $this->assertSame(10, $product->fresh()->stock);

        $this->gateway()->decide($id, true);
        $payment = $this->payments()->sync($id);

        $this->assertSame(PaymentStatus::COMPLETED, $payment->status);
        $this->assertSame($id, $payment->external_reference);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(1, $order->payments()->count());
        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertSame('Pago confirmado', $order->fresh()->statusHistories->last()->note);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertNotNull($order->fresh()->stock_deducted_at);
        Notification::assertSentOnDemandTimes(OrderStatusChanged::class, 1);
    }

    public function test_a_rejected_payment_leaves_the_order_pending_and_the_stock_alone(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $order = $this->pendingOrder($product);

        $payment = $this->pay($order, approve: false);

        $this->assertSame(PaymentStatus::FAILED, $payment->status);
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock);

        // And the customer can try again.
        $retry = $this->pay($order);
        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertNotSame($payment->id, $retry->id);
    }

    public function test_a_payment_the_customer_has_not_decided_yet_changes_nothing(): void
    {
        $order = $this->pendingOrder(Product::factory()->create());
        $id = $this->open($order);

        $payment = $this->payments()->sync($id);

        $this->assertSame(PaymentStatus::PENDING, $payment->status);
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_hearing_about_the_same_payment_again_changes_nothing(): void
    {
        Notification::fake();
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->pendingOrder($product, 3);
        $id = $this->open($order);
        $this->gateway()->decide($id, true);

        $this->payments()->sync($id);
        $this->payments()->sync($id);
        $this->payments()->sync($id);

        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(1, $order->payments()->count());
        $this->assertSame(2, $order->fresh()->statusHistories->count());
        Notification::assertSentOnDemandTimes(OrderStatusChanged::class, 1);
    }

    public function test_the_first_payment_confirmed_takes_the_last_unit_and_the_other_one_is_refunded(): void
    {
        Notification::fake();
        $product = Product::factory()->create(['price' => 40, 'stock' => 1, 'name' => 'Último libro']);
        // Ana placed her order first, but Luis is the one who pays first.
        $ana = $this->pendingOrder($product, 1, 'ana@example.com');
        $luis = $this->pendingOrder($product, 1, 'luis@example.com');
        $anaPayment = $this->open($ana);
        $luisPayment = $this->open($luis);
        $this->gateway()->decide($anaPayment, true);
        $this->gateway()->decide($luisPayment, true);

        $this->payments()->sync($luisPayment);
        $this->payments()->sync($anaPayment);

        $this->assertSame(OrderStatus::CONFIRMED, $luis->fresh()->status);
        $this->assertSame(0, $product->fresh()->stock);

        // Ana lost the unit: her order is cancelled, her money goes back, and she is told why.
        $ana = $ana->fresh();
        $this->assertSame(OrderStatus::REFUNDED, $ana->status);
        $this->assertSame('40.00', (string) $ana->refund_amount);
        $this->assertSame(PaymentStatus::REFUNDED, $ana->payments()->sole()->status);
        $this->assertNull($ana->stock_deducted_at);
        $this->assertSame(['pending', 'cancelled', 'refunded'], $ana->statusHistories->map(fn ($h) => $h->to_status->value)->all());
        $this->assertSame('Sin stock al confirmar el pago', $ana->statusHistories[1]->note);
        Notification::assertSentOnDemand(OrderStatusChanged::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'ana@example.com'
            && $n->status === OrderStatus::CANCELLED && str_contains((string) $n->reason, 'última unidad'));
        $this->assertSame(PaymentStatus::COMPLETED, $luis->payments()->sole()->status);
    }

    public function test_when_an_order_loses_the_race_the_other_products_of_it_are_untouched(): void
    {
        $scarce = Product::factory()->create(['stock' => 1]);
        $plenty = Product::factory()->create(['stock' => 10]);
        $first = $this->pendingOrder($scarce);
        $second = Order::factory()->create(['email' => 'b@example.com']);
        $second->items()->create(OrderItem::valuesFor($plenty, 2));
        $second->items()->create(OrderItem::valuesFor($scarce, 1));
        $second->update(Order::totalsFor(bcadd(bcmul($plenty->currentPrice(), '2', 2), $scarce->currentPrice(), 2), '0'));

        // Both open the gateway's checkout while the unit is still there, then both pay.
        $firstId = $this->open($first);
        $secondId = $this->open($second->fresh());
        $this->gateway()->decide($firstId, true);
        $this->gateway()->decide($secondId, true);

        $this->payments()->sync($firstId);
        $this->payments()->sync($secondId);

        $this->assertSame(10, $plenty->fresh()->stock);
        $this->assertSame(OrderStatus::REFUNDED, $second->fresh()->status);
    }

    public function test_a_second_payment_for_an_order_that_is_already_paid_is_refunded(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->pendingOrder($product, 2);
        $first = $this->open($order);
        $second = $this->open($order);
        $this->gateway()->decide($first, true);
        $this->gateway()->decide($second, true);

        $this->payments()->sync($first);
        $this->payments()->sync($second);

        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock);
        $statuses = $order->payments()->pluck('status')->map->value->sort()->values()->all();
        $this->assertSame(['completed', 'refunded'], $statuses);
    }

    public function test_a_payment_that_arrives_after_the_order_was_cancelled_is_refunded(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $order = $this->pendingOrder($product);
        $id = $this->open($order);
        app(OrderService::class)->cancel($order);
        $this->gateway()->decide($id, true);

        $this->payments()->sync($id);

        $order = $order->fresh();
        $this->assertSame(OrderStatus::REFUNDED, $order->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->payments()->sole()->status);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertNotNull($order->refund_amount);
    }

    public function test_a_payment_for_another_amount_is_never_accepted(): void
    {
        $product = Product::factory()->create(['price' => 50, 'stock' => 5]);
        $order = $this->pendingOrder($product);
        $id = $this->open($order);
        $this->gateway()->decide($id, true);
        // The gateway reports a different amount than the order's.
        Cache::put('fake-payment:'.$id, [...Cache::get('fake-payment:'.$id), 'amount' => '10.00']);

        $this->payments()->sync($id);

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(PaymentStatus::REFUNDED, $order->payments()->sole()->status);
    }

    public function test_an_unknown_payment_or_one_of_another_order_is_ignored(): void
    {
        $order = $this->pendingOrder(Product::factory()->create());
        $other = $this->pendingOrder(Product::factory()->create());
        $id = $this->open($other);
        $this->gateway()->decide($id, true);

        $this->assertNull($this->payments()->sync('FAKE-DOES-NOT-EXIST'));
        $this->assertNull($this->payments()->sync($id, onlyForOrder: $order));
        $this->assertSame(OrderStatus::PENDING, $other->fresh()->status);
    }

    public function test_cancelling_a_paid_order_returns_the_money_and_the_stock(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $order = $this->pendingOrder($product, 2);
        $this->pay($order);
        $this->assertSame(3, $product->fresh()->stock);

        $refund = app(OrderService::class)->cancel($order->fresh());

        $order = $order->fresh();
        $this->assertNotNull($refund);
        $this->assertSame(OrderStatus::REFUNDED, $order->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->payments()->sole()->status);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_a_refund_that_fails_leaves_the_order_cancelled_and_is_retried_later(): void
    {
        $double = new class extends FakeGateway
        {
            public bool $failing = true;

            public function refund(string $paymentId, string $amount): bool
            {
                if ($this->failing) {
                    throw new PaymentUnavailable('gateway down');
                }

                return parent::refund($paymentId, $amount);
            }
        };
        $this->app->instance(PaymentGateway::class, $double);
        $product = Product::factory()->create(['price' => 50, 'stock' => 5]);
        $order = $this->pendingOrder($product);
        $id = (function () use ($order, $double) {
            $checkout = app(PaymentService::class)->start($order);
            preg_match('#/pago/prueba/(FAKE-[A-Z0-9]+)#', $checkout->url, $m);
            $double->decide($m[1], true);
            app(PaymentService::class)->sync($m[1]);

            return $m[1];
        })();

        app(OrderService::class)->cancel($order->fresh());

        $order = $order->fresh();
        $this->assertSame(OrderStatus::CANCELLED, $order->status);
        $this->assertSame(PaymentStatus::COMPLETED, $order->payments()->sole()->status);
        $this->assertSame('50.00', (string) $order->refund_amount);

        // The gateway is back: the scheduled retry finishes the job.
        $double->failing = false;
        $this->artisan('payments:retry-refunds')->expectsOutputToContain('1')->assertSuccessful();

        $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->payments()->sole()->status);
        $this->assertNotEmpty($id);
    }

    public function test_the_gateway_saying_the_money_went_back_closes_a_cancelled_order(): void
    {
        $order = $this->pendingOrder(Product::factory()->create());
        $id = $this->open($order);
        $this->gateway()->decide($id, true);
        $this->payments()->sync($id);
        $order->fresh()->transitionTo(OrderStatus::CANCELLED);
        $this->gateway()->refund($id, '1.00');

        $this->payments()->sync($id);

        $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);
    }

    public function test_money_returned_from_outside_on_a_confirmed_order_is_only_recorded(): void
    {
        $order = $this->pendingOrder(Product::factory()->create());
        $id = $this->open($order);
        $this->gateway()->decide($id, true);
        $this->payments()->sync($id);
        $this->gateway()->refund($id, '1.00');

        $this->payments()->sync($id);

        $this->assertSame(PaymentStatus::REFUNDED, $order->payments()->sole()->status);
        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
    }
}
