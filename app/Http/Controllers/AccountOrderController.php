<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderNotCancellable;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Mis pedidos": the signed-in customer's own orders, and the cancel action.
 */
class AccountOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()->orders()->withCount('items')->latest()->paginate(10);

        return view('account.orders', ['orders' => $orders]);
    }

    public function cancel(Request $request, Order $order, OrderService $service): RedirectResponse
    {
        abort_unless($request->user()->can('view', $order), 404);
        abort_unless($request->user()->can('cancel', $order), 403);

        try {
            $service->cancel($order, $request->user(), 'Cancelado por el cliente');
        } catch (OrderNotCancellable) {
            return back()->with('payment_error', 'Este pedido ya no se puede cancelar.');
        }

        return back()->with('payment_notice', 'Pedido cancelado.');
    }
}
