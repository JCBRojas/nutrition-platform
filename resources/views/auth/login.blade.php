<x-auth-shell title="Iniciar sesión">
    <h2 id="login-title">Iniciar sesión</h2>
    <p class="lead">Usa el correo y la contraseña de tu cuenta.</p>

    <x-auth-alerts />

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="field">
            <label for="email">Correo electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                autocomplete="username" required autofocus
                @error('email') aria-invalid="true" @enderror>
        </div>

        <div class="field">
            <label for="password">Contraseña</label>
            <div class="control">
                <input id="password" type="password" name="password"
                    autocomplete="current-password" required
                    @error('password') aria-invalid="true" @enderror>
                <button type="button" class="reveal" data-reveal="password" aria-controls="password" aria-pressed="false">
                    Mostrar
                </button>
            </div>
        </div>

        <div class="row">
            <label class="check">
                <input type="checkbox" name="remember" @checked(old('remember'))>
                Recordarme
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
            @endif
        </div>

        <button type="submit" class="submit">Iniciar sesión</button>
    </form>
</x-auth-shell>
