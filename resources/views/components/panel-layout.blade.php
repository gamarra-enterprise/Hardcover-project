@props(['title' => null, 'subtitle' => null])

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

                <div class="p-group">Tienda</div>
                <a class="p-link" href="{{ route('admin.index') }}" @if (request()->routeIs('admin.index')) aria-current="page" @endif>Resumen</a>
                <a class="p-link" href="{{ route('admin.sales') }}" @if (request()->routeIs('admin.sales')) aria-current="page" @endif>Ventas</a>
                <a class="p-link" href="{{ route('admin.orders.index') }}" @if (request()->routeIs('admin.orders.*')) aria-current="page" @endif>Pedidos</a>

                <a class="p-link" href="{{ route('admin.products.index') }}" @if (request()->routeIs('admin.products.*')) aria-current="page" @endif>Productos</a>
                <a class="p-link" href="{{ route('admin.inventory.index') }}" @if (request()->routeIs('admin.inventory.*')) aria-current="page" @endif>Inventario</a>
                <a class="p-link" href="{{ route('admin.categories.index') }}" @if (request()->routeIs('admin.categories.*')) aria-current="page" @endif>Categorías</a>

                <a class="p-link" href="{{ route('admin.club.index') }}" @if (request()->routeIs('admin.club.*')) aria-current="page" @endif>Club de lectores</a>
                <a class="p-link" href="{{ route('admin.shipping.index') }}" @if (request()->routeIs('admin.shipping.*')) aria-current="page" @endif>Envíos</a>

                @if (auth()->user()->role === \App\Enums\UserRole::SUPER_ADMIN)
                    <div class="p-group">Sistema</div>
                    <a class="p-link" href="{{ route('super.index') }}" @if (request()->routeIs('super.index')) aria-current="page" @endif>Resumen</a>
                    <a class="p-link" href="{{ route('super.users.index') }}" @if (request()->routeIs('super.users.*')) aria-current="page" @endif>Usuarios y roles</a>
                    <a class="p-link" href="{{ route('super.gateways') }}" @if (request()->routeIs('super.gateways')) aria-current="page" @endif>Pasarelas de pago</a>
                    <a class="p-link" href="{{ route('super.backups.index') }}" @if (request()->routeIs('super.backups.*')) aria-current="page" @endif>Respaldos</a>
                    <a class="p-link" href="{{ route('super.activity') }}" @if (request()->routeIs('super.activity')) aria-current="page" @endif>Actividad</a>
                @endif

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
                @if ($title)
                    <h1 class="p-title">{{ $title }}</h1>
                    <p class="p-sub">{{ $subtitle }}</p>
                @endif
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
