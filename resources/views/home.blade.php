<x-shop-layout>
    <section class="cover-hero" id="cover" aria-label="Hardcover Bookery">
        @foreach ($floating as $i => $product)
            @php
                [$position, $speed, $rotation, $delay, $hideOnPhone] = [
                    ['left:5%;top:12%', '.55', '-8deg', 350, ''],
                    ['right:6%;top:9%', '.32', '7deg', 520, ''],
                    ['left:13%;bottom:11%', '.2', '5deg', 680, 'hm'],
                    ['right:11%;bottom:13%', '.48', '-6deg', 820, 'hm'],
                ][$i];
            @endphp
            <div class="cv-c {{ $hideOnPhone }}" style="{{ $position }}; --d: {{ $delay }}">
                <div class="cv-i" style="--s: {{ $speed }}; --r: {{ $rotation }}">
                    <x-shop.cover :product="$product" />
                </div>
            </div>
        @endforeach

        <div class="cv-in">
            <div class="cv-eyebrow">Librería independiente · Lima</div>
            <h1 class="cv-word" aria-label="hardcover">
                @foreach (str_split('hardcover') as $i => $letter)
                    <span class="l"><i style="--i: {{ $i }}">{{ $letter }}</i></span>
                @endforeach
            </h1>
            <div class="cv-sub">book<b>ery</b></div>
            <div class="cv-actions">
                <a class="btn btn-dark" href="#novedades">Entrar a la librería →</a>
            </div>
        </div>
        <div class="cv-hint"><span>Desliza</span><i>↓</i></div>
    </section>

    <section class="wrap" id="novedades" style="padding-block: 64px 0">
        <div class="sec-head">
            <div style="display: flex; align-items: center; gap: .8rem">
                <span style="color: var(--accent-deep); display: flex"><x-shop.icon name="star" size="22" /></span>
                <div>
                    <h2 style="font-size: clamp(1.8rem, 3.4vw, 2.5rem); font-weight: 700">Novedades</h2>
                    <p class="muted" style="margin-top: .2rem; font-size: 14.5px">Lo último que llegó a la librería.</p>
                </div>
            </div>
            <a class="tlink" href="{{ route('catalog') }}">Ver todo el catálogo →</a>
        </div>

        @if ($newest->isEmpty())
            <p class="muted">Pronto tendremos libros nuevos.</p>
        @else
            <div class="pgrid">
                @foreach ($newest as $i => $product)
                    <x-shop.product-card :product="$product" :index="$i" />
                @endforeach
            </div>
        @endif
    </section>
</x-shop-layout>
