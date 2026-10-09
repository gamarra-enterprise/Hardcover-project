<div>
    @if ($this->product)
        @php
            $p = $this->product;
            $book = $p->bookDetail;
            $discount = $p->sale_price ? (int) round((1 - $p->sale_price / $p->price) * 100) : 0;
            $soldOut = $p->status === \App\Enums\ProductStatus::OUT_OF_STOCK;
            $low = ! $soldOut && $p->stock <= 5;
        @endphp
        <div class="qv-ov enter" wire:click="close"></div>
        <div class="qv enter" role="dialog" aria-modal="true" aria-label="{{ $p->name }}" x-data x-trap.noscroll="true" x-on:keydown.escape.window="$wire.close()">
            <button type="button" class="btn btn-sm qv-x" style="padding: .5rem" wire:click="close" aria-label="Cerrar"><x-shop.icon name="x" size="18" /></button>
            <div class="qv-grid">
                <div style="position: relative; max-width: 400px; width: 100%; margin-inline: auto">
                    @if ($discount && ! $soldOut)<span class="badge" style="font-size: 13px">-{{ $discount }}%</span>@endif
                    <x-shop.cover :product="$p" />
                </div>
                <div style="min-width: 0">
                    <div class="label" style="color: var(--accent-deep)">{{ $p->categories->first()?->name ?? $p->type->label() }}</div>
                    <h2 style="font-size: clamp(1.7rem, 3.4vw, 2.4rem); font-weight: 800; letter-spacing: -.03em; margin-top: .4rem; padding-right: 2.5rem">{{ $p->name }}</h2>
                    @if ($book)<p style="font-size: 1.05rem; margin-top: .3rem">de <b>{{ $book->author }}</b></p>@endif
                    <div style="display: flex; gap: .7rem; align-items: baseline; margin-top: .9rem; flex-wrap: wrap">
                        <b class="num" style="font-size: 1.9rem; font-family: var(--f-display)">S/ {{ number_format($p->currentPrice(), 2) }}</b>
                        @if ($p->sale_price)<span class="old num" style="font-size: 1.05rem">S/ {{ number_format($p->price, 2) }}</span>@endif
                        <span class="muted" style="font-size: 13px">IGV incluido</span>
                    </div>
                    <div class="stock {{ $soldOut ? 'is-out' : ($low ? 'is-low' : '') }}">
                        <span class="dot-state"></span>
                        @if ($soldOut) Sin stock @elseif ($low) {{ $p->stock === 1 ? 'Última unidad' : 'Últimas '.$p->stock.' unidades' }} @else Disponible @endif
                    </div>
                    @if ($p->description)<p class="muted" style="margin-top: .8rem; line-height: 1.6">{{ \Illuminate\Support\Str::limit($p->description, 320) }}</p>@endif
                    <div style="margin-top: 1.2rem">
                        <livewire:cart.add-to-cart :product="$p" :key="'qv-add-'.$p->id" />
                    </div>
                    <a class="tlink" style="margin-top: 1rem" href="{{ route('products.show', $p) }}">Ver ficha completa →</a>
                </div>
            </div>
        </div>
    @endif
</div>
