<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Contracts\View\View;

/**
 * Payment gateway status: which one is active, whether its credentials are set, and the addresses
 * to register in Mercado Pago. It is read-only on purpose: the keys live in the server's `.env`,
 * never in the database or on screen, so nobody can read or leak them from the panel.
 */
class GatewayController extends Controller
{
    public function __invoke(): View
    {
        $active = (string) config('shop.payment_gateway');
        $mp = config('services.mercadopago');
        $url = (string) config('app.url');

        $checks = [
            ['Token de acceso (MERCADOPAGO_ACCESS_TOKEN)', filled($mp['access_token'])],
            ['Clave pública (MERCADOPAGO_PUBLIC_KEY), para el formulario de tarjeta', filled($mp['public_key'])],
            ['Secreto del webhook (MERCADOPAGO_WEBHOOK_SECRET)', filled($mp['webhook_secret'])],
            ['APP_URL pública con https', str_starts_with($url, 'https://') && ! str_contains($url, 'localhost')],
        ];

        return view('super.gateways', [
            'active' => $active,
            'isFake' => $active === 'fake',
            'checks' => $checks,
            'ready' => collect($checks)->every(fn ($c) => $c[1]),
            'webhookUrl' => route('payments.webhook'),
            'byStatus' => Payment::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'recent' => Payment::with('order:id,tracking_code')->latest('id')->limit(8)->get(),
        ]);
    }
}
