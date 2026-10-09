@use('App\Enums\OrderStatus')
@use('App\Support\Money')

<x-panel-layout title="Ventas" subtitle="Cuentan los pedidos con pago confirmado que no se cancelaron ni reembolsaron.">
    <div class="p-stats">
        @foreach ([['Hoy', $today], ['Esta semana', $week], ['Este mes', $month]] as [$label, $t])
            <div class="p-stat"><b>{{ Money::format($t['total']) }}</b>{{ $label }} · {{ $t['orders'] }} {{ $t['orders'] === 1 ? 'pedido' : 'pedidos' }}</div>
        @endforeach
    </div>

    <form method="GET" class="p-tools">
        <label class="p-field">Periodo
            <select name="dias" onchange="this.form.submit()">
                @foreach ($periods as $p)
                    <option value="{{ $p }}" @selected($days === $p)>Últimos {{ $p }} días</option>
                @endforeach
            </select>
        </label>
        <noscript><button class="p-btn">Ver</button></noscript>
    </form>

    <section class="p-card">
        <h2>Ventas por día · {{ Money::format($period['total']) }} en {{ $period['orders'] }} {{ $period['orders'] === 1 ? 'pedido' : 'pedidos' }}</h2>
        <div role="img" aria-label="Ventas diarias de los últimos {{ $days }} días" style="display: flex; align-items: flex-end; gap: 2px; height: 140px">
            @foreach ($daily as $d)
                <div title="{{ $d['date']->format('d/m') }}: {{ Money::format($d['total']) }} ({{ $d['orders'] }})"
                     style="flex: 1; min-width: 2px; background: {{ (float) $d['total'] > 0 ? 'var(--accent-deep)' : 'var(--line)' }}; height: {{ max(2, round((float) $d['total'] / $maxDay * 100)) }}%; border-radius: 2px 2px 0 0"></div>
            @endforeach
        </div>
        <p class="p-muted" style="display: flex; justify-content: space-between; margin: 6px 0 0"><span>{{ $daily[0]['date']->format('d/m') }}</span><span>{{ end($daily)['date']->format('d/m') }}</span></p>
    </section>

    <div class="p-grid">
        <section class="p-card">
            <h2>Más vendidos</h2>
            @forelse ($top as $row)
                <ul class="p-lines"><li><span>{{ $row['name'] }}</span><span><b>{{ $row['units'] }}</b> u. · {{ Money::format($row['revenue']) }}</span></li></ul>
            @empty
                <p class="p-muted">Sin ventas en este periodo.</p>
            @endforelse
        </section>

        <section class="p-card">
            <h2>Pedidos por estado (todos)</h2>
            <ul class="p-lines">
                @foreach (OrderStatus::cases() as $s)
                    <li><a href="{{ route('admin.orders.index', ['estado' => $s->value]) }}">{{ $s->label() }}</a><b class="num">{{ $byStatus[$s->value] }}</b></li>
                @endforeach
            </ul>
        </section>
    </div>
</x-panel-layout>
