<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BankTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
        config(['shop.bank_transfer' => ['bank' => 'BCP', 'holder' => 'Hardcover SAC', 'account' => '193-1234567-0-12', 'cci' => null]]);
    }

    private function pendingOrder(int $stock = 5): Order
    {
        $product = Product::factory()->create(['stock' => $stock, 'price' => 40]);

        return Order::factory()->guest()->withItems([$product])->create(['email' => 'ana@example.com']);
    }

    private function upload(Order $order, ?UploadedFile $file = null)
    {
        return $this->post($order->transferUrl(), ['proof' => $file ?? UploadedFile::fake()->image('voucher.jpg')]);
    }

    private function staff(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_the_order_page_shows_the_bank_details_only_when_configured(): void
    {
        $order = $this->pendingOrder();

        $this->get($order->signedUrl())->assertSee('Pagar por transferencia bancaria')->assertSee('193-1234567-0-12');

        config(['shop.bank_transfer.account' => null]);
        $this->get($order->signedUrl())->assertDontSee('Pagar por transferencia bancaria');
    }

    public function test_uploading_the_proof_registers_a_payment_waiting_for_review(): void
    {
        $order = $this->pendingOrder();

        $this->upload($order)->assertRedirect()->assertSessionHas('payment_notice');

        $payment = $order->payments()->firstOrFail();
        $this->assertSame('bank_transfer', $payment->provider);
        $this->assertSame(PaymentStatus::PROCESSING, $payment->status);
        $this->assertSame($order->total, $payment->amount);
        Storage::disk('local')->assertExists($payment->payload['proof']);
        // Nothing moves until a person confirms.
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame(5, Product::first()->stock);
    }

    public function test_the_upload_needs_the_signed_address_and_a_valid_file(): void
    {
        $order = $this->pendingOrder();

        $this->post(route('orders.transfer', $order), ['proof' => UploadedFile::fake()->image('a.jpg')])->assertNotFound();
        $this->upload($order, UploadedFile::fake()->create('virus.exe', 10))->assertSessionHasErrors('proof');
        $this->assertSame(0, $order->payments()->count());
    }

    public function test_a_second_proof_is_refused_while_the_first_is_under_review(): void
    {
        $order = $this->pendingOrder();
        $this->upload($order);
        $this->upload($order)->assertSessionHas('payment_error');

        $this->assertSame(1, $order->payments()->count());
    }

    public function test_confirming_takes_the_stock_confirms_the_order_and_tells_the_customer(): void
    {
        $order = $this->pendingOrder(5);
        $this->upload($order);
        $payment = $order->payments()->firstOrFail();

        $this->actingAs($this->staff())->post(route('admin.payments.confirm', $payment))->assertSessionHas('notice');

        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertSame(PaymentStatus::COMPLETED, $payment->fresh()->status);
        $this->assertSame(4, Product::first()->stock);
        Notification::assertSentOnDemand(OrderStatusChanged::class);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.transfer_confirmed']);
    }

    public function test_confirming_twice_changes_nothing_the_second_time(): void
    {
        $order = $this->pendingOrder(5);
        $this->upload($order);
        $payment = $order->payments()->firstOrFail();
        $admin = $this->staff();

        $this->actingAs($admin)->post(route('admin.payments.confirm', $payment));
        $this->actingAs($admin)->post(route('admin.payments.confirm', $payment))->assertSessionHas('error');

        $this->assertSame(4, Product::first()->stock);
    }

    public function test_rejecting_leaves_the_order_pending_and_lets_the_customer_try_again(): void
    {
        $order = $this->pendingOrder();
        $this->upload($order);
        $payment = $order->payments()->firstOrFail();

        $this->actingAs($this->staff())->post(route('admin.payments.reject', $payment), ['reason' => 'El monto no coincide'])->assertSessionHas('notice');

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame(PaymentStatus::FAILED, $payment->fresh()->status);
        $this->get($order->signedUrl())->assertSee('El monto no coincide');
        $this->upload($order)->assertSessionHas('payment_notice');
        $this->assertSame(2, $order->payments()->count());
    }

    public function test_confirming_when_the_last_unit_was_sold_cancels_and_leaves_a_manual_refund(): void
    {
        $order = $this->pendingOrder(1);
        $this->upload($order);
        Product::query()->update(['stock' => 0]);
        $payment = $order->payments()->firstOrFail();

        $this->actingAs($this->staff())->post(route('admin.payments.confirm', $payment));

        $order->refresh();
        $this->assertSame(OrderStatus::CANCELLED, $order->status);
        $this->assertSame(PaymentStatus::COMPLETED, $payment->fresh()->status, 'the money is still with the shop until a person returns it');
        $this->assertNotNull($order->refund_amount);
    }

    public function test_cancelling_a_paid_transfer_waits_for_the_manual_refund_then_closes(): void
    {
        $order = $this->pendingOrder(5);
        $this->upload($order);
        $payment = $order->payments()->firstOrFail();
        $admin = $this->staff();
        $this->actingAs($admin)->post(route('admin.payments.confirm', $payment));

        app(OrderService::class)->cancel($order->fresh(), $admin);

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(PaymentStatus::COMPLETED, $payment->fresh()->status);
        $this->assertSame(5, Product::first()->stock);

        $this->actingAs($admin)->post(route('admin.payments.refunded', $payment))->assertSessionHas('notice');

        $this->assertSame(PaymentStatus::REFUNDED, $payment->fresh()->status);
        $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);
    }

    public function test_only_staff_see_proofs_and_decide(): void
    {
        $order = $this->pendingOrder();
        $this->upload($order);
        $payment = $order->payments()->firstOrFail();
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('admin.payments.proof', $payment))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.payments.confirm', $payment))->assertForbidden();
        $this->actingAs($this->staff())->get(route('admin.payments.proof', $payment))->assertOk();
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }
}
