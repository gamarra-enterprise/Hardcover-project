@use('App\Enums\OrderStatus')
@use('App\Support\Money')

@php
    $address = $order->shipping_address;
    $isOff = in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true);
    $reached = array_search($order->status, OrderStatus::timeline(), true);
@endphp

<x-shop-layout :title="'Pedido '.$order->tracking_code">
    <div class="wrap" style="padding-block: 28px 0">
        <nav class="muted" style="font-size: 13px" aria-label="Ruta"><a href="{{ route('home') }}">Inicio</a> / Pedido</nav>
        <h1 style="font-size: clamp(2rem, 4.5vw, 3rem); font-weight: 800; letter-spacing: -.035em; margin-top: .3rem">
            Pedido <span class="num">{{ $order->tracking_code }}</span>
        </h1>
        <p style="margin-top: .5rem">
            <span class="status-pill" style="color: {{ $isOff ? 'var(--bad)' : ($order->status === OrderStatus::DELIVERED ? 'var(--ok)' : 'var(--accent-deep)') }}">{{ $order->status->label() }}</span>
            <span class="muted" style="margin-left: .6rem">{{ $order->created_at->timezone(config('app.display_timezone'))->translatedFormat('j \d\e F \d\e Y, H:i') }}</span>
        </p>

        @if (! $isOff)
            <ol class="timeline" aria-label="Estado del pedido">
                @foreach (OrderStatus::timeline() as $i => $step)
                    <li class="{{ $reached !== false && $i <= $reached ? 'on' : '' }}" @if ($step === $order->status) aria-current="step" @endif>{{ $step->label() }}</li>
                @endforeach
            </ol>
        @endif

        @if (session('payment_notice'))
            <div class="notice" role="status">{{ session('payment_notice') }}</div>
        @endif
        @if (session('payment_error'))
            <div class="notice notice-error" role="alert">{{ session('payment_error') }}</div>
        @endif

        @if ($order->status === OrderStatus::PENDING)
            @if ($order->payments->contains(fn ($p) => $p->status === \App\Enums\PaymentStatus::PROCESSING))
                <div class="notice" role="status">Estamos verificando tu pago. Te avisaremos por correo cuando se confirme.</div>
            @else
                <div class="notice pay-box">
                    <span>Tu pedido está pendiente de pago: <b class="num">{{ Money::format($order->total) }}</b>.</span>
                    <div class="pay-actions">
                        @if ($canPayWithCard)
                            <a class="btn btn-primary" href="{{ $order->cardUrl() }}">Pagar con tarjeta</a>
                        @endif
                        <form method="post" action="{{ $order->payUrl() }}" style="display: inline">
                            @csrf
                            <button type="submit" class="btn {{ $canPayWithCard ? '' : 'btn-primary' }}">Otros medios de pago (Mercado Pago)</button>
                        </form>
                    </div>
                    @if (config('shop.payment_gateway') === 'fake')
                        <small class="muted" style="flex-basis: 100%">Modo de prueba: los pagos no son reales.</small>
                    @endif
                </div>
            @endif
        @elseif ($order->status === OrderStatus::CANCELLED && $order->refund_amount !== null)
            <div class="notice" role="status">Pedido cancelado. Te devolveremos {{ Money::format($order->refund_amount) }}.</div>
        @endif

        <div class="cart-layout">
            <div style="display: grid; gap: 28px">
                <section class="card" style="padding: 22px" aria-labelledby="items-title">
                    <h2 id="items-title" style="font-size: 1.3rem; font-weight: 700; margin-bottom: .8rem">Productos</h2>
                    <ul class="mini-lines">
                        @foreach ($order->items as $item)
                            <li>
                                <span>{{ $item->quantity }} × {{ $item->product_snapshot['name'] ?? 'Producto' }}
                                    @if (! empty($item->product_snapshot['author'])) <span class="muted">· {{ $item->product_snapshot['author'] }}</span> @endif
                                </span>
                                <b class="num">{{ Money::format($item->lineTotal()) }}</b>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="card" style="padding: 22px" aria-labelledby="history-title">
                    <h2 id="history-title" style="font-size: 1.3rem; font-weight: 700; margin-bottom: .8rem">Seguimiento</h2>
                    <ul class="history">
                        @foreach ($order->statusHistories->reverse() as $entry)
                            <li>
                                <span><b>{{ $entry->to_status->label() }}</b>@if ($entry->note) <span class="muted">· {{ $entry->note }}</span>@endif</span>
                                <span class="muted num">{{ $entry->created_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>

            <aside style="display: grid; gap: 22px; align-self: start">
                <section class="card" style="padding: 22px; display: grid; gap: 10px" aria-labelledby="total-title">
                    <h2 id="total-title" style="font-size: 1.3rem; font-weight: 700">Resumen</h2>
                    <dl class="totals">
                        <div><dt>Subtotal</dt><dd class="num">{{ Money::format($order->subtotal) }}</dd></div>
                        <div><dt>Envío</dt><dd class="num">{{ (float) $order->shipping_cost > 0 ? Money::format($order->shipping_cost) : 'Gratis' }}</dd></div>
                        <div class="total"><dt>Total</dt><dd class="num">{{ Money::format($order->total) }}</dd></div>
                    </dl>
                    <p class="muted" style="font-size: 13px">IGV incluido ({{ Money::format($order->tax) }}).</p>
                </section>

                <section class="card" style="padding: 22px" aria-labelledby="address-title">
                    <h2 id="address-title" style="font-size: 1.3rem; font-weight: 700; margin-bottom: .6rem">Entrega</h2>
                    <address style="font-style: normal; line-height: 1.6">
                        <b>{{ $address['recipient_name'] ?? '' }}</b><br>
                        {{ $address['line1'] ?? '' }}@if (! empty($address['line2'])), {{ $address['line2'] }}@endif<br>
                        {{ $address['city'] ?? '' }}@if (! empty($address['state'])), {{ $address['state'] }}@endif<br>
                        <span class="muted">Cel. {{ $address['phone'] ?? '' }}</span>
                    </address>
                </section>
            </aside>
        </div>
    </div>
</x-shop-layout>
