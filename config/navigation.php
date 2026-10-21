<?php

/*
|--------------------------------------------------------------------------
| Menú lateral
|--------------------------------------------------------------------------
|
| Secciones y enlaces del menú (NUT-CMP-002). Cada enlace usa la misma condición que el
| middleware de su ruta, para no mostrar enlaces que terminan en 403:
|   'can'     => permiso, o lista de permisos (basta uno)
|   'role'    => rol exigido (en lugar de 'can')
|   'premium' => función de config('subscriptions.premium_features'); sin plan, el enlace
|                lleva a /suscripcion con un candado
|   'active'  => patrones de nombre de ruta que marcan el enlace como actual
| Una sección solo se muestra si el usuario ve al menos uno de sus enlaces.
|
*/

return [

    'sections' => [
        [
            'label' => 'Principal',
            'items' => [
                ['id' => 'nav-nutricional', 'label' => 'Dietas Activas', 'route' => 'dashboard.nutrition', 'icon' => 'grid', 'can' => 'viewActiveDiets'],
                ['id' => 'nav-despacho', 'label' => 'Despacho', 'route' => 'dashboard.dispatch', 'icon' => 'clipboard', 'role' => 'despachador'],
            ],
        ],
        [
            'label' => 'Seguimiento',
            'items' => [
                ['id' => 'nav-despachos', 'label' => 'Despachos del día', 'route' => 'dispatches.index', 'icon' => 'truck', 'can' => 'viewDispatches', 'premium' => 'dispatch_log'],
                ['id' => 'nav-historial', 'label' => 'Historial de cambios', 'route' => 'diets.history', 'icon' => 'clock', 'can' => 'viewDiets', 'premium' => 'history'],
                ['id' => 'nav-reporte', 'label' => 'Reporte Nutricional', 'route' => 'entrega.index', 'active' => ['entrega.index', 'diet.reports'], 'icon' => 'activity', 'can' => ['viewReports', 'generateFiles']],
                ['id' => 'nav-precios', 'label' => 'Facturación y precios', 'route' => 'prices.index', 'icon' => 'dollar', 'can' => 'managePrices', 'premium' => 'prices'],
                ['id' => 'nav-suscripcion', 'label' => 'Suscripción', 'route' => 'subscription.index', 'active' => ['subscription.*'], 'icon' => 'card', 'can' => ['manageSubscription', 'approvePayments']],
            ],
        ],
        [
            'label' => 'Administración',
            'items' => [
                ['id' => 'nav-empresas', 'label' => 'Empresas', 'route' => 'companies.index', 'icon' => 'building', 'can' => ['viewCompanies', 'manageCompanies']],
                ['id' => 'nav-usuarios', 'label' => 'Usuarios', 'route' => 'admin.users.index', 'active' => ['admin.users.*'], 'icon' => 'users', 'can' => 'viewUsers'],
                ['id' => 'nav-roles', 'label' => 'Roles y permisos', 'route' => 'admin.roles.index', 'active' => ['admin.roles.*'], 'icon' => 'lock', 'can' => 'manageRoles'],
                ['id' => 'nav-demos', 'label' => 'Solicitudes de demo', 'route' => 'admin.demo-requests.index', 'active' => ['admin.demo-requests.*'], 'icon' => 'message', 'can' => 'viewDemoRequests'],
                ['id' => 'nav-auditoria', 'label' => 'Auditoría', 'route' => 'admin.audit.index', 'active' => ['admin.audit.*'], 'icon' => 'shield', 'can' => 'viewAuditLog'],
            ],
        ],
    ],

    // Contenido de cada ícono (SVG 24×24 con trazo); lo dibuja <x-nav-item>
    'icons' => [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'clipboard' => '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12l2 2 4-4"/>',
        'truck' => '<path d="M1 3h15v13H1zM16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'dollar' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>',
        'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>',
        'building' => '<rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4"/><line x1="8" y1="6" x2="8" y2="6.01"/><line x1="12" y1="6" x2="12" y2="6.01"/><line x1="16" y1="6" x2="16" y2="6.01"/><line x1="8" y1="10" x2="8" y2="10.01"/><line x1="12" y1="10" x2="12" y2="10.01"/><line x1="16" y1="10" x2="16" y2="10.01"/><line x1="8" y1="14" x2="8" y2="14.01"/><line x1="12" y1="14" x2="12" y2="14.01"/><line x1="16" y1="14" x2="16" y2="14.01"/>',
        'users' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>',
        'message' => '<path d="M4 4h16v12H5.2L4 17.2z"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="13" y2="12"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>',
    ],

    // Nombre visible de cada rol en el pie del menú (los roles creados en /admin/roles usan su nombre)
    'role_labels' => [
        'admin' => 'Administrador',
        'nutricionista' => 'Nutricionista',
        'despachador' => 'Despachador',
        'jefe_nutricion' => 'Jefe de nutrición',
        'jefe_despacho' => 'Jefe de despacho',
        'enfermeria' => 'Enfermería',
    ],

];
