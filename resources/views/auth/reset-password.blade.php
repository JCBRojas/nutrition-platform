<x-auth-shell title="Nueva contraseña">
    <h2>Nueva contraseña</h2>
    <p class="lead">Elige una contraseña nueva para tu cuenta.</p>

    <x-auth-alerts />

    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="field">
            <label for="email">Correo electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}"
                autocomplete="username" required
                @error('email') aria-invalid="true" @enderror>
        </div>

        <div class="field">
            <label for="password">Contraseña nueva</label>
            <div class="control">
                <input id="password" type="password" name="password"
                    autocomplete="new-password" required autofocus
                    aria-describedby="password-hint"
                    @error('password') aria-invalid="true" @enderror>
                <button type="button" class="reveal" data-reveal="password" aria-controls="password" aria-pressed="false">
                    Mostrar
                </button>
            </div>
            <p class="hint" id="password-hint">Mínimo 8 caracteres.</p>
        </div>

        <div class="field">
            <label for="password_confirmation">Confirmar contraseña</label>
            <div class="control">
                <input id="password_confirmation" type="password" name="password_confirmation"
                    autocomplete="new-password" required>
                <button type="button" class="reveal" data-reveal="password_confirmation" aria-controls="password_confirmation" aria-pressed="false">
                    Mostrar
                </button>
            </div>
        </div>

        <button type="submit" class="submit">Guardar contraseña</button>
    </form>

    <a class="back" href="{{ route('login') }}">Volver a iniciar sesión</a>
</x-auth-shell>
