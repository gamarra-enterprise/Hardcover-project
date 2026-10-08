@use('App\Support\Money')

<x-shop-layout title="Preguntas frecuentes">
    <div class="wrap" style="padding-block: 28px 0; max-width: 760px">
        <nav class="muted" style="font-size: 13px" aria-label="Ruta"><a href="{{ route('home') }}">Inicio</a> / Preguntas frecuentes</nav>
        <h1 style="font-size: clamp(2.2rem, 5vw, 3.4rem); font-weight: 800; letter-spacing: -.035em; margin-top: .3rem">Preguntas frecuentes</h1>

        <div class="faq">
            <details open>
                <summary>¿A qué lugares envían?</summary>
                <div class="prose-block">
                    @if ($served->isNotEmpty())
                        <p>Por ahora enviamos a {{ $served->join(', ', ' y ') }}. Los envíos a otras regiones estarán disponibles próximamente.</p>
                    @else
                        <p>Los envíos estarán disponibles próximamente.</p>
                    @endif
                </div>
            </details>

            <details>
                <summary>¿Cuánto cuesta el envío?</summary>
                <div class="prose-block">
                    @if ($zones->isNotEmpty())
                        <p>El costo depende del distrito de entrega:</p>
                        <ul>
                            @foreach ($zones as $zone)
                                <li>{{ $zone->name }}: desde <b class="num">{{ Money::format($zone->min_cost) }}</b>.</li>
                            @endforeach
                        </ul>
                        <p>El costo exacto se muestra al elegir tu distrito, antes de pagar.</p>
                    @endif
                    <p>Las compras desde <b class="num">{{ Money::format($freeFrom) }}</b> tienen envío gratis.</p>
                </div>
            </details>

            <details>
                <summary>¿Cómo sé si hay stock?</summary>
                <div class="prose-block">
                    <p>Cada ficha muestra la disponibilidad real. Guardar un libro en el carrito no lo reserva: el stock se descuenta cuando se confirma tu pago. Si quedan pocas unidades, conviene pagar pronto.</p>
                </div>
            </details>

            <details>
                <summary>¿Cómo sigo mi pedido?</summary>
                <div class="prose-block">
                    <p>Te enviamos un correo cada vez que tu pedido cambia de estado: confirmado, en preparación, enviado y entregado. Guarda el código de seguimiento que recibes al comprar.</p>
                </div>
            </details>

            <details>
                <summary>¿Puedo cancelar mi pedido?</summary>
                <div class="prose-block">
                    <p>Sí, mientras tu pedido no haya sido enviado. Si ya pagaste, te devolvemos todo lo que pagaste. Una vez enviado ya no se puede cancelar.</p>
                </div>
            </details>
        </div>
    </div>
</x-shop-layout>
