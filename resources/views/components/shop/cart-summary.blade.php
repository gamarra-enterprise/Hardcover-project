@props(['summary'])

@use('App\Support\Money')

<div class="cart-summary">
    <div style="display: flex; justify-content: space-between; align-items: baseline">
        <span>Subtotal</span>
        <b class="num" style="font-size: 1.25rem">{{ Money::format($summary->subtotal) }}</b>
    </div>
    <p class="muted" style="font-size: 13px">IGV incluido. El envío se calcula al pagar.</p>

    @if ($summary->hasIssues())
        <p class="muted" style="font-size: 13px">El subtotal no incluye los productos con aviso.</p>
    @endif

    <div class="free-bar" aria-hidden="true"><i style="width: {{ $summary->freeShippingProgress() }}%"></i></div>
    <p style="font-size: 13.5px">
        @if ((float) $summary->missingForFreeShipping() > 0)
            Te faltan <b class="num">{{ Money::format($summary->missingForFreeShipping()) }}</b> para el envío gratis.
        @else
            ¡Tienes envío gratis!
        @endif
    </p>
</div>
