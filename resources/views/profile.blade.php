<x-shop-layout title="Mi perfil">
    <div class="wrap" style="padding-block: 28px 48px">
        <h1 style="font-size: clamp(2rem, 4.5vw, 3rem); font-weight: 800; letter-spacing: -.035em">Mi perfil</h1>
        <x-shop.account-tabs active="profile" />

        <div class="card stack-card"><livewire:profile.update-profile-information-form /></div>
        <div class="card stack-card"><livewire:profile.update-password-form /></div>
        <div class="card stack-card"><livewire:profile.delete-user-form /></div>
    </div>
</x-shop-layout>
