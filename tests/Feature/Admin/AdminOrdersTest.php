<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_open_the_orders_panel(): void
    {
        $order = Order::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->patch(route('admin.orders.update', $order), ['status' => 'processing'])->assertForbidden();
    }

    public function test_admin_lists_filters_and_searches_orders(): void
    {
        $shipped = Order::factory()->status(OrderStatus::SHIPPED)->create();
        $pending = Order::factory()->status(OrderStatus::PENDING)->create();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.orders.index'))
            ->assertOk()->assertSee($shipped->tracking_code)->assertSee($pending->tracking_code);
        $this->actingAs($admin)->get(route('admin.orders.index', ['estado' => 'shipped']))
            ->assertSee($shipped->tracking_code)->assertDontSee($pending->tracking_code);
        $this->actingAs($admin)->get(route('admin.orders.index', ['q' => $pending->tracking_code]))
            ->assertSee($pending->tracking_code)->assertDontSee($shipped->tracking_code);
    }

    public function test_admin_moves_an_order_forward_and_it_is_recorded(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->status(OrderStatus::CONFIRMED)->create();

        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk();
        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'processing', 'note' => 'Empacando'])
            ->assertRedirect();

        $this->assertSame(OrderStatus::PROCESSING, $order->fresh()->status);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'to_status' => 'processing', 'changed_by' => $admin->id, 'note' => 'Empacando']);
    }

    public function test_payment_confirmation_and_refund_cannot_be_forced_by_hand(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = Order::factory()->status(OrderStatus::PENDING)->create();
        $cancelled = Order::factory()->status(OrderStatus::CANCELLED)->create();

        $this->actingAs($admin)->patch(route('admin.orders.update', $pending), ['status' => 'confirmed'])->assertSessionHasErrors('status');
        $this->actingAs($admin)->patch(route('admin.orders.update', $cancelled), ['status' => 'refunded'])->assertStatus(422);
        $this->assertSame(OrderStatus::PENDING, $pending->fresh()->status);
    }

    public function test_admin_cancels_an_order(): void
    {
        Notification::fake();
        $order = Order::factory()->status(OrderStatus::PROCESSING)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])->assertRedirect();

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
    }

    public function test_shipped_order_cannot_be_cancelled(): void
    {
        $order = Order::factory()->status(OrderStatus::SHIPPED)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])->assertSessionHasErrors('status');
        $this->assertSame(OrderStatus::SHIPPED, $order->fresh()->status);
    }
}
