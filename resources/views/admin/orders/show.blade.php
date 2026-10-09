@use('App\Support\Money')

@php($address = $order->shipping_address)

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Pedido') }} {{ $order->tracking_code }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <a class="text-indigo-600 underline text-sm" href="{{ route('admin.orders.index') }}">← Volver a pedidos</a>

            @if (session('notice'))
                <div class="p-3 rounded bg-green-50 text-green-800" role="status">{{ session('notice') }}</div>
            @endif
            @if (session('error'))
                <div class="p-3 rounded bg-red-50 text-red-800" role="alert">{{ session('error') }}</div>
            @endif

            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-3">
                <p><b>Estado:</b> {{ $order->status->label() }}
                    · {{ $order->created_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</p>
                @if ($order->refund_amount !== null)
                    <p><b>Reembolso:</b> {{ Money::format($order->refund_amount) }}</p>
                @endif

                @if ($targets)
                    <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="flex flex-wrap gap-3 items-end"
                          onsubmit="return confirm('¿Cambiar el estado? Se avisará al cliente por correo.')">
                        @csrf
                        @method('PATCH')
                        <label class="text-sm">Pasar a
                            <select name="status" class="block mt-1 rounded border-gray-300">
                                @foreach ($targets as $t)
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm">Nota (opcional)
                            <input type="text" name="note" maxlength="255" class="block mt-1 rounded border-gray-300">
                        </label>
                        <button class="px-4 py-2 bg-gray-800 text-white rounded">Cambiar estado</button>
                    </form>
                    @error('status') <p class="text-red-700 text-sm">{{ $message }}</p> @enderror
                @else
                    <p class="text-gray-500 text-sm">Este pedido no admite cambios de estado manuales.</p>
                @endif
                @if ($order->status === \App\Enums\OrderStatus::PENDING)
                    <p class="text-gray-500 text-sm">Se confirma solo cuando se acredita el pago.</p>
                @endif
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold mb-2">Cliente y entrega</h3>
                <p>{{ $address['recipient_name'] ?? '' }} · {{ $order->contactEmail() }} · Cel. {{ $address['phone'] ?? '' }}</p>
                <p>{{ $address['line1'] ?? '' }}@if (! empty($address['line2'])), {{ $address['line2'] }}@endif, {{ $address['city'] ?? '' }}@if (! empty($address['state'])), {{ $address['state'] }}@endif</p>
                <p class="text-gray-500 text-sm">{{ $order->user ? 'Con cuenta' : 'Compra sin cuenta' }}</p>
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold mb-2">Productos</h3>
                <ul class="divide-y">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between py-2">
                            <span>{{ $item->quantity }} × {{ $item->product_snapshot['name'] ?? 'Producto' }}</span>
                            <span>{{ Money::format($item->lineTotal()) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-right">Envío: {{ Money::format($order->shipping_cost) }} · <b>Total: {{ Money::format($order->total) }}</b></p>
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold mb-2">Pagos</h3>
                @forelse ($order->payments as $p)
                    <p>{{ $p->provider }} · {{ Money::format($p->amount) }} · {{ $p->status->value }}</p>
                @empty
                    <p class="text-gray-500">Sin pagos registrados.</p>
                @endforelse
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold mb-2">Historial</h3>
                <ul class="space-y-1 text-sm">
                    @foreach ($order->statusHistories->reverse() as $h)
                        <li>{{ $h->created_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}
                            · <b>{{ $h->to_status->label() }}</b>
                            · {{ $h->author?->name ?? 'Sistema' }}@if ($h->note) · {{ $h->note }}@endif</li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>
</x-app-layout>
