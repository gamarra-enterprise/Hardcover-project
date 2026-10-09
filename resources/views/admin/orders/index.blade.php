@use('App\Enums\OrderStatus')
@use('App\Support\Money')

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Pedidos') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="flex flex-wrap gap-3 items-end">
                <label class="text-sm">Buscar
                    <input type="search" name="q" value="{{ $search }}" placeholder="Código o correo" class="block mt-1 rounded border-gray-300">
                </label>
                <label class="text-sm">Estado
                    <select name="estado" class="block mt-1 rounded border-gray-300">
                        <option value="">Todos</option>
                        @foreach (OrderStatus::cases() as $s)
                            <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="px-4 py-2 bg-gray-800 text-white rounded">Filtrar</button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-gray-500">
                        <tr><th class="p-3">Pedido</th><th class="p-3">Fecha</th><th class="p-3">Cliente</th><th class="p-3">Estado</th><th class="p-3 text-right">Total</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr class="border-t">
                                <td class="p-3"><a class="text-indigo-600 underline" href="{{ route('admin.orders.show', $order) }}">{{ $order->tracking_code }}</a></td>
                                <td class="p-3">{{ $order->created_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</td>
                                <td class="p-3">{{ $order->shipping_address['recipient_name'] ?? '' }}<br><span class="text-gray-500">{{ $order->contactEmail() }}</span></td>
                                <td class="p-3">{{ $order->status->label() }}</td>
                                <td class="p-3 text-right">{{ Money::format($order->total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-6 text-center text-gray-500">No hay pedidos con esos filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $orders->links() }}
        </div>
    </div>
</x-app-layout>
