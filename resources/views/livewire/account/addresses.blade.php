<div class="wrap" style="padding-block: 28px 48px">
    <nav class="muted" style="font-size: 13px" aria-label="Ruta"><a href="{{ route('account.orders') }}">Mi cuenta</a> / Direcciones</nav>
    <h1 style="font-size: clamp(2rem, 4.5vw, 3rem); font-weight: 800; letter-spacing: -.035em">Mis direcciones</h1>
    <x-shop.account-tabs active="addresses" />

    @if ($this->addresses->isEmpty() && ! $showForm)
        <p class="muted" style="margin-top: 1rem">Todavía no guardaste ninguna dirección.</p>
    @endif

    <ul style="display: grid; gap: 14px; margin-top: 1.4rem; list-style: none; padding: 0">
        @foreach ($this->addresses as $address)
            <li class="card" style="padding: 18px" wire:key="address-{{ $address->id }}">
                <div style="display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between">
                    <div>
                        <b>{{ $address->recipient_name }}</b>
                        @if ($address->is_default) <span class="status-pill" style="color: var(--accent-deep)">Principal</span> @endif
                        <div>{{ $address->line1 }}@if ($address->line2), {{ $address->line2 }}@endif</div>
                        <div class="muted" style="font-size: 13px">{{ $address->city }} · Cel. {{ $address->phone }}</div>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-start">
                        @unless ($address->is_default)
                            <button type="button" class="btn btn-sm" wire:click="makeDefault({{ $address->id }})">Hacer principal</button>
                        @endunless
                        <button type="button" class="btn btn-sm" wire:click="edit({{ $address->id }})">Editar</button>
                        <button type="button" class="btn btn-sm" wire:click="remove({{ $address->id }})" wire:confirm="¿Eliminar esta dirección?">Eliminar</button>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>

    @if ($showForm)
        <form class="card" style="padding: 22px; margin-top: 20px; display: grid; gap: 14px; max-width: 560px" wire:submit="save">
            <h2 style="font-size: 1.3rem; font-weight: 700">{{ $editingId ? 'Editar dirección' : 'Nueva dirección' }}</h2>
            <div>
                <label for="a-name">Nombre de quien recibe</label>
                <input id="a-name" class="input" type="text" wire:model="name" autocomplete="name" required>
                @error('name') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="a-phone">Celular</label>
                <input id="a-phone" class="input" type="tel" wire:model="phone" autocomplete="tel-national" inputmode="numeric" placeholder="987 654 321" required>
                @error('phone') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="a-ubigeo">Distrito</label>
                <select id="a-ubigeo" class="select" style="border-radius: 14px; width: 100%" wire:model="ubigeo" required>
                    <option value="">Elige tu distrito</option>
                    @foreach ($this->zones as $zone)
                        <optgroup label="{{ $zone->name }}">
                            @forelse ($zone->districts as $district)
                                <option value="{{ $district->ubigeo }}">{{ $district->name }}</option>
                            @empty
                                <option disabled>Próximamente</option>
                            @endforelse
                        </optgroup>
                    @endforeach
                </select>
                @error('ubigeo') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="a-line1">Dirección</label>
                <input id="a-line1" class="input" type="text" wire:model="line1" autocomplete="address-line1" placeholder="Calle, número, departamento" required>
                @error('line1') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="a-line2">Referencia (opcional)</label>
                <input id="a-line2" class="input" type="text" wire:model="line2" autocomplete="address-line2">
                @error('line2') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div style="display: flex; gap: 10px">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <button type="button" class="btn" wire:click="cancelForm">Cancelar</button>
            </div>
        </form>
    @else
        <button type="button" class="btn btn-primary" style="margin-top: 20px" wire:click="add">Agregar dirección</button>
    @endif
</div>
