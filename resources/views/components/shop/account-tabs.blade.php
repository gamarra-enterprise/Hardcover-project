@props(['active'])

<div style="margin-top: .6rem">
    <p class="muted">Hola, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</p>
    <nav style="display: flex; gap: .5rem; margin: 14px 0 20px; flex-wrap: wrap" aria-label="Mi cuenta">
        @foreach (['orders' => ['Mis pedidos', route('account.orders')], 'addresses' => ['Direcciones', route('account.addresses')], 'profile' => ['Perfil', route('profile')]] as $key => [$label, $url])
            <a class="chip" href="{{ $url }}" aria-pressed="{{ $active === $key ? 'true' : 'false' }}" @if ($active === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
</div>
