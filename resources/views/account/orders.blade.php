@use('App\Enums\OrderStatus')
@use('App\Support\Money')

<x-shop-layout title="Mis pedidos">
    <div class="wrap" style="padding-block: 28px 48px">
        <h1 style="font-size: clamp(2rem, 4.5vw, 3rem); font-weight: 800; letter-spacing: -.035em">Mis pedidos</h1>

        @if ($orders->isEmpty())
            <p class="muted" style="margin-top: 1rem">Todavía no tienes pedidos. <a href="{{ route('catalog') }}">Ver el catálogo</a>.</p>
        @else
            <ul style="display: grid; gap: 14px; margin-top: 1.4rem; list-style: none; padding: 0">
                @foreach ($orders as $order)
                    @php($isOff = in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true))
                    <li class="card" style="padding: 18px; display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: center">
                        <div>
                            <a href="{{ route('orders.show', $order) }}"><b class="num">{{ $order->tracking_code }}</b></a>
                            <div class="muted" style="font-size: 13px">
                                {{ $order->created_at->timezone(config('app.display_timezone'))->translatedFormat('j \d\e F \d\e Y') }}
                                · {{ $order->items_count }} {{ $order->items_count === 1 ? 'producto' : 'productos' }}
                            </div>
                        </div>
                        <span class="status-pill" style="color: {{ $isOff ? 'var(--bad)' : ($order->status === OrderStatus::DELIVERED ? 'var(--ok)' : 'var(--accent-deep)') }}">{{ $order->status->label() }}</span>
                        <b class="num">{{ Money::format($order->total) }}</b>
                        <a class="btn btn-sm" href="{{ route('orders.show', $order) }}">Ver detalle</a>
                    </li>
                @endforeach
            </ul>
            <div style="margin-top: 1.2rem">{{ $orders->links() }}</div>
        @endif
    </div>
</x-shop-layout>
