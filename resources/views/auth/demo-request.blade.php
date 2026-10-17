<x-auth-shell title="Solicitar demo">
    <h2 id="demo-title">Solicitar demo</h2>
    <p class="lead">Déjanos tus datos y te mostramos NutriTrack con un recorrido por las pantallas de tu equipo.</p>

    <x-auth-alerts />

    @unless (session('status'))
        <form method="POST" action="{{ route('demo.store') }}" novalidate>
            @csrf

            {{-- Campo trampa para bots: oculto para personas --}}
            <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">
                <label for="website">No llenar</label>
                <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="field">
                <label for="name">Nombre</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="120"
                    @error('name') aria-invalid="true" @enderror>
            </div>

            <div class="field">
                <label for="institution">Institución</label>
                <input id="institution" type="text" name="institution" value="{{ old('institution') }}" autocomplete="organization" required maxlength="160"
                    @error('institution') aria-invalid="true" @enderror>
            </div>

            <div class="field">
                <label for="position">Cargo <span class="hint-inline">(opcional)</span></label>
                <input id="position" type="text" name="position" value="{{ old('position') }}" autocomplete="organization-title" maxlength="120">
            </div>

            <div class="field">
                <label for="email">Correo electrónico</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required maxlength="160"
                    @error('email') aria-invalid="true" @enderror>
            </div>

            <div class="field">
                <label for="phone">Teléfono o WhatsApp</label>
                <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" required maxlength="30" placeholder="+57 300 123 4567"
                    @error('phone') aria-invalid="true" @enderror>
            </div>

            <div class="field">
                <label for="beds">Número de camas <span class="hint-inline">(opcional)</span></label>
                <input id="beds" type="number" name="beds" value="{{ old('beds') }}" min="1" inputmode="numeric"
                    @error('beds') aria-invalid="true" @enderror>
            </div>

            <div class="field">
                <label for="message">¿Qué te gustaría ver? <span class="hint-inline">(opcional)</span></label>
                <textarea id="message" name="message" rows="3" maxlength="2000">{{ old('message') }}</textarea>
            </div>

            <div class="row">
                <label class="check">
                    <input type="checkbox" name="consent" value="1" required @checked(old('consent'))>
                    <span>Autorizo el uso de mis datos para que me contacten, según la <a href="{{ route('privacy.policy') }}">política de datos</a>.</span>
                </label>
            </div>

            <button type="submit" class="submit">Enviar solicitud</button>
        </form>
    @endunless

    <a href="{{ route('login') }}" class="back">Volver a iniciar sesión</a>

    <style>
        .hint-inline { font-weight: 400; color: var(--muted); }
        .field input[type="tel"], .field input[type="number"], .field textarea {
            width: 100%; font: inherit; color: var(--ink); background: var(--bg);
            border: 1.5px solid var(--line); border-radius: .75rem; padding: .75rem 1rem;
        }
        .field textarea { resize: vertical; }
        .field input[type="tel"]:focus, .field input[type="number"]:focus, .field textarea:focus {
            outline: none; border-color: var(--focus); box-shadow: 0 0 0 3px color-mix(in srgb, var(--focus) 30%, transparent);
        }
        .check { align-items: flex-start; }
        .check input { margin-top: .2rem; flex: 0 0 auto; }
    </style>
</x-auth-shell>
