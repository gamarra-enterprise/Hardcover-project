<x-panel-layout title="Super administrador" subtitle="Cuentas, permisos y seguimiento del personal.">
    <div class="p-grid">
        <a class="p-card" href="{{ route('super.users.index') }}" style="text-decoration: none; color: inherit"><h2>Usuarios y roles</h2><p class="p-muted">Quién es cliente, administrador o super administrador.</p></a>
        <a class="p-card" href="{{ route('super.activity') }}" style="text-decoration: none; color: inherit"><h2>Actividad</h2><p class="p-muted">Qué hizo el personal en el panel.</p></a>
    </div>
    <p class="p-muted">Pronto: respaldos de la base de datos y pasarelas de pago.</p>
</x-panel-layout>
