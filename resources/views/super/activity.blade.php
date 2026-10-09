<x-panel-layout title="Actividad" subtitle="Lo que hizo el personal en el panel, de lo más reciente a lo más antiguo.">
    <form method="GET" class="p-tools">
        <label class="p-field">Acción empieza por
            <input type="search" name="accion" value="{{ $action }}" placeholder="user, product, shipping…">
        </label>
        <button class="p-btn p-btn-primary">Filtrar</button>
    </form>

    <div class="p-table-wrap">
        <table class="p-table">
            <thead><tr><th>Fecha</th><th>Quién</th><th>Acción</th><th>Detalle</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</td>
                        <td>{{ $log->user?->name ?? 'Sistema' }}</td>
                        <td><span class="p-pill">{{ $log->action }}</span></td>
                        <td>{{ $log->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-muted" style="text-align: center; padding: 28px">Todavía no hay actividad registrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top: 16px">{{ $logs->links('pagination.panel') }}</div>
</x-panel-layout>
