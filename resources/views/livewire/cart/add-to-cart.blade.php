<div class="buy">
    @if ($this->available < 1)
        <button type="button" class="btn" disabled style="opacity: .55; cursor: not-allowed">{{ $this->unavailableLabel() }}</button>
    @elseif ($this->room < 1)
        <p class="muted" style="margin-bottom: .6rem">Ya tienes en tu carrito todas las unidades disponibles.</p>
        <button type="button" class="btn btn-dark" x-on:click="$dispatch('cart-open')">Ver mi carrito</button>
    @else
        <div style="display: flex; gap: .7rem; flex-wrap: wrap; align-items: center">
            <div class="qty" role="group" aria-label="Cantidad">
                <button type="button" wire:click="decrement" aria-label="Menos" @disabled($quantity <= 1)>−</button>
                <span class="num" aria-live="polite">{{ $quantity }}</span>
                <button type="button" wire:click="increment" aria-label="Más" @disabled($quantity >= $this->room)>+</button>
            </div>
            <button type="button" class="btn btn-primary" style="flex: 1; min-width: 180px" wire:click="add" wire:loading.attr="disabled">Añadir al carrito</button>
        </div>
        @if ($this->inCart > 0)
            <p class="muted" style="font-size: 13px; margin-top: .5rem">Ya tienes {{ $this->inCart }} en tu carrito.</p>
        @endif
    @endif

    @if ($message)
        <p role="status" style="margin-top: .6rem; font-weight: 600; color: {{ $isWarning ? 'var(--warn, #9a6200)' : 'var(--ok)' }}">{{ $message }}</p>
    @endif
</div>
