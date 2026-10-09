<x-panel-layout title="Inventario" :subtitle="'En rojo, agotados; en naranja, '.$lowAt.' unidades o menos.'">
    @if (session('notice'))<div class="p-notice" role="status">{{ session('notice') }}</div>@endif

    <form method="GET" class="p-tools">
        <label class="p-field">Buscar<input type="search" name="q" value="{{ $search }}" placeholder="Nombre o SKU"></label>
        <label style="display: flex; gap: 6px; align-items: center"><input type="checkbox" name="bajo" value="1" @checked($low)> Solo stock bajo</label>
        <button class="p-btn p-btn-primary">Filtrar</button>
    </form>

    <div class="p-table-wrap">
        <table class="p-table">
            <thead><tr><th>Producto</th><th>SKU</th><th>Tipo</th><th class="num">Precio</th><th>Stock</th></tr></thead>
            <tbody>
                @forelse ($products as $p)
                    <tr>
                        <td style="min-width: 200px"><a href="{{ route('admin.products.edit', $p) }}"><b>{{ $p->name }}</b></a></td>
                        <td>{{ $p->sku }}</td>
                        <td>{{ $p->type->label() }}</td>
                        <td class="num">S/ {{ number_format($p->currentPrice(), 2) }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.inventory.update', $p) }}" style="display: flex; gap: 6px">
                                @csrf @method('PATCH')
                                <label class="sr-only-label" for="st-{{ $p->id }}" style="position: absolute; left: -9999px">Stock de {{ $p->name }}</label>
                                <input id="st-{{ $p->id }}" type="number" name="stock" min="0" value="{{ $p->stock }}" required
                                       style="min-width: 0; width: 86px; {{ $p->stock === 0 ? 'border-color: var(--bad); color: var(--bad)' : ($p->stock <= $lowAt ? 'border-color: var(--warn); color: var(--warn)' : '') }}">
                                <button class="p-btn">Guardar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-muted" style="text-align: center; padding: 28px">No hay productos con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top: 16px">{{ $products->links('pagination.panel') }}</div>
</x-panel-layout>
