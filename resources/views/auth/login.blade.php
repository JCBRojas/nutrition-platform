@php
    $now = now();
    $labels = config('timeFood.labels');
    $services = collect(config('timeFood.schedule', []))
        ->map(fn ($time, $key) => [
            'label' => $labels[$key] ?? ucfirst($key),
            'time' => $time,
            'at' => $now->copy()->setTimeFromTimeString($time),
        ])
        ->values();
    $nextIndex = $services->search(fn ($s) => $s['at']->greaterThanOrEqualTo($now));
    $next = $nextIndex === false ? null : $services[$nextIndex];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Iniciar sesión | NutriTrack</title>

    {{-- Aplica el tema guardado antes del primer pintado (evita parpadeo) --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('nt-theme');
                if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t;
            } catch (e) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #e9efec;
            --surface: #ffffff;
            --ink: #15302a;
            --muted: #5b6f69;
            --line: #c9d6d1;
            --accent: #0f7b6c;
            --accent-hover: #0b6559;
            --on-accent: #ffffff;
            --past: #8fa39c;
            --error-bg: #fbeaea;
            --error-ink: #9b1c1c;
            --focus: #0f7b6c;
            --shadow: 0 1px 2px rgba(21, 48, 42, .06), 0 12px 32px -12px rgba(21, 48, 42, .18);
        }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --bg: #0e1a17;
                --surface: #15241f;
                --ink: #e4eeea;
                --muted: #93a8a1;
                --line: #24362f;
                --accent: #3fbfa8;
                --accent-hover: #5fd1bc;
                --on-accent: #08231d;
                --past: #4f655e;
                --error-bg: #3a1a1a;
                --error-ink: #f4b4b4;
                --focus: #5fd1bc;
                --shadow: 0 1px 2px rgba(0, 0, 0, .3), 0 16px 40px -16px rgba(0, 0, 0, .6);
            }
        }
        :root[data-theme="dark"] {
            --bg: #0e1a17;
            --surface: #15241f;
            --ink: #e4eeea;
            --muted: #93a8a1;
            --line: #24362f;
            --accent: #3fbfa8;
            --accent-hover: #5fd1bc;
            --on-accent: #08231d;
            --past: #4f655e;
            --error-bg: #3a1a1a;
            --error-ink: #f4b4b4;
            --focus: #5fd1bc;
            --shadow: 0 1px 2px rgba(0, 0, 0, .3), 0 16px 40px -16px rgba(0, 0, 0, .6);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--ink);
            font-family: 'Atkinson Hyperlegible', system-ui, sans-serif;
            font-size: 1rem;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 440px);
            gap: clamp(2rem, 6vw, 6rem);
            align-items: center;
            max-width: 1120px;
            margin: 0 auto;
            padding: 2rem clamp(1rem, 4vw, 3rem);
        }

        /* ---------- Marca + servicios del día ---------- */
        .brand {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 2.5rem;
        }
        .brand-mark {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: .75rem;
            background: var(--accent);
            display: grid;
            place-items: center;
        }
        .brand-name { font-size: 1.375rem; font-weight: 700; letter-spacing: -.01em; }
        .brand-mobile { display: none; }

        .services h1 {
            font-size: clamp(2rem, 4vw, 3rem);
            line-height: 1.1;
            letter-spacing: -.02em;
            margin: 0 0 .5rem;
            max-width: 14ch;
        }
        .services .date { color: var(--muted); margin: 0 0 2rem; }

        .timeline {
            list-style: none;
            margin: 0;
            padding: 0;
            border-left: 2px solid var(--line);
        }
        .timeline li {
            position: relative;
            display: grid;
            grid-template-columns: 6.5rem minmax(0, 1fr) auto;
            align-items: baseline;
            gap: 1rem;
            padding: .5rem 0 .5rem 1.5rem;
            font-variant-numeric: tabular-nums;
        }
        .timeline li::before {
            content: '';
            position: absolute;
            left: -7px;
            top: 50%;
            width: 12px;
            height: 12px;
            margin-top: -6px;
            border-radius: 50%;
            background: var(--bg);
            border: 2px solid var(--line);
        }
        .timeline time { color: var(--muted); white-space: nowrap; }
        .timeline .past { color: var(--past); }
        .timeline .past time { color: var(--past); }
        .timeline .past::before { background: var(--line); }
        .timeline .next { font-weight: 700; font-size: 1.25rem; }
        .timeline .next time { color: var(--ink); }
        .timeline .next::before {
            background: var(--accent);
            border-color: var(--accent);
            box-shadow: 0 0 0 5px color-mix(in srgb, var(--accent) 22%, transparent);
        }
        .timeline .tag {
            font-size: .875rem;
            font-weight: 700;
            color: var(--accent);
        }
        .timeline .done { font-size: .875rem; }

        /* ---------- Formulario ---------- */
        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 1.25rem;
            padding: clamp(1.5rem, 4vw, 2.5rem);
            box-shadow: var(--shadow);
        }
        .card h2 { font-size: 1.5rem; margin: 0 0 .25rem; letter-spacing: -.01em; }
        .card .lead { color: var(--muted); margin: 0 0 1.75rem; }

        .field { margin-bottom: 1.25rem; }
        .field label { display: block; font-weight: 700; margin-bottom: .375rem; }
        .control { position: relative; }
        .field input[type="email"],
        .field input[type="password"],
        .field input[type="text"] {
            width: 100%;
            font: inherit;
            color: var(--ink);
            background: var(--bg);
            border: 1.5px solid var(--line);
            border-radius: .75rem;
            padding: .75rem 1rem;
            transition: border-color .15s;
        }
        .field input:hover { border-color: var(--muted); }
        .field input:focus { outline: none; border-color: var(--focus); box-shadow: 0 0 0 3px color-mix(in srgb, var(--focus) 30%, transparent); }
        .field input[aria-invalid="true"] { border-color: var(--error-ink); }
        .control input { padding-right: 5.5rem !important; }
        .reveal {
            position: absolute;
            right: .375rem;
            top: 50%;
            transform: translateY(-50%);
            font: inherit;
            font-size: .875rem;
            font-weight: 700;
            color: var(--accent);
            background: none;
            border: 0;
            border-radius: .5rem;
            padding: .375rem .625rem;
            cursor: pointer;
        }

        .row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            font-size: .9375rem;
        }
        .check { display: inline-flex; align-items: center; gap: .5rem; cursor: pointer; }
        .check input { width: 1.125rem; height: 1.125rem; accent-color: var(--accent); }

        a { color: var(--accent); text-underline-offset: 3px; }
        a:hover { color: var(--accent-hover); }

        .submit {
            width: 100%;
            font: inherit;
            font-weight: 700;
            font-size: 1.0625rem;
            color: var(--on-accent);
            background: var(--accent);
            border: 0;
            border-radius: .75rem;
            padding: .875rem 1rem;
            cursor: pointer;
            transition: background .15s;
        }
        .submit:hover { background: var(--accent-hover); }

        .alert {
            border-radius: .75rem;
            padding: .75rem 1rem;
            margin-bottom: 1.25rem;
            font-size: .9375rem;
        }
        .alert-error { background: var(--error-bg); color: var(--error-ink); }
        .alert-status { background: color-mix(in srgb, var(--accent) 14%, transparent); color: var(--ink); }
        .alert ul { margin: 0; padding-left: 1.125rem; }

        :focus-visible { outline: 3px solid var(--focus); outline-offset: 2px; }
        .field input:focus-visible { outline: none; }

        /* ---------- Tema ---------- */
        .theme-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font: inherit;
            font-size: .875rem;
            font-weight: 700;
            color: var(--ink);
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: .5rem .875rem;
            cursor: pointer;
        }
        .theme-toggle svg { width: 1rem; height: 1rem; }
        .icon-sun { display: none; }
        :root[data-theme="dark"] .icon-sun { display: block; }
        :root[data-theme="dark"] .icon-moon { display: none; }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) .icon-sun { display: block; }
            :root:not([data-theme="light"]) .icon-moon { display: none; }
        }

        footer { color: var(--muted); font-size: .8125rem; margin-top: 1.25rem; text-align: center; }

        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

        /* ---------- Móvil: formulario primero, línea compacta ---------- */
        @media (max-width: 860px) {
            .page { grid-template-columns: minmax(0, 1fr); gap: 2rem; padding-top: 4.5rem; align-items: start; }
            .auth { order: -1; }
            .services h1 { font-size: 1.75rem; max-width: none; }
            .services .brand { display: none; }
            .brand-mobile { display: flex; margin-bottom: 1.25rem; }
            .timeline li { grid-template-columns: 5.5rem minmax(0, 1fr) auto; padding-top: .375rem; padding-bottom: .375rem; }
            .timeline .next { font-size: 1.125rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; }
        }
    </style>
</head>

<body>
    <button type="button" class="theme-toggle" id="themeToggle" aria-label="Cambiar tema">
        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg>
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        <span id="themeLabel">Modo oscuro</span>
    </button>

    <main class="page">
        {{-- ===================== SERVICIOS DEL DÍA ===================== --}}
        <section class="services" aria-labelledby="services-title">
            <div class="brand">
                <span class="brand-mark" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--on-accent)" stroke-width="2.2" stroke-linecap="round">
                        <path d="M3 13h18M5 13a7 7 0 0114 0M12 6V4M2 17h20"/>
                    </svg>
                </span>
                <span class="brand-name">NutriTrack</span>
            </div>

            <h1 id="services-title">
                @if ($next)
                    Próximo servicio: {{ $next['label'] }}, {{ \Carbon\Carbon::createFromFormat('H:i', $next['time'])->format('g:i a') }}
                @else
                    Servicios del día cerrados
                @endif
            </h1>
            <p class="date">{{ ucfirst($now->locale('es')->isoFormat('dddd D [de] MMMM')) }}</p>

            <ol class="timeline" aria-label="Horario de servicios de hoy">
                @foreach ($services as $i => $service)
                    @php
                        $state = $nextIndex === false || $i < $nextIndex ? 'past' : ($i === $nextIndex ? 'next' : 'upcoming');
                    @endphp
                    <li class="{{ $state }}" @if ($state === 'next') aria-current="time" @endif>
                        <time datetime="{{ $service['time'] }}">{{ \Carbon\Carbon::createFromFormat('H:i', $service['time'])->format('g:i a') }}</time>
                        <span>{{ $service['label'] }}</span>
                        @if ($state === 'next')
                            <span class="tag">Próximo</span>
                        @elseif ($state === 'past')
                            <span class="sr-only">Hora ya pasada</span>
                        @else
                            <span></span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- ===================== FORMULARIO ===================== --}}
        <section class="auth" aria-labelledby="login-title">
            <div class="brand brand-mobile" aria-hidden="true">
                <span class="brand-mark">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--on-accent)" stroke-width="2.2" stroke-linecap="round">
                        <path d="M3 13h18M5 13a7 7 0 0114 0M12 6V4M2 17h20"/>
                    </svg>
                </span>
                <span class="brand-name">NutriTrack</span>
            </div>
            <div class="card">
                <h2 id="login-title">Iniciar sesión</h2>
                <p class="lead">Usa el correo y la contraseña de tu cuenta.</p>

                @if ($errors->any())
                    <div class="alert alert-error" role="alert">
                        @if ($errors->count() === 1)
                            {{ $errors->first() }}
                        @else
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                @if (session('status'))
                    <div class="alert alert-status" role="status">{{ session('status') }}</div>
                @endif

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
                            <button type="button" class="reveal" id="reveal" aria-controls="password" aria-pressed="false">
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
            </div>

            <footer>© {{ date('Y') }} NutriTrack. Gestión de dietas hospitalarias.</footer>
        </section>
    </main>

    <script>
        (function () {
            var root = document.documentElement;
            var toggle = document.getElementById('themeToggle');
            var label = document.getElementById('themeLabel');
            var media = window.matchMedia('(prefers-color-scheme: dark)');

            function current() {
                return root.dataset.theme || (media.matches ? 'dark' : 'light');
            }
            function render() {
                var dark = current() === 'dark';
                label.textContent = dark ? 'Modo claro' : 'Modo oscuro';
                toggle.setAttribute('aria-label', dark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
            }

            toggle.addEventListener('click', function () {
                var next = current() === 'dark' ? 'light' : 'dark';
                root.dataset.theme = next;
                try { localStorage.setItem('nt-theme', next); } catch (e) {}
                render();
            });
            media.addEventListener('change', render);
            render();

            var reveal = document.getElementById('reveal');
            var password = document.getElementById('password');
            reveal.addEventListener('click', function () {
                var show = password.type === 'password';
                password.type = show ? 'text' : 'password';
                reveal.textContent = show ? 'Ocultar' : 'Mostrar';
                reveal.setAttribute('aria-pressed', String(show));
            });
        })();
    </script>
</body>
</html>
