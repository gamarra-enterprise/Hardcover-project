@use('App\Support\Money')

<x-panel-layout title="Pasarelas de pago" subtitle="Solo lectura: las claves se configuran en el archivo .env del servidor y nunca se muestran aquí.">
    <section class="p-card">
        <h2>Pasarela activa</h2>
        <p>
            <span class="p-pill {{ $isFake ? 'warn' : 'done' }}">{{ $isFake ? 'Prueba (fake)' : ucfirst($active) }}</span>
            @if ($isFake)
                <span class="p-muted">Los pagos no son reales. Es el modo de desarrollo; en producción se rechaza.</span>
            @endif
        </p>
        <p class="p-muted">Se cambia con <code>PAYMENT_GATEWAY</code> en <code>.env</code> (<code>mercadopago</code> o <code>fake</code>).</p>
    </section>

    <section class="p-card">
        <h2>Mercado Pago {!! $ready ? '<span class="p-pill done">Listo</span>' : '<span class="p-pill warn">Incompleto</span>' !!}</h2>
        <ul class="p-lines">
            @foreach ($checks as [$label, $ok])
                <li><span>{{ $label }}</span><span class="p-pill {{ $ok ? 'done' : 'off' }}">{{ $ok ? 'Configurado' : 'Falta' }}</span></li>
            @endforeach
        </ul>
        <p class="p-muted" style="margin-top: 12px">Dirección de notificaciones (webhook) para registrar en Mercado Pago:</p>
        <p><code>{{ $webhookUrl }}</code></p>
    </section>

    <div class="p-grid">
        <section class="p-card">
            <h2>Pagos por estado</h2>
            @forelse ($byStatus as $status => $n)
                <ul class="p-lines"><li><span>{{ $status }}</span><b class="num">{{ $n }}</b></li></ul>
            @empty
                <p class="p-muted">Todavía no hay pagos.</p>
            @endforelse
        </section>
        <section class="p-card">
            <h2>Últimos pagos</h2>
            @forelse ($recent as $p)
                <ul class="p-lines"><li>
                    <a href="{{ route('admin.orders.show', $p->order_id) }}">{{ $p->order?->tracking_code }}</a>
                    <span>{{ $p->provider }} · {{ $p->status->value }}</span>
                    <b class="num">{{ Money::format($p->amount) }}</b>
                </li></ul>
            @empty
                <p class="p-muted">Sin movimientos.</p>
            @endforelse
        </section>
    </div>
</x-panel-layout>
