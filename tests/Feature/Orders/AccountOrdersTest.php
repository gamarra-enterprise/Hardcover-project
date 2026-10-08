<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('account.orders'))->assertRedirect(route('login'));
    }

    public function test_customer_sees_only_their_own_orders(): void
    {
        $user = User::factory()->create();
        $mine = Order::factory()->create(['user_id' => $user->id]);
        $other = Order::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($user)->get(route('account.orders'))
            ->assertOk()->assertSee($mine->tracking_code)->assertDontSee($other->tracking_code);
    }

    public function test_customer_cancels_their_own_order(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $order = Order::factory()->status(OrderStatus::PENDING)->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('orders.cancel', $order))->assertRedirect();

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
    }

    public function test_cannot_cancel_someone_elses_or_a_shipped_order(): void
    {
        $user = User::factory()->create();
        $other = Order::factory()->status(OrderStatus::PENDING)->create(['user_id' => User::factory()->create()->id]);
        $shipped = Order::factory()->status(OrderStatus::SHIPPED)->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('orders.cancel', $other))->assertNotFound();
        $this->actingAs($user)->post(route('orders.cancel', $shipped))->assertForbidden();
        $this->assertSame(OrderStatus::SHIPPED, $shipped->fresh()->status);
    }
}
