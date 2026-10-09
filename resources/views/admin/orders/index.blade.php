@use('App\Enums\OrderStatus')
@use('App\Support\Money')

<x-panel-layout title="Pedidos" subtitle="Busca por código o correo y filtra por estado.">
    <form method="GET" class="p-tools">
        <label class="p-field">Buscar
            <input type="search" name="q" value="{{ $search }}" placeholder="Código o correo">
        </label>
        <label class="p-field">Estado
            <select name="estado">
                <option value="">Todos</option>
                @foreach (OrderStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </label>
        <button class="p-btn p-btn-primary">Filtrar</button>
        @if ($search !== '' || $status)
            <a class="p-btn" href="{{ route('admin.orders.index') }}">Limpiar</a>
        @endif
    </form>

    <div class="p-table-wrap">
        <table class="p-table">
            <thead>
                <tr><th>Pedido</th><th>Fecha</th><th>Cliente</th><th>Estado</th><th class="num">Total</th></tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    @php($off = in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true))
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $order) }}"><b>{{ $order->tracking_code }}</b></a></td>
                        <td>{{ $order->created_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</td>
                        <td>{{ $order->shipping_address['recipient_name'] ?? '' }}<br><span class="p-muted">{{ $order->contactEmail() }}</span></td>
                        <td><span class="p-pill {{ $off ? 'off' : ($order->status === OrderStatus::DELIVERED ? 'done' : ($order->status === OrderStatus::PENDING ? 'warn' : '')) }}">{{ $order->status->label() }}</span></td>
                        <td class="num">{{ Money::format($order->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-muted" style="text-align: center; padding: 28px">No hay pedidos con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 16px">{{ $orders->links() }}</div>
</x-panel-layout>
