<div class="drawer-in">
    <div class="drawer-head">
        <h2 style="font-size: 1.35rem; font-weight: 700">Tu carrito <span class="muted num" style="font-size: 1rem">({{ $this->summary->count }})</span></h2>
        <button type="button" class="btn btn-sm" x-on:click="open = false" aria-label="Cerrar carrito">✕</button>
    </div>

    @if ($this->summary->isEmpty())
        <div class="drawer-empty">
            <p><b>Tu carrito está vacío.</b></p>
            <p class="muted">Guarda aquí los libros que quieras llevarte.</p>
            <a class="btn btn-dark" href="{{ route('catalog') }}" style="margin-top: .8rem">Ver el catálogo</a>
        </div>
    @else
        <div class="drawer-body"><x-shop.cart-lines :summary="$this->summary" /></div>
        <div class="drawer-foot">
            <x-shop.cart-summary :summary="$this->summary" />
            @unless ($this->summary->hasIssues())
                <a class="btn btn-primary" href="{{ route('checkout') }}" style="width: 100%">Finalizar compra</a>
            @endunless
            <a class="btn" href="{{ route('cart') }}" style="width: 100%">Ver carrito completo</a>
        </div>
    @endif
</div>
