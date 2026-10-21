@props(['title', 'showcase' => false])
@php
    $contact = config('landing.contact');
    $onDemoPage = request()->routeIs('demo.*');
@endphp
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
        (function() {
            try {
                var t = localStorage.getItem('nt-theme');
                if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t;
            } catch (e) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --bg: #EEF0FA;
            --surface: #ffffff;
            --ink: #1F2340;
            --muted: #5E6384;
            --line: #DCDFF0;
            --accent: #5B4CC4;
            --accent-hover: #4A3BB0;
            --on-accent: #ffffff;
            --error-bg: #FDECEC;
            --error-ink: #B91C1C;
            --focus: #6C5DD3;
            --shadow: 0 1px 2px rgba(31, 35, 64, .06), 0 12px 32px -12px rgba(31, 35, 64, .18);
        }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --bg: #1C1B33;
                --surface: #28264A;
                --ink: #ECEBFA;
                --muted: #A9A7C9;
                --line: #3A3766;
                --accent: #A79DF0;
                --accent-hover: #C9C2FA;
                --on-accent: #1C1B33;
                --error-bg: #3A1A1F;
                --error-ink: #F87171;
                --focus: #C9C2FA;
                --shadow: 0 1px 2px rgba(0, 0, 0, .3), 0 16px 40px -16px rgba(0, 0, 0, .6);
            }
        }

        :root[data-theme="dark"] {
            --bg: #1C1B33;
            --surface: #28264A;
            --ink: #ECEBFA;
            --muted: #A9A7C9;
            --line: #3A3766;
            --accent: #A79DF0;
            --accent-hover: #C9C2FA;
            --on-accent: #1C1B33;
            --error-bg: #3A1A1F;
            --error-ink: #F87171;
            --focus: #C9C2FA;
            --shadow: 0 1px 2px rgba(0, 0, 0, .3), 0 16px 40px -16px rgba(0, 0, 0, .6);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

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
        .intro {
            animation: rise .6s ease-out both;
        }

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

        .hero-mark svg {
            width: 3.75rem;
            height: 3.75rem;
            overflow: visible;
        }

        .hero-mark .steam {
            stroke-dasharray: 6;
            stroke-dashoffset: 6;
            opacity: 0;
            animation: steam 2.4s ease-in-out infinite;
        }

        .hero-mark .steam:nth-of-type(2) {
            animation-delay: .4s;
        }

        .hero-mark .steam:nth-of-type(3) {
            animation-delay: .8s;
        }

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

        .features li:nth-child(1) {
            animation-delay: .15s;
        }

        .features li:nth-child(2) {
            animation-delay: .3s;
        }

        .features li:nth-child(3) {
            animation-delay: .45s;
        }

        .features svg {
            flex-shrink: 0;
            width: 1.25rem;
            height: 1.25rem;
            color: var(--accent);
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        @keyframes pulse {
            0% {
                opacity: .5;
                transform: scale(1);
            }

            70%,
            100% {
                opacity: 0;
                transform: scale(1.18);
            }
        }

        @keyframes steam {
            0% {
                stroke-dashoffset: 6;
                opacity: 0;
                transform: translateY(2px);
            }

            40% {
                opacity: 1;
            }

            100% {
                stroke-dashoffset: -6;
                opacity: 0;
                transform: translateY(-3px);
            }
        }

        @keyframes rise {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        /* ---------- Formulario ---------- */
        .auth {
            animation: rise .6s .1s ease-out both;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 1.25rem;
            padding: clamp(1.5rem, 4vw, 2.5rem);
            box-shadow: var(--shadow);
        }

        .card h2 {
            font-size: 1.5rem;
            margin: 0 0 .25rem;
            letter-spacing: -.01em;
        }

        .card .lead {
            color: var(--muted);
            margin: 0 0 1.75rem;
        }

        .field {
            margin-bottom: 1.25rem;
        }

        .field label {
            display: block;
            font-weight: 700;
            margin-bottom: .375rem;
        }

        .field .hint {
            color: var(--muted);
            font-size: .875rem;
            margin: .375rem 0 0;
        }

        .control {
            position: relative;
        }

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

        .field input:hover {
            border-color: var(--muted);
        }

        .field input:focus {
            outline: none;
            border-color: var(--focus);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--focus) 30%, transparent);
        }

        .field input[aria-invalid="true"] {
            border-color: var(--error-ink);
        }

        .control input {
            padding-right: 5.5rem !important;
        }

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

        .check {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            cursor: pointer;
        }

        .check input {
            width: 1.125rem;
            height: 1.125rem;
            accent-color: var(--accent);
        }

        a {
            color: var(--accent);
            text-underline-offset: 3px;
        }

        a:hover {
            color: var(--accent-hover);
        }

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

        .submit:hover {
            background: var(--accent-hover);
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 1.25rem;
            font-size: .9375rem;
        }

        .alert {
            border-radius: .75rem;
            padding: .75rem 1rem;
            margin-bottom: 1.25rem;
            font-size: .9375rem;
        }

        .alert-error {
            background: var(--error-bg);
            color: var(--error-ink);
        }

        .alert-status {
            background: color-mix(in srgb, var(--accent) 14%, transparent);
            color: var(--ink);
        }

        .alert ul {
            margin: 0;
            padding-left: 1.125rem;
        }

        :focus-visible {
            outline: 3px solid var(--focus);
            outline-offset: 2px;
        }

        .field input:focus-visible {
            outline: none;
        }

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

        .theme-toggle svg {
            width: 1rem;
            height: 1rem;
        }

        .icon-sun {
            display: none;
        }

        :root[data-theme="dark"] .icon-sun {
            display: block;
        }

        :root[data-theme="dark"] .icon-moon {
            display: none;
        }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) .icon-sun {
                display: block;
            }

            :root:not([data-theme="light"]) .icon-moon {
                display: none;
            }
        }

        /* ---------- Llamados a la acción (marca) ---------- */
        .cta-row {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-top: 1.75rem;
        }

        .cta,
        .cta-ghost {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-weight: 700;
            border-radius: .75rem;
            padding: .75rem 1.125rem;
            text-decoration: none;
            transition: background .15s, color .15s, border-color .15s;
        }

        .cta {
            background: var(--ink);
            color: var(--bg);
        }

        .cta:hover {
            background: var(--accent);
            color: var(--on-accent);
        }

        .cta-ghost {
            border: 1.5px solid var(--line);
            color: var(--ink);
        }

        .cta-ghost:hover {
            border-color: var(--ink);
            color: var(--ink);
        }

        .cta svg,
        .cta-ghost svg {
            width: 1.125rem;
            height: 1.125rem;
        }

        .demo-hint {
            text-align: center;
            margin: 1.25rem 0 0;
            font-size: .9375rem;
            color: var(--muted);
        }

        /* ---------- Vitrina de la plataforma (capturas) ---------- */
        .showcase {
            max-width: 1120px;
            margin: 0 auto;
            padding: 3rem clamp(1rem, 4vw, 3rem) 1rem;
        }

        .showcase-head {
            max-width: 680px;
            margin-bottom: 2rem;
        }

        .eyebrow {
            font-size: .875rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--accent);
            margin: 0 0 .5rem;
        }

        .showcase-head h2 {
            font-size: clamp(1.75rem, 3.6vw, 2.5rem);
            line-height: 1.15;
            margin: 0 0 .75rem;
        }

        .showcase-head p {
            color: var(--muted);
            margin: 0;
            font-size: 1.0625rem;
        }

        .sc-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 1.25rem;
        }

        .sc-card {
            grid-column: span 3;
            position: relative;
            display: flex;
            flex-direction: column;
            background: color-mix(in srgb, var(--surface) 55%, var(--bg));
            border: 1px solid color-mix(in srgb, var(--line) 60%, transparent);
            border-radius: 1.25rem;
            padding: 1.75rem 1.75rem 0;
            overflow: hidden;
            scroll-margin-top: 1.5rem;
        }

        .sc-card.wide {
            grid-column: span 6;
            flex-direction: row;
            align-items: stretch;
            gap: 2rem;
        }

        .sc-card.third {
            grid-column: span 2;
        }

        .sc-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .sc-card.wide .sc-top {
            flex: 0 0 34%;
            flex-direction: column;
            justify-content: center;
            padding-bottom: 1.75rem;
        }

        .sc-card h3 {
            margin: 0;
            font-size: clamp(1.25rem, 2.2vw, 1.625rem);
            line-height: 1.2;
        }

        .sc-card.third h3 {
            font-size: 1.1875rem;
        }

        .sc-card .sc-sub {
            display: block;
            margin-top: .5rem;
            font-size: .9375rem;
            font-weight: 400;
            color: var(--muted);
            line-height: 1.5;
        }

        .sc-badge {
            display: inline-block;
            margin-bottom: .5rem;
            padding: .125rem .5rem;
            border-radius: 999px;
            background: #FEF3E2;
            color: #92400E;
            font-size: .75rem;
            font-weight: 700;
        }

        .sc-arrow {
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: var(--ink);
            color: var(--bg);
            transition: transform .2s ease, background .15s;
        }

        .sc-arrow:hover {
            background: var(--accent);
            color: var(--on-accent);
            transform: translateX(3px);
        }

        .sc-arrow svg {
            width: 1.125rem;
            height: 1.125rem;
        }

        .sc-shot {
            margin-top: auto;
            height: 18rem;
            border-radius: .875rem .875rem 0 0;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 -1px 0 color-mix(in srgb, var(--line) 70%, transparent), 0 18px 40px -20px rgba(31, 35, 64, .35);
        }

        .sc-card.wide .sc-shot {
            flex: 1 1 auto;
            height: 22rem;
            margin-top: 0;
            align-self: flex-end;
        }

        .sc-card.third .sc-shot {
            height: 13rem;
        }

        /* La captura se muestra ampliada (solo la esquina superior izquierda), como un detalle legible */
        .sc-shot img {
            display: block;
            width: 165%;
            max-width: none;
            height: auto;
        }

        .sc-card.wide .sc-shot img {
            width: 120%;
        }

        .sc-card.third .sc-shot img {
            width: 190%;
        }

        /* ---------- Pie del sitio ---------- */
        .site-footer {
            margin-top: 3rem;
            border-top: 1px solid var(--line);
            background: color-mix(in srgb, var(--surface) 45%, var(--bg));
        }

        .footer-inner {
            max-width: 1120px;
            margin: 0 auto;
            padding: 2.5rem clamp(1rem, 4vw, 3rem) 1.5rem;
            display: grid;
            grid-template-columns: 1.4fr repeat(3, minmax(0, 1fr));
            gap: 2rem;
        }

        .footer-brand strong {
            font-size: 1.25rem;
        }

        .footer-brand p,
        .footer-col li,
        .footer-bottom {
            color: var(--muted);
            font-size: .9375rem;
        }

        .footer-col h3 {
            margin: 0 0 .75rem;
            font-size: .875rem;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .footer-col ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: .5rem;
        }

        .footer-col a {
            color: var(--ink);
            text-decoration: none;
        }

        .footer-col a:hover {
            color: var(--accent);
            text-decoration: underline;
        }

        .footer-bottom {
            max-width: 1120px;
            margin: 0 auto;
            padding: 1rem clamp(1rem, 4vw, 3rem) 2rem;
            border-top: 1px solid var(--line);
            font-size: .8125rem;
        }

        @media (max-width: 860px) {
            .sc-card,
            .sc-card.third,
            .sc-card.wide {
                grid-column: span 6;
            }

            .sc-card.wide {
                flex-direction: column;
                gap: 0;
            }

            .sc-card.wide .sc-top {
                flex-basis: auto;
                padding-bottom: 0;
                margin-bottom: 1.5rem;
            }

            .sc-shot,
            .sc-card.wide .sc-shot,
            .sc-card.third .sc-shot {
                height: 14rem;
            }

            .footer-inner {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .footer-brand {
                grid-column: span 2;
            }

            .cta-row {
                justify-content: center;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .sc-arrow {
                transition: none;
            }
        }

        /* ---------- Móvil: marca compacta arriba, formulario después ---------- */
        @media (max-width: 860px) {
            .page {
                grid-template-columns: minmax(0, 1fr);
                gap: 1.5rem;
                padding-top: 4.5rem;
                align-items: start;
            }

            .intro {
                text-align: center;
            }

            .hero-mark {
                width: 5rem;
                height: 5rem;
                border-radius: 1.25rem;
                margin: 0 auto 1rem;
            }

            .hero-mark::after {
                border-radius: 1.6rem;
            }

            .hero-mark svg {
                width: 2.75rem;
                height: 2.75rem;
            }

            .intro .description {
                margin: 0 auto;
            }

            .features {
                display: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation: none !important;
                transition: none !important;
            }

            .hero-mark .steam {
                opacity: 1;
                stroke-dashoffset: 0;
            }
        }
    </style>
</head>

<body>
    <button type="button" class="theme-toggle" id="themeToggle" aria-label="Cambiar tema">
        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            aria-hidden="true">
            <path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z" />
        </svg>
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            aria-hidden="true">
            <circle cx="12" cy="12" r="4" />
            <path
                d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
        </svg>
        <span id="themeLabel">Modo oscuro</span>
    </button>

    <main class="page">
        {{-- ===================== MARCA Y DESCRIPCIÓN ===================== --}}
        <section class="intro" aria-labelledby="brand-title">
            <div class="hero-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--on-accent)" stroke-width="1.8"
                    stroke-linecap="round">
                    <path class="steam" d="M9 3.5c-.6-.8.6-1.6 0-2.4" />
                    <path class="steam" d="M12 3.5c-.6-.8.6-1.6 0-2.4" />
                    <path class="steam" d="M15 3.5c-.6-.8.6-1.6 0-2.4" />
                    <path d="M3 15h18M5 15a7 7 0 0114 0M12 8V6.5M2 19h20" />
                </svg>
            </div>

            <h1 id="brand-title" class="brand-name">NutriTrack</h1>
            <p class="tagline">Control total de las dietas hospitalarias, en tiempo real.</p>
            <p class="description">
                Desde la prescripción nutricional hasta la entrega al paciente, NutriTrack centraliza todo el proceso en una sola plataforma. El equipo de nutrición actualiza las dietas, cocina recibe los cambios al instante y cada servicio queda registrado y confirmado, mejorando la seguridad alimentaria, la trazabilidad y la gestión administrativa.
            </p>

            <ul class="features">
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 20h9" />
                        <path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z" />
                    </svg>
                    Dietas por paciente y habitación con historial de modificaciones.
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M1 3h15v13H1zM16 8h4l3 3v5h-7z" />
                        <circle cx="5.5" cy="18.5" r="2.5" />
                        <circle cx="18.5" cy="18.5" r="2.5" />
                    </svg>
                    Control en tiempo real de producción, despacho y recepción.
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                    </svg>
                    Reportes automáticos para seguimiento nutricional y facturación.
                </li>
            </ul>

            @unless ($onDemoPage)
                <div class="cta-row">
                    <a href="{{ route('demo.create') }}" class="cta">
                        Solicitar demo
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12" /><polyline points="13 6 19 12 13 18" /></svg>
                    </a>
                    @if ($showcase)
                        <a href="#plataforma" class="cta-ghost">Ver la plataforma</a>
                    @endif
                </div>
            @endunless
        </section>

        {{-- ===================== FORMULARIO ===================== --}}
        <section class="auth">
            <div class="card">
                {{ $slot }}
            </div>

        </section>
    </main>

    {{-- ===================== VITRINA (capturas reales de la plataforma) ===================== --}}
    @if ($showcase)
        @php
            $arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12" /><polyline points="13 6 19 12 13 18" /></svg>';
        @endphp
        <section class="showcase" id="plataforma" aria-labelledby="showcase-title">
            <div class="showcase-head">
                <p class="eyebrow">La plataforma</p>
                <h2 id="showcase-title">Así se ve NutriTrack por dentro.</h2>
                <p>Nutrición, cocina y administración trabajan sobre la misma información, al mismo tiempo.</p>
            </div>

            <div class="sc-grid">
                <article class="sc-card" id="producto-dietas">
                    <div class="sc-top">
                        <h3>Prescribe y versiona cada dieta en un solo lugar.</h3>
                        <a href="{{ route('demo.create') }}" class="sc-arrow" aria-label="Solicitar demo del panel de dietas">{!! $arrow !!}</a>
                    </div>
                    <div class="sc-shot"><img src="{{ asset('images/demo/dietas.jpg') }}" alt="Panel de dietas activas con tiempos de comida, consistencia y estado del día" loading="lazy"></div>
                </article>

                <article class="sc-card" id="producto-despacho">
                    <div class="sc-top">
                        <h3>Cocina recibe los cambios al instante.</h3>
                        <a href="{{ route('demo.create') }}" class="sc-arrow" aria-label="Solicitar demo del despacho de cocina">{!! $arrow !!}</a>
                    </div>
                    <div class="sc-shot"><img src="{{ asset('images/demo/despacho.jpg') }}" alt="Pantalla de despacho de cocina con las dietas listas para despachar" loading="lazy"></div>
                </article>

                <article class="sc-card wide" id="producto-reportes">
                    <div class="sc-top">
                        <div>
                            <p class="eyebrow">Reportes</p>
                            <h3>Cada bandeja servida, contada y lista para reportar.
                                <span class="sc-sub">Producción diaria por tiempo de comida, pacientes del mes y Excel en un clic.</span>
                            </h3>
                        </div>
                        <a href="{{ route('demo.create') }}" class="sc-arrow" aria-label="Solicitar demo de los reportes">{!! $arrow !!}</a>
                    </div>
                    <div class="sc-shot"><img src="{{ asset('images/demo/reporte.jpg') }}" alt="Reporte nutricional con la producción diaria en barras y el total del mes por tiempo de comida" loading="lazy"></div>
                </article>

                <article class="sc-card third" id="producto-facturacion">
                    <div class="sc-top">
                        <h3><span class="sc-badge">Premium</span><br>Facturación del mes en un clic.</h3>
                    </div>
                    <div class="sc-shot"><img src="{{ asset('images/premium/facturacion.png') }}" alt="Precios por tipo de dieta y facturación del mes" loading="lazy"></div>
                </article>

                <article class="sc-card third" id="producto-despachos-dia">
                    <div class="sc-top">
                        <h3><span class="sc-badge">Premium</span><br>Despachos y entregas del día.</h3>
                    </div>
                    <div class="sc-shot"><img src="{{ asset('images/premium/despachos.png') }}" alt="Despachos del día por tiempo de comida y su estado de entrega" loading="lazy"></div>
                </article>

                <article class="sc-card third" id="producto-historial">
                    <div class="sc-top">
                        <h3><span class="sc-badge">Premium</span><br>Trazabilidad de cada cambio.</h3>
                    </div>
                    <div class="sc-shot"><img src="{{ asset('images/premium/historial.png') }}" alt="Historial de cambios de la dieta de un paciente" loading="lazy"></div>
                </article>
            </div>
        </section>
    @endif

    {{-- ===================== PIE DEL SITIO ===================== --}}
    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <strong>NutriTrack</strong>
                <p>Gestión de dietas hospitalarias, de la prescripción a la bandeja.</p>
            </div>
            <nav class="footer-col" aria-labelledby="footer-products">
                <h3 id="footer-products">Productos</h3>
                <ul>
                    <li><a href="{{ route('login') }}#producto-dietas">Panel de dietas</a></li>
                    <li><a href="{{ route('login') }}#producto-despacho">Despacho de cocina</a></li>
                    <li><a href="{{ route('login') }}#producto-reportes">Reportes</a></li>
                    <li><a href="{{ route('login') }}#producto-facturacion">Plan Premium</a></li>
                </ul>
            </nav>
            <div class="footer-col">
                <h3 id="footer-contact">Contacto</h3>
                <ul aria-labelledby="footer-contact">
                    <li><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></li>
                    <li><a href="tel:{{ preg_replace('/[^+0-9]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></li>
                    <li><a href="https://wa.me/{{ $contact['whatsapp'] }}" target="_blank" rel="noopener">WhatsApp</a></li>
                    <li>{{ $contact['city'] }}</li>
                </ul>
            </div>
            <nav class="footer-col" aria-labelledby="footer-company">
                <h3 id="footer-company">NutriTrack</h3>
                <ul>
                    <li><a href="{{ route('demo.create') }}">Solicitar demo</a></li>
                    <li><a href="{{ route('login') }}">Iniciar sesión</a></li>
                    <li><a href="{{ route('privacy.policy') }}">Política de datos</a></li>
                </ul>
            </nav>
        </div>
        <div class="footer-bottom">© {{ date('Y') }} NutriTrack. Todos los derechos reservados.</div>
    </footer>

    <script>
        (function() {
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

            toggle.addEventListener('click', function() {
                var next = current() === 'dark' ? 'light' : 'dark';
                root.dataset.theme = next;
                try {
                    localStorage.setItem('nt-theme', next);
                } catch (e) {}
                render();
            });
            media.addEventListener('change', render);
            render();

            // Botones «Mostrar/Ocultar» de los campos de contraseña
            document.querySelectorAll('[data-reveal]').forEach(function(button) {
                var input = document.getElementById(button.dataset.reveal);
                button.addEventListener('click', function() {
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
