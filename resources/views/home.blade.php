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
                <a class="btn btn-dark" href="#inicio-tienda">Entrar a la librería →</a>
                <a class="btn" href="{{ route('collection.novedades') }}">Novedades</a>
            </div>
        </div>
        <div class="cv-hint"><span>Desliza</span><i>↓</i></div>
    </section>

    <div id="inicio-tienda"></div>

    @if ($featured->isNotEmpty())
        <div class="hero-band" x-data="{ i: 0, words: ['leer hoy', 'regalar', 'perderte', 'aprender', 'soñar'], n: {{ $featured->count() }} }">
            <div class="wrap" style="padding-block: 56px 68px; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 40px; align-items: center">
                <div>
                    <div class="label" style="color: var(--accent-deep)">Librería independiente · Lima</div>
                    <h2 class="hero-title" style="margin-top: .75rem">Libros para <span class="mark"><span class="rot" x-text="words[i % words.length]" aria-live="off">leer hoy</span></span>,<br>y objetos para leerlos mejor.</h2>
                    <p class="muted" style="font-size: 1.1rem; margin-top: 1rem; max-width: 46ch">Narrativa, poesía, ensayo y papelería. Elige, paga en línea y te lo enviamos con seguimiento.</p>
                    <div style="display: flex; gap: .6rem; margin-top: 1.5rem; flex-wrap: wrap">
                        <a class="btn btn-primary" href="{{ route('catalog') }}">Ver catálogo →</a>
                        <a class="btn" href="{{ route('collection.novedades') }}">Novedades →</a>
                    </div>
                </div>
                <div>
                    @foreach ($featured as $k => $p)
                        <div x-show="i % n === {{ $k }}" @if ($k > 0) x-cloak @endif x-transition.opacity.duration.400ms style="display: grid; grid-template-columns: minmax(150px, 300px) 1fr; gap: 28px; align-items: center">
                            <a href="{{ route('products.show', $p) }}" aria-label="{{ $p->name }}"><x-shop.cover :product="$p" /></a>
                            <div>
                                <div class="label">Destacado de la semana</div>
                                <h3 style="font-size: 1.7rem; font-weight: 700; margin-top: .35rem">{{ $p->name }}</h3>
                                <p class="muted">{{ $p->bookDetail?->author ?? $p->type->label() }}</p>
                                @if ($p->description)<p style="margin-top: .7rem; line-height: 1.6">{{ \Illuminate\Support\Str::limit($p->description, 200) }}</p>@endif
                                <div style="display: flex; gap: .5rem; align-items: baseline; margin-top: .7rem">
                                    <b class="num" style="font-size: 1.3rem">S/ {{ number_format($p->currentPrice(), 2) }}</b>
                                    @if ($p->sale_price)<span class="old num">S/ {{ number_format($p->price, 2) }}</span>@endif
                                </div>
                                <div style="margin-top: 1rem"><a class="btn btn-dark" href="{{ route('products.show', $p) }}">Ver el libro</a></div>
                            </div>
                        </div>
                    @endforeach
                    @if ($featured->count() > 1)
                        <div style="display: flex; gap: .4rem; margin-top: 1.2rem" role="group" aria-label="Destacados">
                            @foreach ($featured as $k => $p)
                                <button type="button" class="dot" :class="{ on: i % n === {{ $k }} }" x-on:click="i = {{ $k }}" aria-label="Ver destacado {{ $k + 1 }}"></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($genres)
        <div class="wrap" style="padding-block: 28px 0">
            <x-shop.scroller class="chips-row" label="géneros">
                @foreach ($genres as $g)
                    <a class="chip" href="{{ route('catalog', ['genero' => $g['slug']]) }}">{{ $g['name'] }}<span class="cnt">{{ $g['count'] }}</span></a>
                @endforeach
            </x-shop.scroller>
        </div>
    @endif

    @if ($moods->count() >= 2)
        <x-shop.section title="¿Qué te apetece leer hoy?" subtitle="Pasa el mouse por un panel, o tócalo en el celular." icon="heart">
            <div class="xp" x-data="{ open: 0 }">
                @foreach ($moods as $k => $m)
                    <a class="xp-p" :class="{ open: open === {{ $k }} }" href="{{ route('catalog', ['genero' => $m[1]]) }}"
                       x-on:click="if (! matchMedia('(min-width: 701px)').matches && open !== {{ $k }}) { $event.preventDefault(); open = {{ $k }} }"
                       style="--pbg: {{ $m[3] }}; --pfg: {{ $m[4] }}; --i: {{ $k }}; text-decoration: none">
                        <span class="xp-num">0{{ $k + 1 }}</span><span class="xp-t">{{ $m[0] }}</span>
                        <span class="xp-more">
                            <h3>{{ $m[0] }}</h3><p>{{ $m[2] }}</p>
                            <span class="xp-covers">@foreach ($m['covers'] as $c)<x-shop.cover :product="$c" />@endforeach</span>
                            <em>Ver {{ mb_strtolower($m['name']) }} →</em>
                        </span>
                    </a>
                @endforeach
            </div>
        </x-shop.section>
    @endif

    <x-shop.section id="novedades" title="Novedades" subtitle="Lo último que llegó a la librería." icon="star">
        <x-slot:link>
            <div class="sec-actions"><a class="tlink" href="{{ route('collection.novedades') }}">Ver todas →</a><x-shop.arrows target="car-new" /></div>
        </x-slot:link>
        @if ($newest->isEmpty())
            <p class="muted">Pronto tendremos libros nuevos.</p>
        @else
            <div class="carousel" id="car-new" tabindex="0" role="region" aria-label="Novedades">
                @foreach ($newest as $i => $product)
                    <x-shop.product-card :product="$product" :index="$i" />
                @endforeach
            </div>
        @endif
    </x-shop.section>

    @if ($best->isNotEmpty())
        <x-shop.section title="Más vendidos" subtitle="Lo que más se lleva este mes." icon="heart">
            <x-slot:link>
                <div class="sec-actions"><a class="tlink" href="{{ route('collection.masvendidos') }}">Ver todos →</a><x-shop.arrows target="car-best" /></div>
            </x-slot:link>
            <div class="carousel" id="car-best" tabindex="0" role="region" aria-label="Más vendidos">
                @foreach ($best as $i => $product)
                    <x-shop.product-card :product="$product" :index="$i" :rank="$i + 1" />
                @endforeach
            </div>
        </x-shop.section>
    @endif

    <section class="wrap rv" style="padding-block: 56px 0">
        <div class="band" x-data="{ min: 15 }">
            <div>
                <div class="label" style="color: #A8EC7A">Un hábito pequeño</div>
                <h2 style="font-size: clamp(1.8rem, 4vw, 2.7rem); font-weight: 600; margin-top: .6rem">Quince minutos al día pueden ser más de una docena de libros al año.</h2>
                <div style="margin-top: 1.4rem">
                    @foreach ([['Deja el libro a la vista', 'En la mesa de noche, no en la estantería.'], ['Lee diez páginas antes de dormir', 'Sin pantalla, sin prisa.'], ['Empieza por uno corto', 'Terminar uno anima a empezar el siguiente.']] as $k => [$t, $d])
                        <div class="tip"><i>{{ $k + 1 }}</i><div><b style="color: #fff">{{ $t }}</b><br><span style="font-size: 14px; opacity: .8">{{ $d }}</span></div></div>
                    @endforeach
                </div>
            </div>
            <div class="pace-box">
                <div class="label" style="color: #A8EC7A">Calcula tu ritmo</div>
                <div style="margin-top: .8rem; font-size: 1.05rem">Si lees <b class="num" style="color: #A8EC7A" x-text="min">15</b> minutos al día…</div>
                <label for="pace" class="sr-only-label">Minutos de lectura al día</label>
                <input id="pace" type="range" min="5" max="90" step="5" x-model.number="min">
                <div style="margin-top: 1.1rem"><span class="num" style="font-family: var(--f-display); font-size: 3.4rem; font-weight: 600; line-height: 1; color: #A8EC7A" x-text="(min * 365 / 60 / 7).toFixed(1).replace('.0', '')">13</span> <span style="font-size: 1.05rem">libros al año</span></div>
                <p style="font-size: 12.5px; opacity: .7; margin-top: .6rem">Estimación con 7 horas por libro.</p>
                <a class="btn" style="margin-top: 1rem; background: var(--accent); border-color: var(--accent); color: var(--accent-ink)" href="{{ route('catalog') }}">Elegir mi próximo libro →</a>
            </div>
        </div>
    </section>

    @if ($gifts->isNotEmpty())
        <x-shop.section title="Papelería y regalos" subtitle="Separadores, llaveros, figuras y más." icon="gift">
            <x-slot:link><a class="tlink" href="{{ route('collection.regalos') }}">Ver todo →</a></x-slot:link>
            <div class="bento">
                @foreach ($gifts as $i => $g)
                    <a class="tile {{ $i === 0 ? 'big' : '' }}" href="{{ route('products.show', $g) }}" style="--cb: {{ ['#D43F22', '#0B7A5E', '#12161C', '#1E3A5F', '#E0A100'][$i % 5] }}; --cf: {{ $i === 4 ? '#1B1B1B' : '#fff' }}; text-decoration: none">
                        <span class="tl-c">{{ $g->type->label() }}</span>
                        <x-shop.icon :name="['book', 'gift', 'star', 'heart', 'shield'][$i % 5]" size="62" />
                        <span><span class="tl-t">{{ $g->name }}</span><br><span class="tl-p num">S/ {{ number_format($g->currentPrice(), 2) }}</span></span>
                    </a>
                @endforeach
            </div>
        </x-shop.section>
    @endif

    @if ($shelfBooks->count() >= 4)
        <x-shop.section title="Lo que se lee en Hardcover" subtitle="Un vistazo a la estantería." icon="book">
            <div class="mosaic">
                @foreach ($shelfBooks as $book)
                    <div class="pcard">
                        <div style="position: relative">
                            <div class="cover-stage"><a href="{{ route('products.show', $book) }}" aria-label="{{ $book->name }}" style="display: contents"><x-shop.cover :product="$book" /></a></div>
                            <button type="button" class="qv-hint" x-data x-on:click="$dispatch('quick-view', { id: {{ $book->id }} })" aria-label="Vista rápida: {{ $book->name }}">Vista rápida</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-shop.section>
    @endif

    <x-shop.section title="Tu pedido en tres pasos" icon="truck">
        <div class="steps">
            @foreach ([['01', 'Elige', 'Busca por título, autor o género y añade al carrito.'], ['02', 'Paga', $transfer ? 'Tarjeta, Mercado Pago o transferencia, todo en línea.' : 'Tarjeta o Mercado Pago, todo en línea.'], ['03', 'Recibe', 'Te lo enviamos con seguimiento hasta tu puerta.']] as [$n, $t, $d])
                <div><div class="n">{{ $n }}</div><h3>{{ $t }}</h3><p class="muted" style="margin-top: .4rem; max-width: 30ch">{{ $d }}</p></div>
            @endforeach
        </div>
    </x-shop.section>

    <x-shop.section title="Preguntas frecuentes" subtitle="Lo que más nos preguntan antes de comprar." icon="shield">
        <x-slot:link><a class="tlink" href="{{ route('faq') }}">Ver todas →</a></x-slot:link>
        <div style="max-width: 860px">
            <x-shop.accordion title="¿A qué lugares envían?" :open="true">
                @if ($served->isNotEmpty())Por ahora enviamos a {{ $served->join(', ', ' y ') }}. Los envíos a otras regiones estarán disponibles próximamente.@else Los envíos estarán disponibles próximamente.@endif
            </x-shop.accordion>
            <x-shop.accordion title="¿Cuánto cuesta el envío?">El costo depende del distrito y se muestra antes de pagar. Las compras desde S/ {{ number_format($freeFrom, 2) }} tienen envío gratis.</x-shop.accordion>
            <x-shop.accordion title="¿Qué medios de pago aceptan?">{{ $transfer ? 'Tarjeta de crédito o débito, Mercado Pago y transferencia bancaria.' : 'Tarjeta de crédito o débito y Mercado Pago.' }} El pago se hace en línea.</x-shop.accordion>
            <x-shop.accordion title="¿Cómo sé si hay stock?">Cada ficha muestra la disponibilidad real. El stock se descuenta cuando se confirma tu pago, así que si quedan pocas unidades conviene pagar pronto.</x-shop.accordion>
        </div>
    </x-shop.section>

    <section class="wrap rv" style="padding-block: 56px 0" id="club">
        <div class="club">
            <div>
                <h2 style="font-size: clamp(1.6rem, 3.4vw, 2.3rem); font-weight: 600">Club de lectores Bookery</h2>
                <p class="muted" style="margin-top: .5rem; font-size: 1.05rem; max-width: 44ch">Una recomendación a la semana y avisos de novedades. Sin spam: te puedes salir cuando quieras.</p>
            </div>
            <div>
                @if (session('club_notice'))
                    <p role="status" style="font-weight: 600; color: var(--ok)">{{ session('club_notice') }}</p>
                @else
                    <form method="POST" action="{{ route('club.subscribe') }}" style="display: flex; gap: .5rem; flex-wrap: wrap">
                        @csrf
                        <label for="club-mail" class="sr-only-label">Correo</label>
                        <input id="club-mail" class="input" type="email" name="email" required placeholder="tu@correo.com" value="{{ old('email') }}" style="flex: 1; min-width: 200px; border-radius: 999px" aria-describedby="club-help">
                        <button class="btn btn-dark">Unirme</button>
                    </form>
                    @error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    <p id="club-help" class="muted" style="font-size: 12.5px; margin-top: .5rem">Al unirte aceptas recibir correos de Hardcover Bookery.</p>
                @endif
            </div>
        </div>
    </section>
</x-shop-layout>
