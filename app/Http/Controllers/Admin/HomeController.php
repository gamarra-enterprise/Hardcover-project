<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\View\View;

/** Landing of the admin panel: what needs attention today. */
class HomeController extends Controller
{
    /** Units at or below this count as running low. */
    private const LOW_STOCK = 3;

    public function __invoke(): View
    {
        $counts = Order::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.index', [
            'toPrepare' => (int) ($counts[OrderStatus::CONFIRMED->value] ?? 0) + (int) ($counts[OrderStatus::PROCESSING->value] ?? 0),
            'toDeliver' => (int) ($counts[OrderStatus::SHIPPED->value] ?? 0),
            'awaitingPayment' => (int) ($counts[OrderStatus::PENDING->value] ?? 0),
            'proofsToReview' => \App\Models\Payment::where('provider', 'bank_transfer')->where('status', 'processing')->count(),
            'lowStock' => Product::where('stock', '<=', self::LOW_STOCK)->where('status', '!=', 'hidden')->count(),
            'recent' => Order::latest()->limit(5)->get(),
        ]);
    }
}
