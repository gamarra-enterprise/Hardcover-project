<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Sales summary for the admin: today, this week and month, the last days, and best sellers. */
class SalesController extends Controller
{
    private const PERIODS = [7, 30, 90];

    public function __invoke(Request $request, SalesReport $report): View
    {
        Gate::authorize('viewAny', Order::class);

        $days = in_array((int) $request->query('dias'), self::PERIODS, true) ? (int) $request->query('dias') : 30;
        $now = CarbonImmutable::now(config('app.display_timezone'));
        $periodStart = $now->startOfDay()->subDays($days - 1);

        $daily = $report->daily($days);

        return view('admin.sales', [
            'days' => $days,
            'periods' => self::PERIODS,
            'today' => $report->totals($now->startOfDay(), $now),
            'week' => $report->totals($now->startOfWeek(), $now),
            'month' => $report->totals($now->startOfMonth(), $now),
            'period' => $report->totals($periodStart, $now),
            'daily' => $daily,
            'maxDay' => max(1.0, (float) max(array_column($daily, 'total'))),
            'top' => $report->topProducts($periodStart, $now),
            'byStatus' => $report->byStatus(),
        ]);
    }
}
