@php
  $authUser = auth()->user();
  $sections = \App\Support\Navigation::sections($authUser);
  $roleLabel = \App\Support\Navigation::roleLabel($authUser);
  $initials = \App\Support\Navigation::initials($authUser);
@endphp

<!-- Barra superior (solo móvil) -->
<header class="mobile-topbar">
  <button type="button" class="menu-toggle" id="menuToggle" onclick="openSidebar()" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false">
    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </button>
  <span class="brand">NutriTrack</span>
  <a href="{{ route('profile.show') }}" class="user-avatar" title="{{ $authUser?->name }}">{{ $initials }}</a>
</header>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

<!-- ══════════════ MENÚ LATERAL (NUT-CMP-002) ══════════════ -->
<aside class="sidebar" id="sidebar" aria-label="Menú principal">
  <div class="sidebar-logo">
    <div class="sidebar-brand">
      <h1><span class="dot" aria-hidden="true"></span><span class="nav-label">NutriTrack</span></h1>
      <div class="subtitle">Gestión de Dietas Hospitalarias</div>
    </div>
    <button type="button" class="sidebar-collapse" id="sidebarCollapse" onclick="toggleSidebarCollapse()"
      aria-controls="sidebar" aria-expanded="true" aria-label="Contraer menú" title="Contraer menú">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
  </div>

  <nav class="nav" aria-label="Secciones">
    @foreach ($sections as $section)
      <div class="nav-section"><span class="nav-label">{{ $section['label'] }}</span></div>
      @foreach ($section['items'] as $item)
        <x-nav-item :item="$item" />
      @endforeach
    @endforeach
  </nav>

  <div class="sidebar-footer">
    <div class="user-info">
      <a href="{{ route('profile.show') }}" class="user-profile-link {{ request()->routeIs('profile.show') ? 'active' : '' }}"
        title="Mi perfil · {{ $authUser?->name }}" @if (request()->routeIs('profile.show')) aria-current="page" @endif>
        <span class="user-avatar" aria-hidden="true">{{ $initials }}</span>
        <span class="user-meta nav-label">
          <span class="user-name">{{ $authUser?->name }}</span>
          <span class="user-role">{{ $roleLabel }}</span>
        </span>
      </a>
      <button type="button" class="logout-btn" id="logoutButton" onclick="openLogout()" title="Cerrar sesión" aria-haspopup="dialog">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span class="nav-label">Cerrar sesión</span>
      </button>
    </div>
  </div>
</aside>

<!-- Confirmación de cierre de sesión -->
<div class="overlay logout-overlay" id="logoutOverlay" role="dialog" aria-modal="true" aria-labelledby="logoutTitle" onclick="if (event.target === this) closeLogout()">
  <div class="modal logout-modal">
    <div class="modal-header">
      <h2 id="logoutTitle">Cerrar sesión</h2>
      <button type="button" class="close-btn" onclick="closeLogout()" aria-label="Cerrar">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form action="{{ route('logout') }}" method="POST" id="logoutForm">
      @csrf
      <div class="modal-body">
        <p>¿Seguro que deseas cerrar sesión? Los cambios sin guardar se perderán.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" id="logoutCancel" onclick="closeLogout()">Cancelar</button>
        <button type="submit" class="btn btn-danger">Cerrar sesión</button>
      </div>
    </form>
  </div>
</div>

<script>
  (function () {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const toggle = document.getElementById('menuToggle');
    const collapse = document.getElementById('sidebarCollapse');
    const logout = document.getElementById('logoutOverlay');
    const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])';
    let returnFocus = null;

    const visible = (el) => el.offsetParent !== null;
    const focusables = (root) => [...root.querySelectorAll(FOCUSABLE)].filter(visible);
    const isDrawer = () => sidebar.classList.contains('open');

    // El Tab no sale del cajón móvil ni del modal mientras están abiertos
    function trap(root, e) {
      const items = focusables(root);
      if (!items.length) return;
      const first = items[0], last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }

    window.openSidebar = function () {
      sidebar.classList.add('open');
      backdrop.classList.add('open');
      toggle.setAttribute('aria-expanded', 'true');
      (sidebar.querySelector('.nav-item.active') || focusables(sidebar)[0])?.focus();
    };

    window.closeSidebar = function () {
      if (!isDrawer()) return;
      sidebar.classList.remove('open');
      backdrop.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
      if (visible(toggle)) toggle.focus();
    };

    // Escritorio: solo íconos; la preferencia queda en el navegador (la aplica el <head> antes de pintar)
    function syncCollapse() {
      const collapsed = document.documentElement.classList.contains('sidebar-collapsed');
      const label = collapsed ? 'Expandir menú' : 'Contraer menú';
      collapse.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      collapse.setAttribute('aria-label', label);
      collapse.title = label;
    }

    window.toggleSidebarCollapse = function () {
      const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
      try { localStorage.setItem('nt-sidebar', collapsed ? 'collapsed' : 'expanded'); } catch (e) {}
      syncCollapse();
    };

    window.openLogout = function () {
      returnFocus = document.activeElement;
      logout.classList.add('open');
      document.getElementById('logoutCancel').focus();
    };

    window.closeLogout = function () {
      logout.classList.remove('open');
      returnFocus?.focus();
    };

    document.addEventListener('keydown', function (e) {
      const logoutOpen = logout.classList.contains('open');
      if (e.key === 'Escape') {
        logoutOpen ? closeLogout() : closeSidebar();
      } else if (e.key === 'Tab') {
        if (logoutOpen) trap(logout, e);
        else if (isDrawer()) trap(sidebar, e);
      }
    });

    syncCollapse();
  })();
</script>
