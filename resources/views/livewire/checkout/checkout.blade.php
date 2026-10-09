@use('App\Support\Money')

<div class="wrap" style="padding-block: 28px 0">
    <nav class="muted" style="font-size: 13px" aria-label="Ruta"><a href="{{ route('home') }}">Inicio</a> / <a href="{{ route('cart') }}">Carrito</a> / Finalizar compra</nav>
    <h1 style="font-size: clamp(2.2rem, 5vw, 3.4rem); font-weight: 800; letter-spacing: -.035em; margin-top: .3rem">Finalizar compra</h1>

    <form class="cart-layout" wire:submit="place" novalidate>
        <div style="display: grid; gap: 28px">
            <section class="card" style="padding: 22px; display: grid; gap: 14px" aria-labelledby="contact-title">
                <h2 id="contact-title" style="font-size: 1.3rem; font-weight: 700; display: flex; align-items: center; gap: 12px"><span class="cs-n ok" aria-hidden="true">1</span>Tus datos</h2>

                <div class="field">
                    <label for="name">Nombre de quien recibe</label>
                    <input id="name" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" aria-describedby="name-error" class="input" type="text" wire:model.blur="name" autocomplete="name" required>
                    @error('name') <p class="field-error" id="name-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="email">Correo electrónico</label>
                        <input id="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="email-error" class="input" type="email" wire:model.blur="email" autocomplete="email" required>
                        <p class="muted" style="font-size: 12.5px">Aquí te avisamos de cada cambio de tu pedido.</p>
                        @error('email') <p class="field-error" id="email-error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="field">
                        <label for="phone">Celular</label>
                        <input id="phone" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}" aria-describedby="phone-error" class="input" type="tel" wire:model.blur="phone" autocomplete="tel-national" inputmode="numeric" placeholder="987 654 321" required>
                        @error('phone') <p class="field-error" id="phone-error" role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="card" style="padding: 22px; display: grid; gap: 14px" aria-labelledby="delivery-title">
                <h2 id="delivery-title" style="font-size: 1.3rem; font-weight: 700; display: flex; align-items: center; gap: 12px"><span class="cs-n ok" aria-hidden="true">2</span>Entrega</h2>

                <div class="field">
                    <label for="ubigeo">Distrito</label>
                    <select id="ubigeo" aria-invalid="{{ $errors->has('ubigeo') ? 'true' : 'false' }}" aria-describedby="ubigeo-error" class="select" style="border-radius: 14px; width: 100%" wire:model.live="ubigeo" required>
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
                        <optgroup label="Otras regiones">
                            <option disabled>Próximamente</option>
                        </optgroup>
                    </select>
                    <p class="muted" style="font-size: 12.5px">Por ahora enviamos solo a Lima. Los envíos a otras regiones llegarán pronto.</p>
                    @error('ubigeo') <p class="field-error" id="ubigeo-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="line1">Dirección</label>
                    <input id="line1" aria-invalid="{{ $errors->has('line1') ? 'true' : 'false' }}" aria-describedby="line1-error" class="input" type="text" wire:model.blur="line1" autocomplete="address-line1" placeholder="Calle, número, departamento" required>
                    @error('line1') <p class="field-error" id="line1-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="line2">Referencia <span class="muted">(opcional)</span></label>
                    <input id="line2" class="input" type="text" wire:model.blur="line2" autocomplete="address-line2" placeholder="Cerca de…">
                    @error('line2') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>

                @auth
                    <label class="switch-row" style="margin-top: .2rem">Guardar esta dirección en mi cuenta
                        <span class="switch"><input type="checkbox" wire:model="saveAddress"><span></span></span>
                    </label>
                @endauth
            </section>
        </div>

        <aside class="card" style="padding: 22px; display: grid; gap: 14px; align-self: start" aria-labelledby="summary-title">
            <h2 id="summary-title" style="font-size: 1.3rem; font-weight: 700">3. Tu pedido</h2>

            <ul class="mini-lines">
                @foreach ($this->summary->lines as $line)
                    <li>
                        <span>{{ $line->quantity }} × {{ $line->product->name }}</span>
                        <b class="num">{{ Money::format($line->total) }}</b>
                    </li>
                @endforeach
            </ul>

            <dl class="totals">
                <div><dt>Subtotal</dt><dd class="num">{{ Money::format($this->totals['subtotal']) }}</dd></div>
                <div>
                    <dt>Envío</dt>
                    <dd class="num">
                        @if (! $this->totals['quote'])
                            <span class="muted">Elige tu distrito</span>
                        @elseif ($this->totals['quote']->isFree)
                            <b style="color: var(--ok)">Gratis</b>
                        @else
                            {{ Money::format($this->totals['shipping_cost']) }}
                        @endif
                    </dd>
                </div>
                <div class="total"><dt>Total</dt><dd class="num">{{ Money::format($this->totals['total']) }}</dd></div>
            </dl>
            <p class="muted" style="font-size: 13px">Precios con IGV incluido ({{ Money::format($this->totals['tax']) }}).</p>

            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="place">
                <span wire:loading.remove wire:target="place">Confirmar pedido</span>
                <span wire:loading wire:target="place">Procesando…</span>
            </button>
            <p class="muted" style="font-size: 13px">Al confirmar se registra tu pedido. El stock se descuenta cuando se confirma el pago.</p>
        </aside>
    </form>
</div>
