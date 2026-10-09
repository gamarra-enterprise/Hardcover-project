<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sales figures for the admin summary. A sale is an order whose payment was confirmed and that
 * was not cancelled or refunded afterwards; its date is when the payment was confirmed (the
 * moment the stock was deducted), in the shop's own time zone.
 */
class SalesReport
{
    /** Statuses that count as a sale. */
    public const SOLD = [OrderStatus::CONFIRMED, OrderStatus::PROCESSING, OrderStatus::SHIPPED, OrderStatus::DELIVERED];

    /** @return array{orders: int, total: string} */
    public function totals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $query = $this->sold($from, $to);

        return ['orders' => (clone $query)->count(), 'total' => bcadd((string) $query->sum('total'), '0', 2)];
    }

    /**
     * One entry per day, oldest first, with the days without sales as zero.
     *
     * @return list<array{date: CarbonImmutable, orders: int, total: string}>
     */
    public function daily(int $days): array
    {
        $tz = config('app.display_timezone');
        $start = CarbonImmutable::now($tz)->startOfDay()->subDays($days - 1);

        $byDay = $this->sold($start, CarbonImmutable::now($tz))->get(['total', 'stock_deducted_at', 'created_at'])
            ->groupBy(fn (Order $o) => ($o->stock_deducted_at ?? $o->created_at)->timezone($tz)->format('Y-m-d'));

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->addDays($i);
            $orders = $byDay->get($day->format('Y-m-d'), collect());
            $out[] = ['date' => $day, 'orders' => $orders->count(), 'total' => bcadd((string) $orders->sum('total'), '0', 2)];
        }

        return $out;
    }

    /**
     * Best sellers by units in the period.
     *
     * @return list<array{name: string, units: int, revenue: string}>
     */
    public function topProducts(CarbonImmutable $from, CarbonImmutable $to, int $limit = 10): array
    {
        $items = OrderItem::query()
            ->whereIn('order_id', $this->sold($from, $to)->select('orders.id'))
            ->get(['quantity', 'unit_price', 'product_id', 'product_snapshot']);

        return $items
            ->groupBy(fn (OrderItem $i) => $i->product_id ?? ($i->product_snapshot['name'] ?? '?'))
            ->map(fn ($group) => [
                'name' => $group->first()->product_snapshot['name'] ?? 'Producto',
                'units' => (int) $group->sum('quantity'),
                'revenue' => bcadd((string) $group->sum(fn (OrderItem $i) => (float) $i->lineTotal()), '0', 2),
            ])
            ->sortByDesc('units')
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return array<string, int> orders per status, every status present */
    public function byStatus(): array
    {
        $counts = Order::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)])->all();
    }

    /** @return Builder<Order> */
    private function sold(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Order::query()
            ->whereIn('status', array_map(fn (OrderStatus $s) => $s->value, self::SOLD))
            ->whereRaw('coalesce(stock_deducted_at, created_at) >= ?', [$from->utc()])
            ->whereRaw('coalesce(stock_deducted_at, created_at) <= ?', [$to->utc()]);
    }
}
