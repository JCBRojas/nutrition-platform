@props(['title'])
{{-- Páginas de acceso (login, recuperar y restablecer contraseña): CSS propio, sin Tailwind ni Vite --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title }} | NutriTrack</title>

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

        /* ---------- Marca + descripción ---------- */
        .intro { animation: rise .6s ease-out both; }

        .hero-mark {
            position: relative;
            width: 7rem;
            height: 7rem;
            border-radius: 1.75rem;
            background: var(--accent);
            display: grid;
            place-items: center;
            margin-bottom: 1.75rem;
            box-shadow: 0 18px 40px -18px color-mix(in srgb, var(--accent) 80%, transparent);
            animation: float 4s ease-in-out infinite;
        }
        .hero-mark::after {
            content: '';
            position: absolute;
            inset: -6px;
            border-radius: 2.1rem;
            border: 2px solid var(--accent);
            opacity: 0;
            animation: pulse 3s ease-out infinite;
        }
        .hero-mark svg { width: 3.75rem; height: 3.75rem; overflow: visible; }
        .hero-mark .steam {
            stroke-dasharray: 6;
            stroke-dashoffset: 6;
            opacity: 0;
            animation: steam 2.4s ease-in-out infinite;
        }
        .hero-mark .steam:nth-of-type(2) { animation-delay: .4s; }
        .hero-mark .steam:nth-of-type(3) { animation-delay: .8s; }

        .intro .brand-name {
            display: block;
            font-size: clamp(2.25rem, 4.5vw, 3.25rem);
            font-weight: 700;
            letter-spacing: -.02em;
            line-height: 1.05;
        }
        .intro .tagline {
            font-size: clamp(1.125rem, 2vw, 1.375rem);
            font-weight: 700;
            color: var(--accent);
            margin: .5rem 0 1rem;
        }
        .intro .description {
            color: var(--muted);
            max-width: 46ch;
            margin: 0 0 1.5rem;
        }
        .features {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: .625rem;
        }
        .features li {
            display: flex;
            align-items: center;
            gap: .625rem;
            animation: rise .6s ease-out both;
        }
        .features li:nth-child(1) { animation-delay: .15s; }
        .features li:nth-child(2) { animation-delay: .3s; }
        .features li:nth-child(3) { animation-delay: .45s; }
        .features svg { flex-shrink: 0; width: 1.25rem; height: 1.25rem; color: var(--accent); }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        @keyframes pulse {
            0% { opacity: .5; transform: scale(1); }
            70%, 100% { opacity: 0; transform: scale(1.18); }
        }
        @keyframes steam {
            0% { stroke-dashoffset: 6; opacity: 0; transform: translateY(2px); }
            40% { opacity: 1; }
            100% { stroke-dashoffset: -6; opacity: 0; transform: translateY(-3px); }
        }
        @keyframes rise {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: none; }
        }

        /* ---------- Formulario ---------- */
        .auth { animation: rise .6s .1s ease-out both; }
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
        .field .hint { color: var(--muted); font-size: .875rem; margin: .375rem 0 0; }
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

        .back { display: block; text-align: center; margin-top: 1.25rem; font-size: .9375rem; }

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
            z-index: 10;
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

        /* ---------- Móvil: marca compacta arriba, formulario después ---------- */
        @media (max-width: 860px) {
            .page { grid-template-columns: minmax(0, 1fr); gap: 1.5rem; padding-top: 4.5rem; align-items: start; }
            .intro { text-align: center; }
            .hero-mark { width: 5rem; height: 5rem; border-radius: 1.25rem; margin: 0 auto 1rem; }
            .hero-mark::after { border-radius: 1.6rem; }
            .hero-mark svg { width: 2.75rem; height: 2.75rem; }
            .intro .description { margin: 0 auto; }
            .features { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .hero-mark .steam { opacity: 1; stroke-dashoffset: 0; }
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
        {{-- ===================== MARCA Y DESCRIPCIÓN ===================== --}}
        <section class="intro" aria-labelledby="brand-title">
            <div class="hero-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--on-accent)" stroke-width="1.8" stroke-linecap="round">
                    <path class="steam" d="M9 3.5c-.6-.8.6-1.6 0-2.4"/>
                    <path class="steam" d="M12 3.5c-.6-.8.6-1.6 0-2.4"/>
                    <path class="steam" d="M15 3.5c-.6-.8.6-1.6 0-2.4"/>
                    <path d="M3 15h18M5 15a7 7 0 0114 0M12 8V6.5M2 19h20"/>
                </svg>
            </div>

            <h1 id="brand-title" class="brand-name">NutriTrack</h1>
            <p class="tagline">Dietas hospitalarias, de la prescripción a la bandeja.</p>
            <p class="description">
                El equipo de nutrición registra y versiona la dieta de cada paciente, cocina ve los cambios
                al instante y despacha cada servicio, y la recepción queda confirmada para reportes y facturación.
            </p>

            <ul class="features">
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
                    Dietas por habitación con historial de cambios
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 3h15v13H1zM16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    Despacho y recepción de cada tiempo de comida
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Reportes nutricionales y facturación mensual
                </li>
            </ul>
        </section>

        {{-- ===================== FORMULARIO ===================== --}}
        <section class="auth">
            <div class="card">
                {{ $slot }}
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

            // Botones «Mostrar/Ocultar» de los campos de contraseña
            document.querySelectorAll('[data-reveal]').forEach(function (button) {
                var input = document.getElementById(button.dataset.reveal);
                button.addEventListener('click', function () {
                    var show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    button.textContent = show ? 'Ocultar' : 'Mostrar';
                    button.setAttribute('aria-pressed', String(show));
                });
            });
        })();
    </script>
</body>
</html>
