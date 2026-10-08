<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_happy_path_goes_through_every_stage_and_leaves_a_history(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['email' => 'cliente@example.com']);

        foreach ([OrderStatus::CONFIRMED, OrderStatus::PROCESSING, OrderStatus::SHIPPED, OrderStatus::DELIVERED] as $status) {
            $order->transitionTo($status, $admin);
        }

        $history = $order->fresh()->statusHistories;
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame(
            ['pending', 'confirmed', 'processing', 'shipped', 'delivered'],
            $history->map(fn ($h) => $h->to_status->value)->all(),
        );
        $this->assertNull($history->first()->from_status);
        $this->assertSame('Pedido creado', $history->first()->note);
        $this->assertSame($admin->id, $history->last()->changed_by);
        $this->assertSame(OrderStatus::SHIPPED, $history->last()->from_status);
    }

    #[DataProvider('forbiddenMoves')]
    public function test_a_transition_that_is_not_allowed_is_refused_and_changes_nothing(OrderStatus $from, OrderStatus $to): void
    {
        $order = Order::factory()->status($from)->create();

        try {
            $order->transitionTo($to);
            $this->fail('The transition should have been refused.');
        } catch (InvalidOrderTransition $e) {
            $this->assertSame($from, $e->from);
            $this->assertSame($to, $e->to);
        }

        $this->assertSame($from, $order->fresh()->status);
        $this->assertCount(1, $order->fresh()->statusHistories);
    }

    /**
     * @return array<string, array{OrderStatus, OrderStatus}>
     */
    public static function forbiddenMoves(): array
    {
        return [
            'skip a stage' => [OrderStatus::PENDING, OrderStatus::SHIPPED],
            'go backwards' => [OrderStatus::SHIPPED, OrderStatus::PROCESSING],
            'cancel once shipped' => [OrderStatus::SHIPPED, OrderStatus::CANCELLED],
            'reopen a cancelled order' => [OrderStatus::CANCELLED, OrderStatus::PENDING],
            'leave refunded' => [OrderStatus::REFUNDED, OrderStatus::DELIVERED],
            'same status' => [OrderStatus::PENDING, OrderStatus::PENDING],
        ];
    }

    public function test_the_status_is_checked_against_the_database_not_the_stale_object(): void
    {
        $order = Order::factory()->create();
        $other = Order::find($order->id);

        $order->transitionTo(OrderStatus::CANCELLED);

        // $other still believes the order is pending, but another request already cancelled it.
        $this->expectException(InvalidOrderTransition::class);
        $other->transitionTo(OrderStatus::CONFIRMED);
    }

    public function test_the_customer_is_told_by_email_with_the_order_code(): void
    {
        Notification::fake();
        $order = Order::factory()->create(['email' => 'cliente@example.com']);

        $order->transitionTo(OrderStatus::CONFIRMED);

        Notification::assertSentOnDemand(OrderStatusChanged::class, function ($notification, $channels, $notifiable) use ($order) {
            return $notifiable->routes['mail'] === 'cliente@example.com'
                && $notification->order->is($order)
                && $notification->status === OrderStatus::CONFIRMED;
        });
    }

    public function test_the_email_falls_back_to_the_account_and_a_guest_without_email_gets_none(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'cuenta@example.com']);

        Order::factory()->for($user)->create()->transitionTo(OrderStatus::CONFIRMED);
        Notification::assertSentOnDemand(OrderStatusChanged::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'cuenta@example.com');

        Notification::fake();
        Order::factory()->guest()->create()->transitionTo(OrderStatus::CONFIRMED);
        Notification::assertNothingSent();
    }

    public function test_the_notice_is_in_spanish_and_mentions_the_refund(): void
    {
        $order = Order::factory()->create(['refund_amount' => 79.2, 'tracking_code' => 'HB-261008-0001']);

        $mail = (new OrderStatusChanged($order, OrderStatus::CANCELLED))->toMail($order);
        $text = collect([$mail->subject, ...$mail->introLines])->implode(' ');

        $this->assertStringContainsString('Tu pedido HB-261008-0001: Cancelado', $text);
        $this->assertStringContainsString('Te devolveremos S/ 79.20', $text);
    }

    public function test_the_cancellable_stages_and_the_labels(): void
    {
        $this->assertSame(
            [OrderStatus::PENDING, OrderStatus::CONFIRMED, OrderStatus::PROCESSING],
            array_values(array_filter(OrderStatus::cases(), fn ($s) => $s->customerCanCancel())),
        );
        $this->assertSame('En preparación', OrderStatus::PROCESSING->label());
        $this->assertSame('Pendiente de pago', OrderStatus::PENDING->label());
        $this->assertCount(7, array_unique(array_map(fn ($s) => $s->label(), OrderStatus::cases())));
    }

    public function test_money_fractions_round_half_up_to_the_cent(): void
    {
        $this->assertSame('30.00', Money::fraction('33.33', 90, 100));
        $this->assertSame('10.07', Money::fraction('66.00', 18, 118));
        $this->assertSame('18.00', Money::fraction('118.00', 18, 118));
        $this->assertSame('0.00', Money::fraction('0', 90, 100));
        // The IGV contained in a total is rounded, not cut.
        $this->assertSame('10.07', Order::totalsFor('66.00', '0')['tax']);
    }
}
