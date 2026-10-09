<x-panel-layout title="Seguridad" subtitle="Verificación en dos pasos con una app de autenticación (Google Authenticator, Authy, 1Password…).">
    @if (session('notice'))<div class="p-notice" role="status">{{ session('notice') }}</div>@endif
    @if (session('error'))<div class="p-notice error" role="alert">{{ session('error') }}</div>@endif

    @if ($recoveryCodes)
        <section class="p-card" role="status">
            <h2>Guarda tus códigos de recuperación</h2>
            <p class="p-muted">Cada uno sirve una sola vez si pierdes el teléfono. No se volverán a mostrar.</p>
            <pre style="font-size: 1.05rem; line-height: 1.8; user-select: all">{{ implode("\n", $recoveryCodes) }}</pre>
        </section>
    @endif

    @if ($enabled)
        <section class="p-card">
            <h2>Activada <span class="p-pill done">Sí</span></h2>
            <p class="p-muted">Te quedan {{ $remaining }} códigos de recuperación.</p>
            <form method="POST" action="{{ route('security.disable') }}" class="p-tools" onsubmit="return confirm('¿Desactivar la verificación en dos pasos?')">
                @csrf @method('DELETE')
                <label class="p-field">Tu contraseña, para desactivarla
                    <input type="password" name="password" required autocomplete="current-password" style="border: 1px solid var(--line); border-radius: 8px; padding: 8px 10px">
                    @error('password')<span style="color: var(--bad)">{{ $message }}</span>@enderror
                </label>
                <button class="p-btn">Desactivar</button>
            </form>
        </section>
    @elseif ($pending)
        <section class="p-card">
            <h2>1. Escanea el código</h2>
            <div style="width: 200px; height: 200px">{!! $qr !!}</div>
            <p class="p-muted">O escribe esta clave en tu app: <code>{{ $user->two_factor_secret }}</code></p>
            <h2 style="margin-top: 18px">2. Escribe el código de 6 dígitos que muestra la app</h2>
            <form method="POST" action="{{ route('security.confirm') }}" class="p-tools">
                @csrf
                <label class="p-field">Código
                    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus>
                    @error('code')<span style="color: var(--bad)">{{ $message }}</span>@enderror
                </label>
                <button class="p-btn p-btn-primary">Activar</button>
            </form>
        </section>
    @else
        <section class="p-card">
            <h2>Desactivada</h2>
            <p class="p-muted">Protege el panel: además de la contraseña, se pedirá un código de tu teléfono al entrar.</p>
            <form method="POST" action="{{ route('security.start') }}">@csrf <button class="p-btn p-btn-primary">Configurar</button></form>
        </section>
    @endif
</x-panel-layout>
