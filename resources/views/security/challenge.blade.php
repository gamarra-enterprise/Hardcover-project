<x-shop-layout title="Verificación en dos pasos">
    <div class="card auth-card">
        <h1>Verificación en dos pasos</h1>
        <p class="muted">Escribe el código de 6 dígitos de tu app de autenticación, o uno de tus códigos de recuperación.</p>
        <form method="POST" action="{{ route('two-factor.verify') }}">
            @csrf
            <div>
                <label class="field-label" for="code">Código</label>
                <input id="code" class="input" type="text" name="code" autocomplete="one-time-code" required autofocus
                       aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}" aria-describedby="code-error">
                @error('code')<p id="code-error" class="field-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Verificar</button>
        </form>
    </div>
</x-shop-layout>
