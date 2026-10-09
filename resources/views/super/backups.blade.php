<x-panel-layout title="Respaldos" subtitle="Copias de la base de datos. Contienen datos de clientes: descárgalas solo a un lugar seguro.">
    @if (session('notice'))<div class="p-notice" role="status">{{ session('notice') }}</div>@endif
    @if (session('error'))<div class="p-notice error" role="alert">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('super.backups.store') }}" class="p-tools">
        @csrf
        <button class="p-btn p-btn-primary">Crear respaldo ahora</button>
    </form>

    <div class="p-table-wrap">
        <table class="p-table">
            <thead><tr><th>Archivo</th><th>Creado</th><th class="num">Tamaño</th><th></th></tr></thead>
            <tbody>
                @forelse ($backups as $b)
                    <tr>
                        <td>{{ $b['name'] }}</td>
                        <td>{{ $b['created_at']->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</td>
                        <td class="num">{{ number_format($b['size'] / 1048576, 2) }} MB</td>
                        <td style="display: flex; gap: 8px">
                            <a class="p-btn" href="{{ route('super.backups.download', $b['name']) }}">Descargar</a>
                            <form method="POST" action="{{ route('super.backups.destroy', $b['name']) }}" onsubmit="return confirm('¿Eliminar este respaldo?')">
                                @csrf @method('DELETE')
                                <button class="p-btn">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-muted" style="text-align: center; padding: 28px">Todavía no hay respaldos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <section class="p-card" style="margin-top: 18px">
        <h2>Restaurar</h2>
        <p class="p-muted">Restaurar reemplaza toda la base de datos, por eso solo se hace desde la consola del servidor:</p>
        <p><code>php artisan db:restore hardcover-AAAAMMDD-HHMMSS.dump</code></p>
    </section>
</x-panel-layout>
