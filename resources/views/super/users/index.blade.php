@use('App\Enums\UserRole')

<x-panel-layout title="Usuarios y roles" subtitle="Quién entra al panel y con qué permisos.">
    @if (session('notice'))<div class="p-notice" role="status">{{ session('notice') }}</div>@endif
    @if (session('error'))<div class="p-notice error" role="alert">{{ session('error') }}</div>@endif

    <form method="GET" class="p-tools">
        <label class="p-field">Buscar
            <input type="search" name="q" value="{{ $search }}" placeholder="Nombre o correo">
        </label>
        <label class="p-field">Rol
            <select name="rol">
                <option value="">Todos</option>
                @foreach (UserRole::cases() as $r)
                    <option value="{{ $r->value }}" @selected($role === $r)>{{ $r->label() }}</option>
                @endforeach
            </select>
        </label>
        <button class="p-btn p-btn-primary">Filtrar</button>
    </form>

    <div class="p-table-wrap">
        <table class="p-table">
            <thead><tr><th>Usuario</th><th>Registro</th><th class="num">Pedidos</th><th>Rol</th></tr></thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td><b>{{ $user->name }}</b><br><span class="p-muted">{{ $user->email }}</span></td>
                        <td>{{ $user->created_at->timezone(config('app.display_timezone'))->format('d/m/Y') }}</td>
                        <td class="num">{{ $user->orders_count }}</td>
                        <td>
                            @if (auth()->user()->is($user))
                                <span class="p-pill">{{ $user->role->label() }} (tú)</span>
                            @else
                                <form method="POST" action="{{ route('super.users.role', $user) }}" style="display: flex; gap: 8px"
                                      onsubmit="return confirm('¿Cambiar el rol de {{ addslashes($user->name) }}?')">
                                    @csrf @method('PATCH')
                                    <select name="role" aria-label="Rol de {{ $user->name }}" style="min-width: 150px">
                                        @foreach (UserRole::cases() as $r)
                                            <option value="{{ $r->value }}" @selected($user->role === $r)>{{ $r->label() }}</option>
                                        @endforeach
                                    </select>
                                    <button class="p-btn">Guardar</button>
                                </form>
                                @if ($user->hasTwoFactor())
                                    <form method="POST" action="{{ route('super.users.2fa-reset', $user) }}" style="margin-top: 6px" onsubmit="return confirm('¿Restablecer la verificación en dos pasos de {{ addslashes($user->name) }}?')">
                                        @csrf @method('DELETE')
                                        <button class="p-btn">Restablecer 2 pasos</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div style="margin-top: 16px">{{ $users->links() }}</div>
</x-panel-layout>
