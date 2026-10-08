@use('App\Support\Money')

@php
    $isStandIn = $config['provider'] !== 'mercadopago';
    $address = $order->shipping_address;
@endphp

<x-shop-layout title="Pagar con tarjeta">
    @push('scripts')
        @unless ($isStandIn)
            <script src="https://sdk.mercadopago.com/js/v2"></script>
        @endunless
        @vite('resources/js/card-form.js')
    @endpush

    <div class="wrap" style="padding-block: 28px 0">
        <nav class="muted" style="font-size: 13px" aria-label="Ruta"><a href="{{ route('home') }}">Inicio</a> / <a href="{{ $order->signedUrl() }}">Pedido</a> / Pagar con tarjeta</nav>
        <h1 style="font-size: clamp(2.2rem, 5vw, 3.4rem); font-weight: 800; letter-spacing: -.035em; margin-top: .3rem">Pagar con tarjeta</h1>

        <div class="cart-layout">
            <form id="card-form" class="card" style="padding: 22px; display: grid; gap: 16px" novalidate
                  data-provider="{{ $config['provider'] }}" data-public-key="{{ $config['public_key'] }}" data-endpoint="{{ $order->cardPayUrl() }}">

                <h2 style="font-size: 1.3rem; font-weight: 700">Datos de la tarjeta</h2>

                @if ($isStandIn)
                    <div class="notice" role="note" style="margin-top: 0">
                        Modo de prueba: no se cobra nada y los datos de la tarjeta no salen de tu navegador.
                        Usa <b class="num">4111 1111 1111 1111</b> para que el pago se apruebe o <b class="num">4000 0000 0000 0002</b> para que se rechace, con cualquier vencimiento futuro y un código de 3 dígitos.
                    </div>
                @endif

                <noscript><p class="field-error">Necesitas activar JavaScript para pagar con tarjeta.</p></noscript>

                {{-- The card fields have no "name": nothing typed in them can be sent to this server by the form. --}}
                <div class="field">
                    <label for="{{ $isStandIn ? 'card-number-input' : 'card-number' }}">Número de la tarjeta</label>
                    @if ($isStandIn)
                        <div style="position: relative">
                            <input id="card-number-input" class="input" type="text" inputmode="numeric" autocomplete="cc-number" placeholder="1234 1234 1234 1234" maxlength="23">
                            <span id="card-brand" class="brand-tag" aria-live="polite"></span>
                        </div>
                    @else
                        <div id="card-number" class="secure-field"></div>
                    @endif
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="{{ $isStandIn ? 'card-expiry-input' : 'card-expiry' }}">Vencimiento</label>
                        @if ($isStandIn)
                            <input id="card-expiry-input" class="input" type="text" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/AA" maxlength="5">
                        @else
                            <div id="card-expiry" class="secure-field"></div>
                        @endif
                    </div>
                    <div class="field">
                        <label for="{{ $isStandIn ? 'card-cvv-input' : 'card-cvv' }}">Código de seguridad (CVV)</label>
                        @if ($isStandIn)
                            <input id="card-cvv-input" class="input" type="password" inputmode="numeric" autocomplete="cc-csc" placeholder="123" maxlength="3">
                        @else
                            <div id="card-cvv" class="secure-field"></div>
                        @endif
                    </div>
                </div>

                <div class="field">
                    <label for="card-holder">Nombres y apellidos del titular</label>
                    <input id="card-holder" class="input" type="text" autocomplete="cc-name" value="{{ $address['recipient_name'] ?? '' }}" required>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="card-doc-type">Documento</label>
                        <select id="card-doc-type" class="select" style="border-radius: 14px; width: 100%">
                            <option value="DNI">DNI</option>
                            <option value="CE">Carné de extranjería</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="card-doc-number">Número de documento</label>
                        <input id="card-doc-number" class="input" type="text" inputmode="numeric" autocomplete="off" required>
                    </div>
                </div>

                <div class="field">
                    <label for="card-email">Correo electrónico</label>
                    <input id="card-email" class="input" type="email" autocomplete="email" value="{{ $order->contactEmail() }}" required>
                </div>

                <p id="card-error" class="field-error" role="alert" hidden></p>

                <button id="card-submit" type="submit" class="btn btn-primary">Pagar {{ Money::format($order->total) }}</button>

                <p class="muted" style="font-size: 13px">
                    @if ($isStandIn)
                        Modo de prueba: en producción estos campos los sirve Mercado Pago.
                    @else
                        Los datos de tu tarjeta los recibe directamente Mercado Pago, cifrados. Nuestra tienda nunca los ve ni los guarda.
                    @endif
                </p>
            </form>

            <aside class="card" style="padding: 22px; display: grid; gap: 14px; align-self: start">
                <h2 style="font-size: 1.3rem; font-weight: 700">Tu pedido <span class="num">{{ $order->tracking_code }}</span></h2>
                <ul class="mini-lines">
                    @foreach ($order->items as $item)
                        <li><span>{{ $item->quantity }} × {{ $item->product_snapshot['name'] ?? 'Producto' }}</span><b class="num">{{ Money::format($item->lineTotal()) }}</b></li>
                    @endforeach
                </ul>
                <dl class="totals">
                    <div><dt>Subtotal</dt><dd class="num">{{ Money::format($order->subtotal) }}</dd></div>
                    <div><dt>Envío</dt><dd class="num">{{ (float) $order->shipping_cost > 0 ? Money::format($order->shipping_cost) : 'Gratis' }}</dd></div>
                    <div class="total"><dt>Total</dt><dd class="num">{{ Money::format($order->total) }}</dd></div>
                </dl>
                <a class="tlink" href="{{ $order->signedUrl() }}">← Volver al pedido</a>
            </aside>
        </div>
    </div>
</x-shop-layout>
