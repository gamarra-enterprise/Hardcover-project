<div class="wrap" style="padding-block: 28px 0; max-width: 560px">
    <nav class="muted" style="font-size: 13px" aria-label="Ruta"><a href="{{ route('home') }}">Inicio</a> / Seguimiento</nav>
    <h1 style="font-size: clamp(2.2rem, 5vw, 3.2rem); font-weight: 800; letter-spacing: -.035em; margin-top: .3rem">Sigue tu pedido</h1>
    <p class="muted" style="margin-top: .5rem">Ingresa el código que recibiste al comprar y el correo con el que hiciste el pedido.</p>

    <form class="card" style="padding: 22px; display: grid; gap: 14px; margin-top: 22px" wire:submit="search" novalidate>
        <div class="field">
            <label for="code">Código del pedido</label>
            <input id="code" class="input mono" type="text" wire:model="code" placeholder="HB-261008-0042" autocomplete="off" required>
            @error('code') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="field">
            <label for="email">Correo electrónico</label>
            <input id="email" class="input" type="email" wire:model="email" autocomplete="email" required>
            @error('email') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn btn-dark" wire:loading.attr="disabled">Ver mi pedido</button>
    </form>
</div>
