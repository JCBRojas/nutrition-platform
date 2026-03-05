<div id="wrapper">
    <nav class="navbar-pro">
        <div class="navbar-custom py-2">
            <!-- Logo / App Name -->
            <ul class="list-unstyled topnav-menu float-right mb-0">
                <li class="dropdown notification-list">
                    <a class="nav-link dropdown-toggle nav-user mr-0 waves-effect" data-toggle="dropdown" href="#"
                        role="button" aria-haspopup="false" aria-expanded="false">
                        <span class="pro-user-name ml-1 username">
                            {{ Auth::user()->name }} <i class="mdi mdi-chevron-down"></i>
                        </span>
                        <span class="arrow">▾</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right profile-dropdown ">
                        <!-- item-->
                        <a href="{{ route('profile.show') }}" class="dropdown-item notify-item">
                            👤 Perfil
                            <span class="badge-soft">Cuenta</span>
                        </a>

                        <!-- item-->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item notify-item logout text-danger">
                                🚪 Cerrar sesión
                            </button>
                        </form>

                    </div>
                </li>

            </ul>
            <div class="logo-box">
                <a href="{{ route('dashboard') }}" class="logo">
                    Nutri<span class="logo-accent">Track</span>
                </a>

                <span class="badge-pastel">PRO</span>

            </div>
        </div>
    </nav>
</div>
<style>
    /* ===============================
   NAVBAR BASE
================================ */

    .navbar-pro {
        position: sticky;
        top: 0;
        z-index: 1000;
        backdrop-filter: blur(12px);
        background: rgba(255, 255, 255, 0.7);
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        transition: all .3s ease;
    }
    @media (prefers-color-scheme: dark) {
    .navbar-pro {
        background: rgba(17,24,39,0.7);
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
}
/* ===============================
   LOGO
================================ */

.logo {
    font-size: 20px;
    font-weight: 700;
    text-decoration: none;
    color: #1f2937;
}

.logo-accent {
    color: #10b981;
}

@media (prefers-color-scheme: dark) {
    .logo {
        color: #f9fafb;
    }
}
.username {
    font-weight: 600;
    color: #374151;
}

@media (prefers-color-scheme: dark) {
    .username {
        color: #f3f4f6;
    }
}

.arrow {
    font-size: 12px;
    opacity: .6;
}
/* ===============================
   BADGES PASTEL SYSTEM
================================ */
.badge-pastel {
    margin-left: 12px;
    padding: 4px 10px;
    font-size: 12px;
    border-radius: 50px;
    background: #d1fae5;
    color: #065f46;
    font-weight: 600;
}

.badge-soft {
    padding: 2px 8px;
    font-size: 11px;
    border-radius: 20px;
    background: #e0e7ff;
    color: #3730a3;
    font-weight: 500;
}

@media (prefers-color-scheme: dark) {
    .badge-pastel {
        background: #064e3b;
        color: #6ee7b7;
    }

    .badge-soft {
        background: #312e81;
        color: #c7d2fe;
    }
}
</style>