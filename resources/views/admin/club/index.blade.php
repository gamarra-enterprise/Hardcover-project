<x-panel-layout title="Club de lectores" subtitle="Personas que pidieron recibir novedades.">
    <div class="p-stats"><div class="p-stat"><b>{{ $active }}</b>Suscripciones activas</div></div>
    <p><a class="p-btn" href="{{ route('admin.club.export') }}">Exportar activas (CSV)</a></p>

    <div class="p-table-wrap">
        <table class="p-table">
            <thead><tr><th>Correo</th><th>Desde</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse ($subscribers as $s)
                    <tr>
                        <td>{{ $s->email }}</td>
                        <td>{{ $s->subscribed_at->timezone(config('app.display_timezone'))->format('d/m/Y') }}</td>
                        <td><span class="p-pill {{ $s->isActive() ? 'done' : 'off' }}">{{ $s->isActive() ? 'Activa' : 'Dada de baja' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="p-muted" style="text-align: center; padding: 28px">Todavía no hay suscripciones.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top: 16px">{{ $subscribers->links('pagination.panel') }}</div>
</x-panel-layout>
