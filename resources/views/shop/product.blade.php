@php
    $book = $product->bookDetail;
    $discount = $product->sale_price ? (int) round((1 - $product->sale_price / $product->price) * 100) : 0;
    $soldOut = $product->status === \App\Enums\ProductStatus::OUT_OF_STOCK;
    $low = ! $soldOut && $product->stock <= 5;
    $category = $product->categories->first();
    $specs = array_filter([
        'Autor' => $book?->author,
        'Editorial' => $book?->publisher,
        'Año' => $book?->published_year,
        'ISBN' => $book?->isbn_13 ?? $book?->isbn_10,
        'Páginas' => $book?->pages,
        'Formato' => $book?->format,
        'Tamaño' => $product->height_mm && $product->width_mm
            ? rtrim(rtrim(number_format($product->height_mm / 10, 1), '0'), '.').' × '.rtrim(rtrim(number_format($product->width_mm / 10, 1), '0'), '.').' cm'
            : null,
    ]);
    // Genres that are also shop categories link to the filtered catalog.
    $genreLinks = $product->categories;
@endphp

<x-shop-layout :title="$product->name">
    <div class="wrap" style="padding-block: 24px 0">
        <nav class="muted" style="font-size: 13px" aria-label="Ruta">
            <a href="{{ route('home') }}">Inicio</a> / <a href="{{ route('catalog') }}">Catálogo</a>
            @if ($category) / <a href="{{ route('catalog', ['genero' => $category->slug]) }}">{{ $category->name }}</a> @endif
            / {{ $product->name }}
        </nav>

        <div class="detail">
            <div class="dcover">
                @if ($discount && ! $soldOut)
                    <span class="badge" style="font-size: 13px">-{{ $discount }}%</span>
                @endif
                <x-shop.cover :product="$product" />
            </div>

            <div style="min-width: 0">
                @if ($category)
                    <div class="label" style="color: var(--accent-deep)">{{ $category->name }}</div>
                @endif
                <h1 style="font-size: clamp(1.9rem, 4vw, 2.8rem); font-weight: 600; margin-top: .4rem">{{ $product->name }}</h1>
                @if ($book)
                    <p style="font-size: 1.1rem; margin-top: .35rem">de <b>{{ $book->author }}</b></p>
                @endif

                <div style="display: flex; gap: .7rem; align-items: baseline; margin-top: 1.1rem; flex-wrap: wrap">
                    <b class="num" style="font-size: 2rem; font-family: var(--f-display)">S/ {{ number_format($product->currentPrice(), 2) }}</b>
                    @if ($product->sale_price)
                        <span class="old num" style="font-size: 1.05rem">S/ {{ number_format($product->price, 2) }}</span>
                    @endif
                    <span class="muted" style="font-size: 13px">IGV incluido</span>
                </div>

                <div class="stock {{ $soldOut ? 'is-out' : ($low ? 'is-low' : '') }}">
                    <span class="dot-state"></span>
                    @if ($soldOut) Sin stock
                    @elseif ($low) {{ $product->stock === 1 ? 'Última unidad' : 'Últimas '.$product->stock.' unidades' }}
                    @else Disponible
                    @endif
                </div>

                @if ($product->description)
                    <div class="prose-block">{!! nl2br(e($product->description)) !!}</div>
                @endif

                {{-- The cart arrives in the next step; until then the purchase button stays disabled. --}}
                <div style="margin-top: 1.4rem">
                    <button type="button" class="btn btn-primary" disabled style="opacity: .5; cursor: not-allowed">Añadir al carrito</button>
                    <p class="muted" style="font-size: 13px; margin-top: .5rem">La compra en línea estará disponible muy pronto.</p>
                </div>

                @if ($specs)
                    <section style="margin-top: 2rem" aria-labelledby="specs-title">
                        <h2 id="specs-title" class="label" style="margin-bottom: .4rem">Ficha técnica</h2>
                        <table class="specs">
                            @foreach ($specs as $name => $value)
                                <tr><th>{{ $name }}</th><td>{{ $value }}</td></tr>
                            @endforeach
                        </table>
                    </section>
                @endif

                @if ($genreLinks->isNotEmpty())
                    <div style="margin-top: 1.4rem; display: flex; gap: .4rem; flex-wrap: wrap">
                        @foreach ($genreLinks as $genre)
                            <a class="chip" href="{{ route('catalog', ['genero' => $genre->slug]) }}">{{ $genre->name }}</a>
                        @endforeach
                    </div>
                @endif

                @if ($book?->author_bio)
                    <section style="margin-top: 2rem" aria-labelledby="author-title">
                        <h2 id="author-title" class="label" style="margin-bottom: .4rem">Sobre el autor</h2>
                        <div class="prose-block" style="margin-top: 0">{!! nl2br(e($book->author_bio)) !!}</div>
                    </section>
                @endif
            </div>
        </div>
    </div>

    @if ($related->isNotEmpty())
        <section class="wrap" style="padding-block: 64px 0">
            <div class="sec-head"><h2 style="font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700">También te puede gustar</h2></div>
            <div class="pgrid">
                @foreach ($related as $i => $item)
                    <x-shop.product-card :product="$item" :index="$i" />
                @endforeach
            </div>
        </section>
    @endif
</x-shop-layout>
