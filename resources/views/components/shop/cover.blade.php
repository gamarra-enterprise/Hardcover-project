@props(['product'])

{{-- Real cover when the product has an image, typographic placeholder otherwise. --}}
<div class="cw">
    <div class="cover {{ $product->imageUrl() ? '' : 'cover-ph' }}">
        @if ($product->imageUrl())
            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy">
        @else
            <span class="ca">{{ $product->bookDetail?->author ?? $product->categories->first()?->name }}</span>
            <span class="ct">{{ $product->name }}</span>
            <span class="cp">{{ $product->bookDetail?->publisher }}</span>
        @endif
    </div>
</div>
