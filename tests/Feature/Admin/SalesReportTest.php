<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    private function sale(OrderStatus $status, string $subtotal, $paidAt = null, array $products = []): Order
    {
        $order = $products
            ? Order::factory()->status($status)->withItems($products)->create()
            : Order::factory()->status($status)->create(Order::totalsFor($subtotal, '0.00'));
        $order->forceFill(['stock_deducted_at' => $paidAt ?? now(), 'total' => $subtotal])->save();

        return $order;
    }

    public function test_only_paid_orders_that_stayed_count(): void
    {
        $this->sale(OrderStatus::CONFIRMED, '100.00');
        $this->sale(OrderStatus::DELIVERED, '50.00');
        $this->sale(OrderStatus::PENDING, '999.00');
        $this->sale(OrderStatus::CANCELLED, '999.00');
        $this->sale(OrderStatus::REFUNDED, '999.00');

        $totals = app(SalesReport::class)->totals(CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay());

        $this->assertSame(2, $totals['orders']);
        $this->assertSame('150.00', $totals['total']);
    }

    public function test_orders_outside_the_period_are_left_out_and_days_are_filled(): void
    {
        $this->sale(OrderStatus::SHIPPED, '80.00', now()->subDays(40));
        $this->sale(OrderStatus::SHIPPED, '30.00', now()->subDay());

        $report = app(SalesReport::class);
        $daily = $report->daily(7);

        $this->assertCount(7, $daily);
        $this->assertSame('30.00', bcadd((string) array_sum(array_column($daily, 'total')), '0', 2));
    }

    public function test_top_products_are_ranked_by_units(): void
    {
        [$a, $b] = [Product::factory()->create(['name' => 'Alfa']), Product::factory()->create(['name' => 'Beta'])];
        $this->sale(OrderStatus::CONFIRMED, '10.00', null, [$a]);
        $this->sale(OrderStatus::CONFIRMED, '10.00', null, [$b]);
        $this->sale(OrderStatus::CONFIRMED, '10.00', null, [$b]);

        $top = app(SalesReport::class)->topProducts(CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay());

        $this->assertSame('Beta', $top[0]['name']);
        $this->assertSame(2, $top[0]['units']);
    }

    public function test_page_is_for_staff_only_and_renders(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.sales'))->assertForbidden();
        $this->sale(OrderStatus::CONFIRMED, '100.00');

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.sales', ['dias' => 7]))
            ->assertOk()->assertSee('S/ 100.00');
    }
}
