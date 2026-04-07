{{--
  navigation-menu.blade.php — NutriTrack Style
  Basado en Laravel Jetstream con los estilos del sistema NutriTrack.
  Fuentes requeridas en tu layout: Syne (700,800) + DM Sans (400,500)
  Agrega en app.blade.php dentro de <head>:
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
--}}

<style>
    /* ═══════════════════════════════════════════════
    NUTRITRACK NAV — Variables CSS
    ═══════════════════════════════════════════════ */
    :root {
        --nt-accent:        #2d6a4f;
        --nt-accent-light:  #d8f3dc;
        --nt-accent-hover:  #245c43;
        --nt-danger:        #e63946;
        --nt-danger-light:  #fef2f2;
        --nt-blue:          #4a7fcb;
        --nt-blue-light:    #ddeaf8;
        --nt-border:        #e5e7eb;
        --nt-bg:            #f5f3ef;
        --nt-surface:       #ffffff;
        --nt-surface2:      #f9fafb;
        --nt-text:          #111827;
        --nt-text-muted:    #6b7280;
        --nt-text-light:    #9ca3af;
        --nt-shadow:        0 1px 3px rgba(0,0,0,.07), 0 4px 16px rgba(0,0,0,.05);
        --nt-shadow-lg:     0 10px 25px rgba(0,0,0,.08), 0 4px 8px rgba(0,0,0,.04);
        --nt-radius:        8px;
    }

    /* ── BARRA DE NAVEGACIÓN PRINCIPAL ── */
    .nt-nav {
        position: sticky;
        top: 0;
        z-index: 100;
        background: var(--nt-surface);
        border-bottom: 1px solid var(--nt-border);
        font-family: 'DM Sans', sans-serif;
    }

    .nt-nav-inner {
        max-width: 1280px;
        margin: 0 auto;
        padding: 0 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 64px;
    }

    /* ── LOGO ── */
    .nt-logo {
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        flex-shrink: 0;
    }

    .nt-logo-dot {
        width: 8px;
        height: 8px;
        background: #52d68a;
        border-radius: 50%;
        box-shadow: 0 0 8px #52d68a;
        flex-shrink: 0;
    }

    .nt-logo-text {
        font-family: 'Syne', sans-serif;
        font-size: 18px;
        font-weight: 800;
        color: var(--nt-accent);
        letter-spacing: -0.4px;
    }

 /* ── LINKS DE NAVEGACIÓN (desktop) ── */
    .nt-nav-links {
        display: flex;
        align-items: stretch;
        gap: 0;
        margin-left: 40px;
        height: 64px;
        flex: 1;
    }

    .nt-nav-link {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 0 14px;
        height: 64px;
        color: var(--nt-text-muted);
        font-size: 13.5px;
        font-weight: 500;
        font-family: 'DM Sans', sans-serif;
        text-decoration: none;
        border-bottom: 2px solid transparent;
        margin-bottom: -1px;
        white-space: nowrap;
        transition: color .15s, border-color .15s;
        cursor: pointer;
        background: none;
        border-top: none;
        border-left: none;
        border-right: none;
    }

    .nt-nav-link svg {
        width: 15px;
        height: 15px;
        opacity: .65;
        flex-shrink: 0;
        transition: opacity .15s;
    }

    .nt-nav-link:hover {
        color: var(--nt-text);
        border-bottom-color: #d1d5db;
    }

    .nt-nav-link:hover svg { opacity: 1; }

    .nt-nav-link.active {
        color: var(--nt-accent);
        border-bottom-color: var(--nt-accent);
        font-weight: 600;
    }

    .nt-nav-link.active svg {
        opacity: 1;
        color: var(--nt-accent);
    }

 /* ── LADO DERECHO (acciones + user) ── */
    .nt-nav-right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }

    /* Separador entre acciones y user */
    .nt-nav-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        padding-right: 12px;
        border-right: 1px solid var(--nt-border);
        margin-right: 4px;
    }

    /* ── BOTONES ── */
    .nt-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border-radius: var(--nt-radius);
        font-family: 'DM Sans', sans-serif;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        border: none;
        transition: all .2s;
        white-space: nowrap;
        text-decoration: none;
    }

    .nt-btn svg { width: 13px; height: 13px; flex-shrink: 0; }

    .nt-btn-primary {
        background: var(--nt-accent);
        color: #fff;
    }
    .nt-btn-primary:hover {
        background: var(--nt-accent-hover);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(45,106,79,.3);
    }

    .nt-btn-ghost {
        background: transparent;
        color: var(--nt-text-muted);
        border: 1px solid var(--nt-border);
    }
    .nt-btn-ghost:hover {
        background: var(--nt-surface2);
        color: var(--nt-text);
    }

    .nt-btn-info {
        background: var(--nt-blue-light);
        color: var(--nt-blue);
        border: 1px solid #bfdbfe;
    }
    .nt-btn-info:hover { background: #c3d9f5; }

    /* ── DROPDOWN DE USUARIO ── */
    .nt-dropdown {
        position: relative;
    }

    .nt-dropdown-trigger {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 10px;
        border: 1px solid transparent;
        border-radius: var(--nt-radius);
        font-family: 'DM Sans', sans-serif;
        font-size: 13.5px;
        font-weight: 500;
        color: #374151;
        background: transparent;
        cursor: pointer;
        transition: background .15s, border-color .15s;
    }
    .nt-dropdown-trigger:hover {
        background: var(--nt-surface2);
        border-color: var(--nt-border);
    }

    /* Avatar circular con inicial */
    .nt-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--nt-accent);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Syne', sans-serif;
        font-weight: 700;
        font-size: 13px;
        color: #fff;
        flex-shrink: 0;
        transition: box-shadow .2s;
    }
    .nt-dropdown-trigger:hover .nt-avatar {
        box-shadow: 0 0 0 3px rgba(45,106,79,.2);
    }

    /* Foto de perfil (cuando existe) */
    .nt-avatar-img {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid transparent;
        transition: border-color .2s;
    }
    .nt-dropdown-trigger:hover .nt-avatar-img {
        border-color: #d1d5db;
    }

    .nt-user-info {
        display: flex;
        flex-direction: column;
        text-align: left;
    }
    .nt-user-name { font-size: 13px; color: #374151; font-weight: 500; line-height: 1.2; }
    .nt-user-role { font-size: 10.5px; color: var(--nt-text-light); text-transform: uppercase; letter-spacing: .5px; }

    .nt-chevron {
        width: 14px; height: 14px;
        color: var(--nt-text-light);
        transition: transform .2s;
        flex-shrink: 0;
    }

    /* Menú desplegable */
    .nt-dropdown-menu {
        display: none;
        position: absolute;
        right: 0;
        top: calc(100% + 8px);
        background: var(--nt-surface);
        border: 1px solid var(--nt-border);
        border-radius: 10px;
        box-shadow: var(--nt-shadow-lg);
        min-width: 200px;
        z-index: 200;
        overflow: hidden;
        animation: ntDropIn .15s ease;
    }
    @keyframes ntDropIn {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: none; }
    }

    /* Alpine.js: x-show reemplaza display manualmente si no usas Alpine */
    .nt-dropdown-menu.open { display: block; }

    .nt-dropdown-header {
        padding: 9px 14px 6px;
        font-size: 11px;
        color: var(--nt-text-light);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .6px;
    }

    .nt-dropdown-link {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 9px 14px;
        font-family: 'DM Sans', sans-serif;
        font-size: 13.5px;
        color: #374151;
        font-weight: 400;
        background: none;
        border: none;
        cursor: pointer;
        text-decoration: none;
        transition: background .12s;
        white-space: nowrap;
    }
    .nt-dropdown-link:hover { background: #f3f4f6; color: var(--nt-text); }
    .nt-dropdown-link.danger:hover { background: var(--nt-danger-light); color: var(--nt-danger); }

    .nt-dropdown-link svg { width: 14px; height: 14px; opacity: .6; flex-shrink: 0; }
    .nt-dropdown-link:hover svg { opacity: 1; }

    .nt-divider { height: 1px; background: var(--nt-border); margin: 4px 0; }

    /* ── DROPDOWN DE EQUIPOS ── */
    .nt-teams-dropdown { min-width: 240px; }

    /* ── HAMBURGER (solo móvil) ── */
    .nt-hamburger {
        display: none;
        align-items: center;
        justify-content: center;
        padding: 8px;
        border-radius: var(--nt-radius);
        background: none;
        border: none;
        cursor: pointer;
        color: #374151;
        transition: background .15s;
    }
    .nt-hamburger:hover { background: #f3f4f6; }
    .nt-hamburger svg { width: 22px; height: 22px; display: block; }

    /* ── MENÚ RESPONSIVE (mobile drawer) ── */
    .nt-mobile-menu {
        background: var(--nt-surface);
        border-top: 1px solid var(--nt-border);
        padding: 8px 0 16px;
        box-shadow: 0 8px 20px rgba(0,0,0,.08);
    }

    /* En Jetstream se usa x-show + Alpine; si usas puro CSS: */
    [x-cloak] { display: none !important; }

    .nt-mobile-nav-section {
        padding: 10px 16px 4px;
        font-size: 11px;
        font-weight: 600;
        color: var(--nt-text-light);
        text-transform: uppercase;
        letter-spacing: .8px;
    }

    .nt-mobile-nav-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        font-family: 'DM Sans', sans-serif;
        font-size: 14px;
        font-weight: 500;
        color: #374151;
        text-decoration: none;
        border-left: 3px solid transparent;
        transition: background .12s, border-color .12s, color .12s;
        cursor: pointer;
        background: none;
        border-top: none;
        border-right: none;
        border-bottom: none;
        width: 100%;
        text-align: left;
    }
    .nt-mobile-nav-link:hover { background: #f9fafb; color: var(--nt-text); }
    .nt-mobile-nav-link.active {
        background: #f0fdf4;
        color: var(--nt-accent);
        border-left-color: var(--nt-accent);
        font-weight: 600;
    }

    .nt-mobile-nav-link svg { width: 15px; height: 15px; opacity: .65; flex-shrink: 0; }
    .nt-mobile-nav-link:hover svg,
    .nt-mobile-nav-link.active svg { opacity: 1; }

    .nt-mobile-user-section {
        border-top: 1px solid var(--nt-border);
        padding: 12px 16px 4px;
        margin-top: 8px;
    }

    .nt-mobile-user-info {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 4px 0 12px;
    }
    .nt-mobile-user-name { font-size: 14px; font-weight: 600; color: var(--nt-text); }
    .nt-mobile-user-email { font-size: 13px; color: var(--nt-text-muted); margin-top: 1px; }

    /* ── RESPONSIVE ── */
    @media (max-width: 960px) {
        .nt-nav-links  { display: none; }
        .nt-nav-actions { display: none; }
        .nt-nav-right .nt-dropdown { display: none; }
        .nt-hamburger  { display: flex; }
    }
    @media (min-width: 961px) {
        .nt-hamburger  { display: none; }
        .nt-mobile-menu { display: none !important; }
    }
</style>

{{-- ═══════════════════════════════════════════════
     ESTRUCTURA DEL NAV
═══════════════════════════════════════════════ --}}
<nav x-data="{ open: false }" class="nt-nav">
    <div class="nt-nav-inner">

        {{-- ── IZQUIERDA: Logo + Links ── --}}
        <div style="display:flex; align-items:stretch; flex:1; min-width:0;">

            {{-- Logo --}}
            <div style="flex-shrink:0; display:flex; align-items:center;">
                <a href="{{ route('dashboard') }}" class="nt-logo">
                    {{-- Reemplaza con tu <x-application-mark /> si tienes logo SVG --}}
                    <span class="nt-logo-dot"></span>
                    <span class="nt-logo-text">NutriTrack</span>
                </a>
            </div>

            {{-- Links de navegación (desktop) --}}
            <div class="nt-nav-links">

                <a href="{{ route('dashboard') }}"
                   class="nt-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                    {{ __('Dietas Activas') }}
                </a>

                <a href="{{ route('entrega.index') }}"
                   class="nt-nav-link {{ request()->routeIs('entrega.*') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                        <rect x="9" y="3" width="6" height="4" rx="1"/>
                        <path d="M9 12l2 2 4-4"/>
                    </svg>
                    {{ __('Control de Entrega') }}
                </a>

                <a href="{{ route('dietas.canceladas') }}"
                   class="nt-nav-link {{ request()->routeIs('dietas.canceladas') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                    {{ __('Dietas Canceladas') }}
                </a>

                <a href="{{ route('historial.index') }}"
                   class="nt-nav-link {{ request()->routeIs('historial.*') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    {{ __('Historial') }}
                </a>

                <a href="{{ route('reportes.nutricional') }}"
                   class="nt-nav-link {{ request()->routeIs('reportes.nutricional') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                    {{ __('Reporte Nutricional') }}
                </a>

                @can('viewCostos', App\Models\User::class)
                <a href="{{ route('costos.index') }}"
                   class="nt-nav-link {{ request()->routeIs('costos.*') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
                    </svg>
                    {{ __('Facturación') }}
                </a>
                @endcan

            </div>
        </div>

        {{-- ── DERECHA: Acciones + Teams + User ── --}}
        <div class="nt-nav-right">

            {{-- Botones de acción rápida --}}
            <div class="nt-nav-actions">
                @can('create', App\Models\Dieta::class)
                <a href="{{ route('dietas.create') }}" class="nt-btn nt-btn-primary">
                    <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    {{ __('Registrar Dieta') }}
                </a>
                @endcan
            </div>

            {{-- Teams Dropdown --}}
            @if (Laravel\Jetstream\Jetstream::hasTeamFeatures())
            <div x-data="{ open: false }" class="nt-dropdown" style="margin-right:4px">
                <button @click="open = !open"
                        @click.outside="open = false"
                        class="nt-dropdown-trigger">
                    <span style="font-size:13.5px;font-weight:500;color:#374151">
                        {{ Auth::user()->currentTeam->name }}
                    </span>
                    <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"
                         class="nt-chevron" :style="open ? 'transform:rotate(180deg)' : ''">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/>
                    </svg>
                </button>

                <div x-show="open"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="nt-dropdown-menu nt-teams-dropdown"
                     x-cloak>

                    <div class="nt-dropdown-header">{{ __('Manage Team') }}</div>

                    <a href="{{ route('teams.show', Auth::user()->currentTeam->id) }}"
                       class="nt-dropdown-link">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                        </svg>
                        {{ __('Team Settings') }}
                    </a>

                    @can('create', Laravel\Jetstream\Jetstream::newTeamModel())
                    <a href="{{ route('teams.create') }}" class="nt-dropdown-link">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        {{ __('Create New Team') }}
                    </a>
                    @endcan

                    @if (Auth::user()->allTeams()->count() > 1)
                    <div class="nt-divider"></div>
                    <div class="nt-dropdown-header">{{ __('Switch Teams') }}</div>
                    @foreach (Auth::user()->allTeams() as $team)
                        <x-switchable-team :team="$team" />
                    @endforeach
                    @endif
                </div>
            </div>
            @endif

            {{-- User / Settings Dropdown --}}
            <div x-data="{ open: false }" class="nt-dropdown">
                <button @click="open = !open"
                        @click.outside="open = false"
                        class="nt-dropdown-trigger">

                    @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                        <img class="nt-avatar-img"
                             src="{{ Auth::user()->profile_photo_url }}"
                             alt="{{ Auth::user()->name }}" />
                    @else
                        <div class="nt-avatar">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                    @endif

                    <div class="nt-user-info">
                        <span class="nt-user-name">{{ Auth::user()->name }}</span>
                        <span class="nt-user-role">{{ Auth::user()->role ?? __('Usuario') }}</span>
                    </div>

                    <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"
                         class="nt-chevron" :style="open ? 'transform:rotate(180deg)' : ''">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                    </svg>
                </button>

                <div x-show="open"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="nt-dropdown-menu"
                     x-cloak>

                    <div class="nt-dropdown-header">{{ __('Manage Account') }}</div>

                    <a href="{{ route('profile.show') }}" class="nt-dropdown-link">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        {{ __('Profile') }}
                    </a>

                    @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                    <a href="{{ route('api-tokens.index') }}" class="nt-dropdown-link">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="5" y="11" width="14" height="10" rx="2"/>
                            <path d="M12 7a4 4 0 00-4 4"/>
                            <path d="M12 7a4 4 0 014 4"/>
                            <circle cx="12" cy="7" r="1"/>
                        </svg>
                        {{ __('API Tokens') }}
                    </a>
                    @endif

                    <div class="nt-divider"></div>

                    <form method="POST" action="{{ route('logout') }}" x-data>
                        @csrf
                        <button type="submit"
                                @click.prevent="$root.submit()"
                                class="nt-dropdown-link danger">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                                <polyline points="16 17 21 12 16 7"/>
                                <line x1="21" y1="12" x2="9" y2="12"/>
                            </svg>
                            {{ __('Log Out') }}
                        </button>
                    </form>

                </div>
            </div>

            {{-- Hamburger (solo mobile) --}}
            <button @click="open = !open" class="nt-hamburger" aria-label="Toggle menu">
                <svg stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{ 'hidden': open, 'inline-flex': !open }"
                          class="inline-flex"
                          stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"/>
                    <path :class="{ 'hidden': !open, 'inline-flex': open }"
                          class="hidden"
                          stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- ═══════════════════════════════════
         MENÚ RESPONSIVE (mobile)
    ═══════════════════════════════════ --}}
    <div :class="{ 'block': open, 'hidden': !open }"
         class="hidden nt-mobile-menu">

        {{-- Links de navegación --}}
        <div class="nt-mobile-nav-section">{{ __('Principal') }}</div>

        <a href="{{ route('dashboard') }}"
           class="nt-mobile-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/>
                <rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
            {{ __('Dietas Activas') }}
        </a>

        <a href="{{ route('entrega.index') }}"
           class="nt-mobile-nav-link {{ request()->routeIs('entrega.*') ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                <rect x="9" y="3" width="6" height="4" rx="1"/>
                <path d="M9 12l2 2 4-4"/>
            </svg>
            {{ __('Control de Entrega') }}
        </a>

        <a href="{{ route('dietas.canceladas') }}"
           class="nt-mobile-nav-link {{ request()->routeIs('dietas.canceladas') ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            {{ __('Dietas Canceladas') }}
        </a>

        <div class="nt-mobile-nav-section">{{ __('Análisis') }}</div>

        <a href="{{ route('historial.index') }}"
           class="nt-mobile-nav-link {{ request()->routeIs('historial.*') ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            {{ __('Historial de Cambios') }}
        </a>

        <a href="{{ route('reportes.nutricional') }}"
           class="nt-mobile-nav-link {{ request()->routeIs('reportes.nutricional') ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
            {{ __('Reporte Nutricional') }}
        </a>

        @can('viewCostos', App\Models\User::class)
        <a href="{{ route('costos.index') }}"
           class="nt-mobile-nav-link {{ request()->routeIs('costos.*') ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="12" y1="1" x2="12" y2="23"/>
                <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
            </svg>
            {{ __('Facturación / Costos') }}
        </a>
        @endcan

        {{-- Usuario en móvil --}}
        <div class="nt-mobile-user-section">
            <div class="nt-mobile-user-info">
                @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                    <img class="nt-avatar-img" style="width:40px;height:40px"
                         src="{{ Auth::user()->profile_photo_url }}"
                         alt="{{ Auth::user()->name }}" />
                @else
                    <div class="nt-avatar" style="width:40px;height:40px;font-size:15px">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <div class="nt-mobile-user-name">{{ Auth::user()->name }}</div>
                    <div class="nt-mobile-user-email">{{ Auth::user()->email }}</div>
                </div>
            </div>

            @can('create', App\Models\Dieta::class)
            <a href="{{ route('dietas.create') }}" class="nt-btn nt-btn-primary" style="margin-bottom:8px;display:inline-flex">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                {{ __('Registrar Dieta') }}
            </a>
            @endcan

            <a href="{{ route('profile.show') }}" class="nt-mobile-nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                {{ __('Profile') }}
            </a>

            @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
            <a href="{{ route('api-tokens.index') }}" class="nt-mobile-nav-link">
                {{ __('API Tokens') }}
            </a>
            @endif

            <form method="POST" action="{{ route('logout') }}" x-data>
                @csrf
                <button type="submit"
                        @click.prevent="$root.submit()"
                        class="nt-mobile-nav-link"
                        style="color:#dc2626; width:100%; cursor:pointer; background:none; border:none; font-family:'DM Sans',sans-serif;">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    {{ __('Log Out') }}
                </button>
            </form>

            {{-- Team Management (mobile) --}}
            @if (Laravel\Jetstream\Jetstream::hasTeamFeatures())
                <div class="nt-divider" style="margin:8px 0"></div>
                <div class="nt-mobile-nav-section">{{ __('Manage Team') }}</div>

                <a href="{{ route('teams.show', Auth::user()->currentTeam->id) }}"
                   class="nt-mobile-nav-link {{ request()->routeIs('teams.show') ? 'active' : '' }}">
                    {{ __('Team Settings') }}
                </a>

                @can('create', Laravel\Jetstream\Jetstream::newTeamModel())
                <a href="{{ route('teams.create') }}"
                   class="nt-mobile-nav-link {{ request()->routeIs('teams.create') ? 'active' : '' }}">
                    {{ __('Create New Team') }}
                </a>
                @endcan

                @if (Auth::user()->allTeams()->count() > 1)
                    <div class="nt-divider" style="margin:8px 0"></div>
                    <div class="nt-mobile-nav-section">{{ __('Switch Teams') }}</div>
                    @foreach (Auth::user()->allTeams() as $team)
                        <x-switchable-team :team="$team" component="responsive-nav-link" />
                    @endforeach
                @endif
            @endif
        </div>
    </div>
</nav>
