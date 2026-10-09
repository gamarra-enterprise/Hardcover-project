@props(['title' => null, 'subtitle' => null])

@php
    $isSuper = auth()->user()->role === \App\Enums\UserRole::SUPER_ADMIN;
    // The menu has one entry per area; the screens inside an area are tabs at the top of each page.
    $groups = [
        ['label' => 'Resumen', 'tabs' => [['Resumen', 'admin.index', 'admin.index'], ['Ventas', 'admin.sales', 'admin.sales']]],
        ['label' => 'Pedidos', 'tabs' => [['Pedidos', 'admin.orders.index', 'admin.orders.*']]],
        ['label' => 'Catálogo', 'tabs' => [['Productos', 'admin.products.index', 'admin.products.*'], ['Inventario', 'admin.inventory.index', 'admin.inventory.*'], ['Categorías', 'admin.categories.index', 'admin.categories.*']]],
        ['label' => 'Tienda', 'tabs' => [['Envíos', 'admin.shipping.index', 'admin.shipping.*'], ['Club de lectores', 'admin.club.index', 'admin.club.*']]],
        ['super' => true, 'heading' => 'Super admin', 'label' => 'Resumen', 'tabs' => [['Resumen', 'super.index', 'super.index']]],
        ['super' => true, 'label' => 'Usuarios y roles', 'tabs' => [['Usuarios y roles', 'super.users.index', 'super.users.*']]],
        ['super' => true, 'label' => 'Sistema', 'tabs' => [['Pasarelas de pago', 'super.gateways', 'super.gateways'], ['Respaldos', 'super.backups.index', 'super.backups.*']]],
        ['super' => true, 'label' => 'Actividad', 'tabs' => [['Actividad', 'super.activity', 'super.activity']]],
    ];
    $current = collect($groups)->first(fn ($g) => collect($g['tabs'])->contains(fn ($t) => request()->routeIs($t[2])));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex">
        <title>{{ $title ? $title.' · ' : '' }}Panel · {{ config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Instrument+Sans:wght@400..700&display=swap">
        @vite(['resources/css/panel.css'])
    </head>
    <body class="panel">
        <div class="p-shell">
            <nav class="p-side" aria-label="Panel">
                <a class="p-logo" href="{{ route('admin.index') }}">hardcover<small>panel</small></a>

                @foreach ($groups as $group)
                    @if ($group['super'] ?? false) @continue(! $isSuper) @endif
                    @if (! empty($group['heading']))<div class="p-group">{{ $group['heading'] }}</div>@endif
                    <a class="p-link" href="{{ route($group['tabs'][0][1]) }}" @if ($group === $current) aria-current="page" @endif>{{ $group['label'] }}</a>
                @endforeach

                <div class="p-foot">
                    <a href="{{ route('security.show') }}">Seguridad (2 pasos)</a>
                    <a href="{{ route('home') }}">Ver la tienda</a>
                    <span>{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="p-btn" type="submit">Salir</button>
                    </form>
                </div>
            </nav>

            <main class="p-main">
                @if ($current && count($current['tabs']) > 1)
                    <nav class="p-tabs" aria-label="{{ $current['label'] }}">
                        @foreach ($current['tabs'] as [$tabLabel, $tabRoute, $tabPattern])
                            <a href="{{ route($tabRoute) }}" @if (request()->routeIs($tabPattern)) aria-current="page" @endif>{{ $tabLabel }}</a>
                        @endforeach
                    </nav>
                @endif
                @if ($title)
                    <h1 class="p-title">{{ $title }}</h1>
                    <p class="p-sub">{{ $subtitle }}</p>
                @endif
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
