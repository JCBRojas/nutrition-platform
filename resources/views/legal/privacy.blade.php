<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Política de datos · NutriTrack</title>
    <style>
        :root { --bg: #EEF0FA; --surface: #FFFFFF; --text: #1F2340; --muted: #5E6384; --border: #DCDFF0; --accent: #5B4CC4; }
        @media (prefers-color-scheme: dark) { :root { --bg: #1C1B33; --surface: #28264A; --text: #ECEBFA; --muted: #A9A7C9; --border: #3A3766; --accent: #A79DF0; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 760px; margin: 0 auto; padding: 32px 16px 48px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 28px 24px; }
        h1 { margin: 0 0 4px; font-size: 1.5rem; }
        .policy h2 { font-size: 1.05rem; margin: 22px 0 6px; }
        .policy-meta, .back { color: var(--muted); font-size: .875rem; }
        .policy ul { padding-left: 20px; }
        a { color: var(--accent); }
    </style>
</head>
<body>
    <main>
        <div class="card">
            <h1>Política de tratamiento de datos personales</h1>
            @include('legal.policy-text')
        </div>
        <p class="back"><a href="{{ url('/') }}">← Volver al inicio de sesión</a></p>
    </main>
</body>
</html>
