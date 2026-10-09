<x-shop-layout title="Mi perfil">
    <div class="wrap" style="padding-block: 28px 48px">
        <h1 style="font-size: clamp(2rem, 4.5vw, 3rem); font-weight: 800; letter-spacing: -.035em">Mi perfil</h1>
        <p style="margin: .5rem 0 1.4rem"><a href="{{ route('account.orders') }}">Mis pedidos</a> · <a href="{{ route('account.addresses') }}">Mis direcciones</a></p>

        <div class="card stack-card"><livewire:profile.update-profile-information-form /></div>
        <div class="card stack-card"><livewire:profile.update-password-form /></div>
        <div class="card stack-card"><livewire:profile.delete-user-form /></div>
    </div>
</x-shop-layout>
