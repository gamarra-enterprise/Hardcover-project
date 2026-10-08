<span class="cart-count-wrap">
    @if ($count > 0)
        <b class="cart-count num" aria-label="{{ $count }} {{ $count === 1 ? 'producto' : 'productos' }} en el carrito">{{ $count }}</b>
    @endif
</span>
