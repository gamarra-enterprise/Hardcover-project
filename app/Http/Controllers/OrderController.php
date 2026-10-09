<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Payments\PaymentGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * An order is private: it shows an address and a name. It opens for its owner and the staff, or
     * with the signed link given at checkout. Anything else looks like a page that does not exist,
     * so order codes cannot be guessed one by one.
     */
    public function show(Request $request, Order $order, PaymentGateway $gateway): View
    {
        abort_unless(
            $request->hasValidSignature() || ($request->user()?->can('view', $order)),
            404,
        );

        $order->load(['items', 'statusHistories', 'payments']);

        return view('orders.show', ['order' => $order, 'canPayWithCard' => $gateway->supportsCards(), 'transfer' => app(\App\Services\BankTransferService::class)->enabled() ? config('shop.bank_transfer') : null]);
    }
}
