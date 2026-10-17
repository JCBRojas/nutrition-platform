@php
  $authUser = auth()->user();
  $roleLabels = [
    'admin' => 'Administrador',
    'nutricionista' => 'Nutricionista',
    'despachador' => 'Despachador',
    'jefe_nutricion' => 'Jefe de nutrición',
  ];
  $roleName = $authUser?->getRoleNames()->first();
  $roleLabel = $roleName ? ($roleLabels[$roleName] ?? \Illuminate\Support\Str::of($roleName)->replace('_', ' ')->ucfirst()) : 'Sin rol';
  // Funciones del plan Premium: sin plan, el enlace lleva a /suscripcion con un candado
  $hasPremium = fn (string $feature) => app(\App\Services\SubscriptionService::class)->hasFeature($feature);
  $initials = collect(preg_split('/\s+/', trim($authUser?->name ?? '')))
    ->filter()->take(2)
    ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
    ->implode('') ?: '?';
@endphp

<!-- Barra superior (solo móvil) -->
<header class="mobile-topbar">
  <button type="button" class="menu-toggle" onclick="openSidebar()" aria-label="Abrir menú" aria-controls="sidebar">
    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </button>
  <span class="brand">NutriTrack</span>
  <a href="{{ route('profile.show') }}" class="user-avatar" title="{{ $authUser?->name }}">{{ $initials }}</a>
</header>

<!-- Sidebar backdrop -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

<!-- ══════════════ SIDEBAR ══════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <h1><span class="dot"></span>NutriTrack</h1>
    <div class="subtitle">Gestión de Dietas Hospitalarias</div>
  </div>
  <nav class="nav">
    @canany(['viewActiveDiets', 'confirmDelivery'])
    <div class="nav-section">Principal</div>
    @endcanany
    {{-- Cada enlace se muestra solo si el usuario pasa el middleware de su ruta --}}
    @can('viewActiveDiets')
    <a class="nav-item {{ request()->routeIs('dashboard.nutrition') ? 'active' : '' }}" href="{{ route('dashboard.nutrition')}}" id="nav-nutricional">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Dietas Activas
    </a>
    @endcan
    @role('despachador')
    <a class="nav-item {{ request()->routeIs('dashboard.dispatch') ? 'active' : '' }}" href="{{ route('dashboard.dispatch')}}" id="nav-despacho">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12l2 2 4-4"/></svg>
      Despacho
    </a>
    @endrole
    {{-- <a class="nav-item" href="{{ route('dashboard.nutrition')}}" id="nav-nutricional">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12l2 2 4-4"/></svg>
      Control de Entrega
    </a>
    <div class="nav-item" onclick="showPage('canceladas')" id="nav-canceladas">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      Dietas Canceladas
    </div>
    <div class="nav-section">Análisis</div>
    <div class="nav-item" onclick="showPage('historial')" id="nav-historial">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Historial de Cambios
    </div> --}}
    @canany(['viewDispatches', 'viewDiets', 'viewReports', 'generateFiles', 'managePrices'])
    <div class="nav-section">Seguimiento</div>
    @endcanany
    @can('viewDispatches')
    <a @class(['nav-item', 'active' => request()->routeIs('dispatches.index'), 'is-locked' => ! $hasPremium('dispatch_log')]) href="{{ $hasPremium('dispatch_log') ? route('dispatches.index') : route('subscription.index') }}" id="nav-despachos">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 3h15v13H1zM16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
      Despachos del día
      @unless ($hasPremium('dispatch_log'))<span class="nav-lock" title="Plan Premium"><svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg>Premium</span>@endunless
    </a>
    @endcan
    @can('viewDiets')
    <a @class(['nav-item', 'active' => request()->routeIs('diets.history'), 'is-locked' => ! $hasPremium('history')]) href="{{ $hasPremium('history') ? route('diets.history') : route('subscription.index') }}" id="nav-historial">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Historial de cambios
      @unless ($hasPremium('history'))<span class="nav-lock" title="Plan Premium"><svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg>Premium</span>@endunless
    </a>
    @endcan
    @canany(['viewReports', 'generateFiles'])
    <a class="nav-item {{ request()->routeIs('entrega.index', 'diet.reports') ? 'active' : '' }}" href="{{ route('entrega.index')}}" id="nav-reporte">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Reporte Nutricional
    </a>
    @endcanany
    @can('managePrices')
    <a @class(['nav-item', 'active' => request()->routeIs('prices.index'), 'is-locked' => ! $hasPremium('prices')]) href="{{ $hasPremium('prices') ? route('prices.index') : route('subscription.index') }}" id="nav-precios">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
      Precios
      @unless ($hasPremium('prices'))<span class="nav-lock" title="Plan Premium"><svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg>Premium</span>@endunless
    </a>
    @endcan
    @canany(['manageSubscription', 'approvePayments'])
    <a class="nav-item {{ request()->routeIs('subscription.*') ? 'active' : '' }}" href="{{ route('subscription.index') }}" id="nav-suscripcion">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      Suscripción
    </a>
    @endcanany
    @canany(['viewUsers', 'manageRoles', 'viewAuditLog'])
    <div class="nav-section">Administración</div>
    @endcanany
    @can('viewUsers')
    <a class="nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}" id="nav-usuarios">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      Usuarios
    </a>
    @endcan
    @can('manageRoles')
    <a class="nav-item {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}" href="{{ route('admin.roles.index') }}" id="nav-roles">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
      Roles y permisos
    </a>
    @endcan
    @can('viewAuditLog')
    <a class="nav-item {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}" href="{{ route('admin.audit.index') }}" id="nav-auditoria">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
      Auditoría
    </a>
    @endcan
    {{-- <div class="nav-item" onclick="showPage('costos')" id="nav-costos">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
      Facturación / Costos
    </div> --}}
    {{-- <div class="nav-section">Sistema</div>
    <div class="nav-item" onclick="showPage('esquema')" id="nav-esquema">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
      Esquema BD
    </div> --}}
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <a href="{{ route('profile.show') }}" class="user-profile-link {{ request()->routeIs('profile.show') ? 'active' : '' }}" title="Mi perfil">
        <span class="user-avatar" aria-hidden="true">{{ $initials }}</span>
        <span class="user-meta">
          <span class="user-name">{{ $authUser?->name }}</span>
          <span class="user-role">{{ $roleLabel }}</span>
        </span>
      </a>
      <form action="{{ route('logout') }}" method="POST" id="logoutForm">
        @csrf
        <button type="submit" class="logout-btn" title="Cerrar sesión"
          onclick="return confirm('¿Estás seguro de que deseas cerrar sesión?')">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Cerrar sesión
        </button>
      </form>
    </div>
  </div>
</aside>

<script>
  function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarBackdrop').classList.add('open');
  }

  function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarBackdrop').classList.remove('open');
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
  });
</script>
