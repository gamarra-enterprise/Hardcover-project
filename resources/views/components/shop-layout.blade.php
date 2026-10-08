@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Instrument+Sans:wght@400..700&family=JetBrains+Mono:wght@400;500&display=swap">

        @vite(['resources/css/app.css', 'resources/css/shop.css', 'resources/js/shop.js'])
    </head>
    <body class="shop">
        <div class="announce">Envío gratis desde S/ 150 · Despachamos en 24 horas</div>

        <header class="hdr line-b">
            <div class="wrap">
                <div class="hdr-row">
                    <a class="logo" href="{{ route('home') }}">hardcover<small>bookery</small></a>

                    <div class="hdr-icons">
                        @guest
                            <a class="btn btn-sm" href="{{ route('login') }}">Ingresar</a>
                        @else
                            @if (auth()->user()->isStaff())
                                <a href="{{ route('admin.index') }}">Panel</a>
                            @endif
                            @if (auth()->user()->role === \App\Enums\UserRole::SUPER_ADMIN)
                                <a href="{{ route('super.index') }}">Super admin</a>
                            @endif
                            <a class="btn btn-sm" href="{{ route('dashboard') }}" aria-label="Mi cuenta"><x-shop.icon name="user" /></a>
                        @endguest
                    </div>
                </div>
                <nav class="hdr-nav" aria-label="Principal">
                    <a href="{{ route('home') }}#novedades">Novedades</a>
                </nav>
            </div>
        </header>

        {{ $slot }}

        <div class="wrap" style="margin-top: 80px">
            <div class="trust">
                @foreach ([['shield', 'Pagos seguros'], ['truck', 'Envíos a todo el Perú'], ['book', 'Recojo en tienda'], ['gift', 'Envoltorio de regalo'], ['heart', 'Librería independiente']] as [$icon, $text])
                    <div><x-shop.icon :name="$icon" size="22" /><span>{{ $text }}</span></div>
                @endforeach
            </div>
        </div>

        <footer class="bg-surface" style="margin-top: 48px">
            <div class="wrap" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 32px; padding-block: 44px">
                <div>
                    <a class="logo" href="{{ route('home') }}">hardcover<small>bookery</small></a>
                    <p class="muted" style="margin-top: .75rem; max-width: 30ch">Libros, papelería y pequeños objetos para lectores. Envíos a todo el Perú.</p>
                </div>
                <div>
                    <div class="label">Síguenos</div>
                    <p style="margin-top: .6rem"><a href="https://www.instagram.com/hardcoverbookery" target="_blank" rel="noopener" style="font-weight: 600; text-decoration: underline">@hardcoverbookery</a></p>
                    <p class="muted" style="font-size: 13px; margin-top: .35rem">Lanzamientos, reseñas y sorteos.</p>
                </div>
            </div>
            <div class="wrap line-t muted" style="padding-block: 14px; font-size: 12.5px">© {{ date('Y') }} Hardcover Bookery · Los precios incluyen IGV</div>
        </footer>
    </body>
</html>
