<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

/** Landing of the admin panel: what needs attention today. */
class HomeController extends Controller
{
    /** Units at or below this count as running low. */
    private const LOW_STOCK = 3;

    public function __invoke(SalesReport $report): View
    {
        $now = CarbonImmutable::now(config('app.display_timezone'));
        $month = $report->totals($now->subDays(29)->startOfDay(), $now);
        $daily = $report->daily(7);
        $counts = Order::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.index', [
            'toPrepare' => (int) ($counts[OrderStatus::CONFIRMED->value] ?? 0) + (int) ($counts[OrderStatus::PROCESSING->value] ?? 0),
            'toDeliver' => (int) ($counts[OrderStatus::SHIPPED->value] ?? 0),
            'awaitingPayment' => (int) ($counts[OrderStatus::PENDING->value] ?? 0),
            'proofsToReview' => \App\Models\Payment::where('provider', 'bank_transfer')->where('status', 'processing')->count(),
            'lowStock' => Product::where('stock', '<=', self::LOW_STOCK)->where('status', '!=', 'hidden')->count(),
            'salesToday' => $report->totals($now->startOfDay(), $now),
            'avgTicket' => $month['orders'] ? bcdiv($month['total'], (string) $month['orders'], 2) : null,
            'daily' => $daily,
            'maxDay' => max(1.0, (float) max(array_column($daily, 'total'))),
            'restock' => Product::where('stock', '<=', self::LOW_STOCK + 2)->where('status', '!=', 'hidden')->orderBy('stock')->orderBy('name')->limit(6)->get(['id', 'name', 'slug', 'stock']),
            'recent' => Order::latest()->limit(5)->get(),
        ]);
    }
}
