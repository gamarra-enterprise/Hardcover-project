@props(['product', 'index' => 0, 'rank' => null])

@php
    $discount = $product->sale_price ? (int) round((1 - $product->sale_price / $product->price) * 100) : 0;
    $soldOut = $product->status === \App\Enums\ProductStatus::OUT_OF_STOCK;
@endphp

<div class="pcard" style="--i: {{ $index }}">
    @if ($rank)<div class="rank num" aria-hidden="true">{{ str_pad($rank, 2, '0', STR_PAD_LEFT) }}</div>@endif
    <div style="position: relative">
        @if ($soldOut)
            <span class="badge out">Sin stock</span>
        @elseif ($discount)
            <span class="badge">-{{ $discount }}%</span>
        @endif
        <a href="{{ route('products.show', $product) }}" tabindex="-1" aria-hidden="true"><x-shop.cover :product="$product" /></a>
        <button type="button" class="qv-hint" x-data x-on:click="$dispatch('quick-view', { id: {{ $product->id }} })" aria-label="Vista rápida: {{ $product->name }}">Vista rápida</button>
    </div>
    <a href="{{ route('products.show', $product) }}" style="display: flex; flex-direction: column; gap: .55rem; text-decoration: none">
        <div class="label" style="margin-top: .25rem">{{ $product->categories->first()?->name ?? $product->type->label() }}</div>
        <div class="ptitle" style="font-weight: 600; line-height: 1.25">{{ $product->name }}</div>
        @if ($product->bookDetail)
            <div class="muted" style="font-size: 14.5px">{{ $product->bookDetail->author }}</div>
        @endif
        @if ($product->description)
            <p class="pdesc">{{ $product->description }}</p>
        @endif
        <div style="display: flex; gap: .5rem; align-items: baseline">
            <b class="num">S/ {{ number_format($product->currentPrice(), 2) }}</b>
            @if ($product->sale_price)
                <span class="old num">S/ {{ number_format($product->price, 2) }}</span>
            @endif
        </div>
    </a>
</div>
