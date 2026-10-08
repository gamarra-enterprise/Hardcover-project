<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Payments\FakeGateway;
use App\Payments\GatewayPayment;
use App\Payments\PaymentGateway;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/** The page of the stand-in gateway. It only exists while PAYMENT_GATEWAY=fake outside production. */
class FakeGatewayController extends Controller
{
    public function show(Request $request, PaymentGateway $gateway, string $paymentId): View
    {
        $remote = $this->remote($request, $gateway, $paymentId);

        return view('payments.fake', [
            'paymentId' => $paymentId,
            'order' => Order::where('tracking_code', $remote->orderCode)->firstOrFail(),
            'amount' => $remote->amount,
        ]);
    }

    public function decide(Request $request, PaymentGateway $gateway, PaymentService $payments, string $paymentId): RedirectResponse
    {
        $remote = $this->remote($request, $gateway, $paymentId);
        $order = Order::where('tracking_code', $remote->orderCode)->firstOrFail();

        $gateway->decide($paymentId, $request->input('result') === 'approve');
        $payments->sync($paymentId);

        // Back to the shop the way the real gateway does it: the shop's signed address, with the
        // payment id added afterwards.
        $returnUrl = URL::temporarySignedRoute('payments.return', now()->addHour(), ['order' => $order->tracking_code]);

        return redirect($returnUrl.'&payment_id='.$paymentId);
    }

    private function remote(Request $request, PaymentGateway $gateway, string $paymentId): GatewayPayment
    {
        abort_unless($gateway instanceof FakeGateway && ! app()->isProduction() && $request->hasValidSignature(), 404);

        return $gateway->fetchPayment($paymentId) ?? abort(404);
    }
}
