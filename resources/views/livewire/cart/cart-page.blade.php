<div class="wrap" style="padding-block: 28px 0">
    <nav class="muted" style="font-size: 13px" aria-label="Ruta"><a href="{{ route('home') }}">Inicio</a> / Carrito</nav>
    <h1 style="font-size: clamp(2.2rem, 5vw, 3.4rem); font-weight: 800; letter-spacing: -.035em; margin-top: .3rem">Tu carrito</h1>

    @if (session('notice'))
        <div class="notice" role="alert">{{ session('notice') }}</div>
    @endif

    @if ($this->summary->isEmpty())
        <div class="card bg-surface" style="padding: 40px; text-align: center; margin-top: 24px">
            <b>Tu carrito está vacío.</b>
            <p class="muted">Guarda aquí los libros que quieras llevarte.</p>
            <a class="btn btn-dark" href="{{ route('catalog') }}" style="margin-top: .8rem">Ver el catálogo</a>
        </div>
    @else
        <div class="cart-layout">
            <div>
                <x-shop.cart-lines :summary="$this->summary" />
                <a class="tlink" href="{{ route('catalog') }}" style="display: inline-block; margin-top: 1rem">← Seguir comprando</a>
            </div>

            <aside class="card" style="padding: 22px; display: grid; gap: 14px; align-self: start">
                <x-shop.cart-summary :summary="$this->summary" />
                @if ($this->summary->hasIssues())
                    <button type="button" class="btn btn-primary" disabled style="opacity: .5; cursor: not-allowed">Finalizar compra</button>
                    <p class="muted" style="font-size: 13px">Resuelve los avisos de tu carrito para continuar.</p>
                @else
                    <a class="btn btn-primary" href="{{ route('checkout') }}">Finalizar compra</a>
                @endif
                <p class="muted" style="font-size: 13px; border-top: 1px solid var(--line); padding-top: 12px">
                    Guardar un libro en el carrito no lo reserva: el stock se descuenta cuando se confirma el pago.
                </p>
            </aside>
        </div>
    @endif
</div>
