<x-app-layout>
    <div class="content">
        <div class="pa-card">
            <h1 class="card-title">Política de tratamiento de datos</h1>
            <p class="pa-intro">
                @if (auth()->user()->privacy_accepted_at)
                    La política se actualizó. Léela y acéptala de nuevo para seguir usando NutriTrack.
                @else
                    Antes de usar NutriTrack, lee y acepta la política de tratamiento de datos y el deber de confidencialidad.
                @endif
            </p>

            <div class="pa-text" tabindex="0" aria-label="Texto de la política">
                @include('legal.policy-text')
            </div>

            <form method="POST" action="{{ route('privacy.accept.store') }}" class="pa-form">
                @csrf
                <label class="pa-check">
                    <input type="checkbox" name="accept" value="1" required>
                    <span>Leí y acepto la política de tratamiento de datos y me comprometo a cumplir el deber de confidencialidad.</span>
                </label>
                @error('accept') <p class="pa-error">{{ $message }}</p> @enderror
                <div class="pa-actions">
                    <button type="submit" class="btn btn-primary">Aceptar y continuar</button>
                </div>
            </form>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">No acepto, cerrar sesión</button>
            </form>
        </div>
    </div>

    <style>
        .pa-card { max-width: 820px; background: var(--surface, #fff); border: 1px solid var(--border, #e2ddd6); border-radius: 12px; padding: 24px; }
        .pa-intro { color: var(--text-muted); margin: 4px 0 14px; }
        .pa-text { max-height: 52vh; overflow-y: auto; border: 1px solid var(--border, #e2ddd6); border-radius: 8px; padding: 4px 18px 14px; font-size: 14px; line-height: 1.55; }
        .pa-text h2 { font-size: 15px; font-weight: 700; margin: 16px 0 4px; }
        .pa-text ul { list-style: disc; padding-left: 20px; }
        .pa-text .policy-meta { color: var(--text-muted); font-size: 12.5px; }
        .pa-form { margin-top: 16px; }
        .pa-check { display: flex; gap: 10px; align-items: flex-start; font-weight: 600; }
        .pa-check input { width: 18px; height: 18px; margin-top: 2px; }
        .pa-error { color: #b42318; font-size: 13px; margin: 6px 0 0; }
        .pa-actions { margin: 14px 0 10px; }
    </style>
</x-app-layout>
