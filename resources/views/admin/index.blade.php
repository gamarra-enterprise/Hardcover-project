@use('App\Support\Money')

<x-panel-layout title="Resumen" subtitle="Lo que necesita atención hoy.">
    <div class="p-stats">
        <div class="p-stat"><b>{{ Money::format($salesToday['total']) }}</b>Ventas de hoy · {{ $salesToday['orders'] }} {{ $salesToday['orders'] === 1 ? 'pedido' : 'pedidos' }}</div>
        <div class="p-stat"><b>{{ $avgTicket ? Money::format($avgTicket) : '—' }}</b>Ticket promedio · 30 días</div>
        <a class="p-stat" href="{{ route('admin.orders.index', ['estado' => 'confirmed']) }}" style="text-decoration: none; color: inherit"><b>{{ $toPrepare }}</b>Por preparar</a>
        <a class="p-stat" href="{{ route('admin.orders.index', ['estado' => 'shipped']) }}" style="text-decoration: none; color: inherit"><b>{{ $toDeliver }}</b>En camino</a>
        <a class="p-stat" href="{{ route('admin.orders.index', ['estado' => 'pending']) }}" style="text-decoration: none; color: inherit"><b>{{ $awaitingPayment }}</b>Esperando pago</a>
        <div class="p-stat"><b>{{ $proofsToReview }}</b>Comprobantes por revisar</div>
        <div class="p-stat"><b>{{ $lowStock }}</b>Productos con poco stock</div>
    </div>

    <div class="p-grid">
        <section class="p-card">
            <h2>Ventas de los últimos 7 días</h2>
            <div role="img" aria-label="Ventas diarias de los últimos 7 días" style="display: flex; align-items: flex-end; gap: 6px; height: 120px">
                @foreach ($daily as $d)
                    <div title="{{ $d['date']->format('d/m') }}: {{ Money::format($d['total']) }}" style="flex: 1; display: grid; align-items: end; height: 100%">
                        <div style="background: {{ (float) $d['total'] > 0 ? 'var(--accent-deep)' : 'var(--line)' }}; height: {{ max(2, round((float) $d['total'] / $maxDay * 100)) }}%; border-radius: 3px 3px 0 0"></div>
                    </div>
                @endforeach
            </div>
            <p class="p-muted" style="display: flex; justify-content: space-between; margin: 6px 0 0"><span>{{ $daily[0]['date']->format('d/m') }}</span><span>hoy</span></p>
        </section>
        <section class="p-card">
            <h2>Reponer pronto</h2>
            @forelse ($restock as $p)
                <ul class="p-lines"><li><a href="{{ route('admin.products.edit', $p) }}">{{ $p->name }}</a><b class="num" style="color: {{ $p->stock ? 'var(--warn)' : 'var(--bad)' }}">{{ $p->stock ? $p->stock.' u.' : 'Agotado' }}</b></li></ul>
            @empty
                <p class="p-muted">Todo con stock suficiente.</p>
            @endforelse
            <p style="margin: 10px 0 0"><a class="p-btn" href="{{ route('admin.inventory.index', ['bajo' => 1]) }}">Ir a inventario</a></p>
        </section>
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
