<x-auth-shell title="Recuperar contraseña">
    <h2>Recuperar contraseña</h2>
    <p class="lead">Escribe el correo de tu cuenta y te enviaremos un enlace para crear una contraseña nueva.</p>

    <x-auth-alerts />

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <div class="field">
            <label for="email">Correo electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                autocomplete="username" required autofocus
                @error('email') aria-invalid="true" @enderror>
        </div>

        <button type="submit" class="submit">Enviar enlace</button>
    </form>

    <a class="back" href="{{ route('login') }}">Volver a iniciar sesión</a>
</x-auth-shell>
