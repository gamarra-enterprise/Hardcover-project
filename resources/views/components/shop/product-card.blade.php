@props(['product', 'index' => 0])

@php
    $discount = $product->sale_price ? (int) round((1 - $product->sale_price / $product->price) * 100) : 0;
@endphp

<a class="pcard" href="{{ route('products.show', $product) }}" style="--i: {{ $index }}">
    <div style="position: relative">
        @if ($product->status === \App\Enums\ProductStatus::OUT_OF_STOCK)
            <span class="badge out">Sin stock</span>
        @elseif ($discount)
            <span class="badge">-{{ $discount }}%</span>
        @endif
        <x-shop.cover :product="$product" />
    </div>
    <div class="label" style="margin-top: .25rem">{{ $product->categories->first()?->name }}</div>
    <div class="ptitle" style="font-weight: 600; line-height: 1.25">{{ $product->name }}</div>
    @if ($product->bookDetail)
        <div class="muted" style="font-size: 14.5px">{{ $product->bookDetail->author }}</div>
    @endif
    <div style="display: flex; gap: .5rem; align-items: baseline">
        <b class="num">S/ {{ number_format($product->currentPrice(), 2) }}</b>
        @if ($product->sale_price)
            <span class="old num">S/ {{ number_format($product->price, 2) }}</span>
        @endif
    </div>
</a>
