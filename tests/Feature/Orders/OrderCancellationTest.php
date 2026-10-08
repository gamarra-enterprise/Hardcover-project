<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\OrderNotCancellable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    /** An order of 2 units of a product, paid S/ 100, with its stock already deducted. */
    private function paidOrder(OrderStatus $status, ?User $owner = null): array
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = Order::factory()->status($status)->create(['user_id' => $owner?->id, 'email' => 'cliente@example.com']);
        $order->items()->create(OrderItem::valuesFor($product, 2));
        Payment::factory()->for($order)->completed()->create(['amount' => 100]);
        app(InventoryService::class)->deductForOrder($order);

        return [$order, $product];
    }

    private function service(): OrderService
    {
        return app(OrderService::class);
    }

    public function test_cancelling_a_confirmed_order_refunds_everything_and_gives_the_stock_back(): void
    {
        Notification::fake();
        [$order, $product] = $this->paidOrder(OrderStatus::CONFIRMED);
        $this->assertSame(8, $product->fresh()->stock);

        $refund = $this->service()->cancel($order, reason: 'Me equivoqué de libro');

        $this->assertSame('100.00', $refund);
        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame('100.00', (string) $order->fresh()->refund_amount);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame('Me equivoqué de libro', $order->fresh()->statusHistories->last()->note);
        Notification::assertSentOnDemandTimes(OrderStatusChanged::class, 1);
    }

    public function test_cancelling_while_in_preparation_also_refunds_everything_paid(): void
    {
        [$order, $product] = $this->paidOrder(OrderStatus::PROCESSING);

        $this->assertSame('100.00', $this->service()->cancel($order));
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_the_refund_adds_up_only_the_completed_payments(): void
    {
        [$order] = $this->paidOrder(OrderStatus::CONFIRMED);
        Payment::factory()->for($order)->create(['amount' => 500, 'status' => PaymentStatus::FAILED]);
        Payment::factory()->for($order)->completed()->create(['amount' => 33.33]);

        // The failed attempt of 500 does not count; 100 + 33.33 were really paid.
        $this->assertSame('133.33', $this->service()->cancel($order));
    }

    public function test_an_order_that_was_never_paid_is_cancelled_with_no_refund_and_no_stock_change(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $order = Order::factory()->create();
        $order->items()->create(OrderItem::valuesFor($product, 2));

        $this->assertNull($this->service()->cancel($order));
        $this->assertNull($order->fresh()->refund_amount);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
    }

    #[DataProvider('lateStatuses')]
    public function test_an_order_that_has_shipped_cannot_be_cancelled_and_nothing_changes(OrderStatus $status): void
    {
        [$order, $product] = $this->paidOrder($status);

        try {
            $this->service()->cancel($order);
            $this->fail('The cancellation should have been refused.');
        } catch (OrderNotCancellable $e) {
            $this->assertSame($status, $e->status);
        }

        $this->assertSame($status, $order->fresh()->status);
        $this->assertNull($order->fresh()->refund_amount);
        $this->assertSame(8, $product->fresh()->stock);
    }

    /**
     * @return array<string, array{OrderStatus}>
     */
    public static function lateStatuses(): array
    {
        return [
            'shipped' => [OrderStatus::SHIPPED],
            'delivered' => [OrderStatus::DELIVERED],
            'already cancelled' => [OrderStatus::CANCELLED],
        ];
    }

    public function test_cancelling_twice_does_not_give_the_stock_back_twice(): void
    {
        [$order, $product] = $this->paidOrder(OrderStatus::CONFIRMED);

        $this->service()->cancel($order);
        try {
            $this->service()->cancel($order->fresh());
        } catch (OrderNotCancellable) {
            // expected
        }

        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_the_policy_lets_owners_and_staff_cancel_only_while_it_is_allowed(): void
    {
        $owner = User::factory()->create();
        [$mine] = $this->paidOrder(OrderStatus::CONFIRMED, $owner);
        [$shipped] = $this->paidOrder(OrderStatus::SHIPPED, $owner);

        $this->assertTrue($owner->can('cancel', $mine));
        $this->assertFalse($owner->can('cancel', $shipped));
        $this->assertFalse(User::factory()->create()->can('cancel', $mine));
        $this->assertTrue(User::factory()->admin()->create()->can('cancel', $mine));
        $this->assertFalse(User::factory()->admin()->create()->can('cancel', $shipped));
        // The super admin passes every rule through Gate::before.
        $this->assertTrue(User::factory()->superAdmin()->create()->can('cancel', $shipped));
    }
}
