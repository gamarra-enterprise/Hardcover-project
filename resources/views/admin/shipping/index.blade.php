@use('App\Support\Money')

<x-panel-layout title="Envíos" :subtitle="'El costo depende del distrito. Desde '.Money::format($freeFrom).' de compra el envío es gratis.'">
    @if (session('notice'))<div class="p-notice" role="status">{{ session('notice') }}</div>@endif
    @if (session('error'))<div class="p-notice error" role="alert">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="p-notice error" role="alert">{{ $errors->first() }}</div>@endif

    <section class="p-card">
        <h2>Envío gratis</h2>
        <form method="POST" action="{{ route('admin.shipping.settings') }}" class="p-tools" style="margin-bottom: 0">
            @csrf @method('PUT')
            <label class="p-field">Gratis desde (S/, IGV incluido)
                <input type="number" step="0.01" min="0" name="free_shipping_from" value="{{ number_format($freeFrom, 2, '.', '') }}" required>
                @error('free_shipping_from')<span style="color: var(--bad)">{{ $message }}</span>@enderror
            </label>
            <button class="p-btn">Guardar</button>
        </form>
        <p class="p-muted">Se ve en la barra superior, el carrito y el checkout de inmediato. Pon 0 para que todo envío sea gratis.</p>
    </section>

    @foreach ($zones as $zone)
        <section class="p-card">
            <h2>{{ $zone->name }}</h2>
            <form method="POST" action="{{ route('admin.shipping.zones.update', $zone) }}" class="p-tools">
                @csrf @method('PUT')
                <label class="p-field">Costo mínimo (S/)
                    <input type="number" step="0.01" min="0" name="min_cost" value="{{ $zone->min_cost }}" required>
                </label>
                <label><input type="checkbox" name="is_active" value="1" @checked($zone->is_active)> Zona activa</label>
                <button class="p-btn">Guardar zona</button>
            </form>
            <p class="p-muted" style="margin-top: -6px">Ningún distrito de esta zona puede costar menos que el mínimo. Si lo subes, los distritos que estaban por debajo suben con él.</p>

            <div class="p-table-wrap" style="margin-top: 12px">
                <table class="p-table">
                    <thead><tr><th>Distrito</th><th>Ubigeo</th><th>Costo (S/)</th><th>Activo</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($zone->districts as $district)
                            <tr>
                                <td>{{ $district->name }}</td>
                                <td>{{ $district->ubigeo }}</td>
                                <td><input form="d{{ $district->id }}" type="number" step="0.01" min="0" name="cost" value="{{ $district->cost }}" style="min-width: 100px" aria-label="Costo de {{ $district->name }}"></td>
                                <td><input form="d{{ $district->id }}" type="checkbox" name="is_active" value="1" @checked($district->is_active) aria-label="Activo"></td>
                                <td><button form="d{{ $district->id }}" class="p-btn">Guardar</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @foreach ($zone->districts as $district)
                <form method="POST" action="{{ route('admin.shipping.districts.update', $district) }}" id="d{{ $district->id }}">@csrf @method('PUT')</form>
            @endforeach

            <form method="POST" action="{{ route('admin.shipping.districts.store', $zone) }}" class="p-tools" style="margin: 14px 0 0">
                @csrf
                <label class="p-field">Nuevo distrito<input type="text" name="name" required maxlength="120"></label>
                <label class="p-field">Ubigeo<input type="text" name="ubigeo" required maxlength="6" inputmode="numeric" style="min-width: 110px"></label>
                <label class="p-field">Costo (S/)<input type="number" step="0.01" min="0" name="cost" required style="min-width: 100px"></label>
                <button class="p-btn p-btn-primary">Agregar</button>
            </form>
        </section>
    @endforeach
</x-panel-layout>
