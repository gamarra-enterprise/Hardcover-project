@use('App\Support\Money')

<x-shop-layout title="Pasarela de prueba">
    <div class="wrap" style="padding-block: 28px 0; max-width: 560px">
        <div class="notice" role="note">Pasarela de prueba. Solo existe en desarrollo: no se cobra nada.</div>

        <h1 style="font-size: clamp(2rem, 4.5vw, 2.8rem); font-weight: 800; letter-spacing: -.035em; margin-top: 1.2rem">Pagar pedido <span class="num">{{ $order->tracking_code }}</span></h1>
        <p style="margin-top: .6rem; font-size: 1.3rem">Total a pagar: <b class="num">{{ Money::format($amount) }}</b></p>

        <form method="post" action="{{ URL::temporarySignedRoute('payments.fake.decide', now()->addHours(2), ['paymentId' => $paymentId]) }}"
              class="card" style="padding: 22px; display: grid; gap: 12px; margin-top: 22px">
            @csrf
            <p class="muted">Elige qué respondería la pasarela:</p>
            <button type="submit" name="result" value="approve" class="btn btn-primary">Aprobar el pago</button>
            <button type="submit" name="result" value="reject" class="btn">Rechazar el pago</button>
        </form>
    </div>
</x-shop-layout>
