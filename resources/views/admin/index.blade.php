@use('App\Support\Money')

<x-panel-layout title="Resumen" subtitle="Lo que necesita atención hoy.">
    <div class="p-stats">
        <a class="p-stat" href="{{ route('admin.orders.index', ['estado' => 'confirmed']) }}" style="text-decoration: none; color: inherit"><b>{{ $toPrepare }}</b>Por preparar</a>
        <a class="p-stat" href="{{ route('admin.orders.index', ['estado' => 'shipped']) }}" style="text-decoration: none; color: inherit"><b>{{ $toDeliver }}</b>En camino</a>
        <a class="p-stat" href="{{ route('admin.orders.index', ['estado' => 'pending']) }}" style="text-decoration: none; color: inherit"><b>{{ $awaitingPayment }}</b>Esperando pago</a>
        <div class="p-stat"><b>{{ $lowStock }}</b>Productos con poco stock</div>
    </div>

    <section class="p-card">
        <h2>Últimos pedidos</h2>
        @forelse ($recent as $order)
            <ul class="p-lines">
                <li>
                    <a href="{{ route('admin.orders.show', $order) }}">{{ $order->tracking_code }}</a>
                    <span>{{ $order->status->label() }}</span>
                    <b class="num">{{ Money::format($order->total) }}</b>
                </li>
            </ul>
        @empty
            <p class="p-muted">Todavía no hay pedidos.</p>
        @endforelse
    </section>
</x-panel-layout>
