<?php

/*
|--------------------------------------------------------------------------
| Seguridad y cumplimiento (datos de salud, Ley 1581 de 2012)
|--------------------------------------------------------------------------
*/

return [

    // HTTPS obligatorio: redirige http → https, fuerza el esquema de las URL y envía HSTS
    'force_https' => (bool) env('FORCE_HTTPS', env('APP_ENV') === 'production'),

    // Proxies de confianza (balanceador, Cloudflare): "*" o lista de IP separadas por coma
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Roles que puede asignar quien administra usuarios sin ser admin (el admin asigna todos).
    // El jefe de nutrición crea despachadores y solo ve a los usuarios con esos roles.
    'assignable_roles' => [
        'jefe_nutricion' => ['despachador'],
    ],

    // Roles que deben tener la verificación en dos pasos (2FA) activa para usar la plataforma
    'two_factor_roles' => array_values(array_filter(array_map('trim', explode(',', env('TWO_FACTOR_ROLES', 'admin,jefe_nutricion'))))),

    // Política de tratamiento de datos que cada usuario acepta al ingresar.
    // Cambiar la versión hace que todos la acepten de nuevo.
    'privacy' => [
        'required' => (bool) env('PRIVACY_ACCEPTANCE_REQUIRED', true),
        'version' => env('PRIVACY_POLICY_VERSION', '2026-10-16'),
        'controller' => env('DATA_CONTROLLER_NAME', '[Razón social de la institución]'),
        'controller_nit' => env('DATA_CONTROLLER_NIT', '[NIT]'),
        'contact_email' => env('DATA_PROTECTION_EMAIL', '[correo de protección de datos]'),
    ],

    // Respaldos: la verificación semanal exige que el archivo esté cifrado (BACKUP_ARCHIVE_PASSWORD)
    'backups' => [
        'require_encryption' => (bool) env('BACKUP_REQUIRE_ENCRYPTION', env('APP_ENV') === 'production'),
        'max_age_hours' => (int) env('BACKUP_MAX_AGE_HOURS', 26),
    ],

];
