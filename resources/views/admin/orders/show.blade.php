@use('App\Enums\OrderStatus')
@use('App\Support\Money')

@php($address = $order->shipping_address)

<x-panel-layout :title="'Pedido '.$order->tracking_code" :subtitle="$order->status->label().' · '.$order->created_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i')">
    <p style="margin-top: -10px"><a href="{{ route('admin.orders.index') }}">← Volver a pedidos</a></p>

    @if (session('notice'))
        <div class="p-notice" role="status">{{ session('notice') }}</div>
    @endif
    @if (session('error'))
        <div class="p-notice error" role="alert">{{ session('error') }}</div>
    @endif

    <section class="p-card">
        <h2>Estado</h2>
        @if ($order->refund_amount !== null)
            <p>Reembolso: <b>{{ Money::format($order->refund_amount) }}</b></p>
        @endif
        @if ($targets)
            <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="p-tools" style="margin-bottom: 0"
                  onsubmit="return confirm('¿Cambiar el estado? Se avisará al cliente por correo.')">
                @csrf
                @method('PATCH')
                <label class="p-field">Pasar a
                    <select name="status">
                        @foreach ($targets as $t)
                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="p-field">Nota (opcional)
                    <input type="text" name="note" maxlength="255">
                </label>
                <button class="p-btn p-btn-primary">Cambiar estado</button>
            </form>
            @error('status') <p class="p-notice error" style="margin-top: 12px">{{ $message }}</p> @enderror
        @else
            <p class="p-muted">Este pedido no admite cambios de estado manuales.</p>
        @endif
        @if ($order->status === OrderStatus::PENDING)
            <p class="p-muted">Se confirma solo cuando se acredita el pago.</p>
        @endif
    </section>

    <div class="p-grid">
        <section class="p-card">
            <h2>Cliente y entrega</h2>
            <p><b>{{ $address['recipient_name'] ?? '' }}</b><br>
                {{ $order->contactEmail() }} · Cel. {{ $address['phone'] ?? '' }}<br>
                {{ $address['line1'] ?? '' }}@if (! empty($address['line2'])), {{ $address['line2'] }}@endif, {{ $address['city'] ?? '' }}@if (! empty($address['state'])), {{ $address['state'] }}@endif</p>
            <p class="p-muted">{{ $order->user ? 'Con cuenta' : 'Compra sin cuenta' }}</p>
        </section>

        <section class="p-card">
            <h2>Pagos</h2>
            @forelse ($order->payments as $p)
                @php($manual = $p->provider === 'bank_transfer')
                <div style="margin-bottom: 14px">
                    <p style="margin: 0 0 6px">{{ $manual ? 'Transferencia' : $p->provider }} · {{ Money::format($p->amount) }} · <span class="p-pill {{ $p->status->value === 'completed' ? 'done' : ($p->status->value === 'failed' ? 'off' : 'warn') }}">{{ $p->status->value }}</span></p>
                    @if ($manual)
                        @if (! empty($p->payload['proof']))
                            <p style="margin: 0 0 6px"><a href="{{ route('admin.payments.proof', $p) }}" target="_blank" rel="noopener">Ver comprobante</a>@if (! empty($p->payload['note'])) · <span class="p-muted">{{ $p->payload['note'] }}</span>@endif</p>
                        @endif
                        @if ($p->status === \App\Enums\PaymentStatus::PROCESSING)
                            <div class="p-tools" style="margin-bottom: 0">
                                <form method="POST" action="{{ route('admin.payments.confirm', $p) }}" onsubmit="return confirm('¿El dinero ya está en la cuenta? Se confirmará el pedido y se descontará el stock.')">
                                    @csrf <button class="p-btn p-btn-primary">Confirmar pago</button>
                                </form>
                                <form method="POST" action="{{ route('admin.payments.reject', $p) }}" class="p-tools" style="margin-bottom: 0">
                                    @csrf
                                    <label class="p-field">Motivo del rechazo<input type="text" name="reason" required maxlength="200"></label>
                                    <button class="p-btn">Rechazar</button>
                                </form>
                            </div>
                        @elseif ($p->status === \App\Enums\PaymentStatus::COMPLETED && $order->status === \App\Enums\OrderStatus::CANCELLED)
                            <form method="POST" action="{{ route('admin.payments.refunded', $p) }}" onsubmit="return confirm('¿Ya devolviste el dinero al cliente?')">
                                @csrf <button class="p-btn">Ya devolví el dinero</button>
                            </form>
                        @endif
                    @endif
                </div>
            @empty
                <p class="p-muted">Sin pagos registrados.</p>
            @endforelse
        </section>
    </div>

    <section class="p-card">
        <h2>Productos</h2>
        <ul class="p-lines">
            @foreach ($order->items as $item)
                <li><span>{{ $item->quantity }} × {{ $item->product_snapshot['name'] ?? 'Producto' }}</span><b class="num">{{ Money::format($item->lineTotal()) }}</b></li>
            @endforeach
        </ul>
        <p style="text-align: right; margin: 12px 0 0">Envío {{ Money::format($order->shipping_cost) }} · <b>Total {{ Money::format($order->total) }}</b></p>
    </section>

    <section class="p-card">
        <h2>Historial</h2>
        <ul class="p-lines">
            @foreach ($order->statusHistories->reverse() as $h)
                <li>
                    <span><b>{{ $h->to_status->label() }}</b> · {{ $h->author?->name ?? 'Sistema' }}@if ($h->note) · {{ $h->note }}@endif</span>
                    <span class="p-muted">{{ $h->created_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</span>
                </li>
            @endforeach
        </ul>
    </section>
</x-panel-layout>
