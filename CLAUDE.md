# CLAUDE.md — Nutrition Platform

Plataforma hospitalaria de **gestión de dietas de pacientes**. El nutricionista crea y versiona dietas por habitación; el despachador (cocina) ve los cambios en tiempo casi real, los confirma y genera reportes de producción.

Idioma del dominio y la UI: **español**. Mantén nombres de rutas/vistas en español cuando ya existan así (`dietas.create`, `entrega.index`).

---

## 1. Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP ^8.1 (local 8.4), **Laravel 10**, Jetstream 4 (stack Livewire), Fortify, Sanctum |
| UI | Blade + **Livewire 3** + Tailwind 3 (Vite) · Chart.js 4 (CDN) · assets de plantilla en `public/assets` |
| Auth/ACL | `spatie/laravel-permission` ^6 (roles + permisos, guard `web`) |
| BD | MySQL (`nutrition`), sesiones en BD |
| Otros | `spatie/laravel-backup` (diario 01:00), Pusher/Echo instalados pero `BROADCAST_DRIVER=log` |
| Entorno | Laravel Herd (`herd.yml`), cola `sync` |

## 2. Comandos

```bash
composer install && npm install
php artisan migrate --seed          # crea roles/permisos + usuarios demo
npm run dev                         # Vite
php artisan test                    # PHPUnit 10, SQLite :memory: (phpunit.xml). Requiere npm run build
./vendor/bin/pint                   # formato PSR-12
php artisan schedule:work           # backups programados
php artisan queue:work              # requerido cuando QUEUE_CONNECTION != sync
php artisan permission:cache-reset  # tras cambiar roles/permisos
```

> ⚠️ Los tests usan `RefreshDatabase`. **Nunca** quitar `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` de `phpunit.xml`: con MySQL borran la BD `nutrition`.

## 3. Arquitectura actual

```
Request → routes/web.php (auth:sanctum + jetstream + verified [+ role:*])
        → DietController ──→ DietService (transacciones, versionado)
                          └→ FormRequest (StoreDietRequest: authorize = rol nutricionista)
        → Livewire\DietsManager (vista despachador, wire:poll.3s)
        → Blade views (dashboard, reports-diets*, components/modal-*-diets)
```

Patrón: **Controller delgado → Service → Eloquent**. Catálogos de opciones en `config/` (no en BD):

- `config/timeFood.php` — tiempos de comida (`desayuno`, `mediamanana`, `almuerzo`, `algo`, `merienda`, `cena`, `fraccionada`)
- `config/consistency.php` — consistencias (`normal`, `blanda_mecanica`, `liquida_clara`, …)
- `config/specifications.php` — especificaciones (`hiposodica`, `sin_gluten`, …)

Cada archivo expone `labels` (texto UI) y `colors` (clases Tailwind). Las claves son snake_case y son lo que se persiste.

### Modelos

| Modelo | Tabla | Campos clave | Relaciones |
|---|---|---|---|
| `Diet` | `diets` | `habitation`, `name_patient`, `status` (bool, activa) | `history()` hasMany, `currentVersion()` hasOne latestOfMany(`version`) |
| `DietHistory` | `diet_histories` | `diet_id`, `updated_by`, `version`, `timeFood`/`consistency`/`specifications` (JSON array), `observations`, `isolation` (`Sí`/`No`), `changes`, `modify` | `diet()`, `updatedBy()` → User |
| `User` | `users` | Jetstream + `HasRoles` | — |
| `App\Models\Role` | — | **No usar**: duplicado; el rol real es `Spatie\Permission\Models\Role` |

### Flujo de dieta (versionado inmutable)

1. **Crear** `POST /dietas` → `DietService::createDiet` → `Diet` + `DietHistory` v1 (`modify='no'`).
2. **Editar** `PUT /dietas/{diet}/version` → `createNewVersion`: `lockForUpdate` sobre max(version), inserta v+1 con `modify='si'`. Si `changes === 'cancelada'` → `diets.status = false`.
3. **Despacho** `DietsManager`: compara versión actual vs anterior (`detectChanges`) y resalta; `confirmChanges($historyId)` pone `modify='no'`.
4. **Reportes** `GET /diet-reports` y `/entrega/index` → `nutritionReport()` (totales diarios/mensuales por tiempo de comida sobre la última versión de dietas activas).

Nunca se edita un `DietHistory` existente (salvo el flag `modify`); todo cambio clínico es una nueva versión.

### Roles y permisos (seeder `RolesAndPermissionsSeeder`)

| Rol | Permisos |
|---|---|
| `nutricionista` | `createDiets`, `editDiets`, `viewDiets`, `viewReports` |
| `despachador` | `viewDiets`, `confirmDelivery`, `generateFiles` |

Convención de permisos: **camelCase verbo+Recurso**. Middleware alias registrados: `role`, `permission`, `role_or_permission`.

### Deuda técnica conocida

Corregido el 2026-10-15: columnas `modify`/`confirmed` (migración idempotente), `cancelDiet` (crea versión `cancelada`), agregación de `timeFood` (normaliza `media_manana`/`mediamanana`, incluye `merienda`), `index()` ahora en `/reportes/produccion`, rutas protegidas con `permission:`, registro público tras `REGISTRATION_ENABLED` (por defecto off), contraseñas del seeder vía `SEED_USER_PASSWORD`, eliminados `PacienteActualizado` y `App\Models\Role`.

Pendiente:
- Claves de `timeFood` inconsistentes entre `config/timeFood.php` (`mediamanana`, `merienda`) y `components/form-diet.blade.php` (`media_manana`, sin `merienda`). Unificar y migrar los datos.
- `resources/views/navigation-menu.blade.php` no se usa (el layout incluye `navigation.blade.php`) y referencia rutas inexistentes (`dashboard`, `dietas.canceladas`, `historial.index`, `costos.index`).
- `DietsManager::confirmRecord` (`diets.confirmed`) no está conectado a ninguna vista.
- Tests de auth fallan sin `npm run build` (falta el manifest de Vite) y sin `APP_KEY` en el entorno de tests.

---

## 4. Configuración regional

Aplicado en `config/app.php` y `config/database.php`:

```php
'timezone'        => env('APP_TIMEZONE', 'America/Bogota'),
'locale'          => env('APP_LOCALE', 'es'),
'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
'faker_locale'    => env('APP_FAKER_LOCALE', 'es_CO'),
// mysql
'timezone'        => env('DB_TIMEZONE', '-05:00'),   // Colombia no tiene horario de verano
```

`.env`:
```
APP_TIMEZONE=America/Bogota
APP_LOCALE=es
APP_FAKER_LOCALE=es_CO
DB_TIMEZONE=-05:00
```

Reglas:
- La sesión MySQL en `-05:00` hace que `CURDATE()`, `NOW()` y `DATE(created_at)` coincidan con `now()` de Laravel.
- **Datos existentes**: los `created_at` previos se guardaron en UTC. Si importan para reportes históricos, migrar con `UPDATE … SET created_at = CONVERT_TZ(created_at,'+00:00','-05:00')` (una sola vez, con backup).
- Traducciones: `composer require laravel-lang/common --dev && php artisan lang:add es` (validación, auth, paginación).
- Fechas en vistas: `$date->locale('es')->isoFormat('D [de] MMMM YYYY, h:mm a')`. Carbon toma el locale de la app.
- Moneda/números (si aplica a costos): `Number::currency($v, 'COP', 'es_CO')` requiere `ext-intl`.

---

## 5. Módulo: Administración de usuarios (por implementar)

**Objetivo**: CRUD de usuarios, asignación de roles y gestión de permisos por un rol `admin`.

### Dependencias
Ninguna nueva (Spatie Permission + Livewire ya instalados).

### Cambios de datos
```php
// migración add_admin_fields_to_users_table
$table->string('phone', 20)->nullable();      // E.164, p.ej. +573001234567 (usado por WhatsApp)
$table->boolean('is_active')->default(true);
$table->boolean('whatsapp_opt_in')->default(false);
$table->softDeletes();
```
`User`: añadir `SoftDeletes`, `phone`, `is_active`, `whatsapp_opt_in` a `$fillable`, cast `is_active`/`whatsapp_opt_in` boolean, `routeNotificationForWhatsApp()` → `$this->phone`.

### Permisos y rol
Agregar al seeder:
```
viewUsers, createUsers, editUsers, deleteUsers, manageRoles
rol admin → todos los permisos (Permission::all())
```
Opcional: `Gate::before(fn ($u) => $u->hasRole('admin') ? true : null)` en `AuthServiceProvider`.

### Estructura
```
app/Livewire/Admin/UsersManager.php      // listado, búsqueda, filtros por rol/estado, paginación
app/Livewire/Admin/UserForm.php          // crear/editar (modal)
app/Livewire/Admin/RolesManager.php      // matriz rol × permiso (syncPermissions)
app/Services/UserService.php             // create/update/deactivate/restore, syncRoles en DB::transaction
app/Policies/UserPolicy.php              // viewAny/create/update/delete → can('xxxUsers'); no auto-borrado; no quitar último admin
app/Http/Requests/Admin/StoreUserRequest.php / UpdateUserRequest.php
resources/views/livewire/admin/*.blade.php
```

### Rutas
```php
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'permission:viewUsers'])
    ->prefix('admin')->name('admin.')->group(function () {
        Route::get('/usuarios', UsersManager::class)->name('users.index');
        Route::get('/roles', RolesManager::class)->middleware('permission:manageRoles')->name('roles.index');
    });
```

### Reglas
- Validación: `email` unique (ignorando el propio y soft-deleted), `password` con `PasswordValidationRules` de Fortify, `roles` ⊆ roles existentes, `phone` regex `^\+57\d{10}$`.
- Eliminar = soft delete + `is_active=false`; bloquear login de inactivos en `FortifyServiceProvider` (`Fortify::authenticateUsing`).
- Tras cambiar permisos: `app(PermissionRegistrar::class)->forgetCachedPermissions()`.
- Deshabilitar `Features::registration()` una vez exista el módulo.
- Tests: `tests/Feature/Admin/UsersManagerTest.php` con `Livewire::test()` cubriendo autorización por permiso.

---

## 6. Módulo: Notificaciones WhatsApp por cambio de dieta (por implementar)

Skill: `.claude/skills/whatsapp-diet-notifications/SKILL.md`.

**Proveedor**: WhatsApp Business Cloud API (Meta) vía HTTP (`guzzlehttp/guzzle` ya instalado). Mensajes iniciados por el negocio requieren **plantillas aprobadas**.

### Flujo
```
DietService::createDiet / createNewVersion
  └─ DB::afterCommit → event(DietVersionCreated($history))
       └─ Listener SendDietChangeNotification (ShouldQueue, cola "notifications")
            └─ Notification DietChangedNotification → canal WhatsAppChannel
                 └─ WhatsAppClient::sendTemplate(phone, template, params)
                      └─ registra en whatsapp_messages (status, wamid, error)
Webhook POST /webhooks/whatsapp → actualiza status (sent/delivered/read/failed)
```

Destinatarios: usuarios con rol `despachador`, `is_active` y `whatsapp_opt_in`. El contenido incluye habitación, tipo de cambio y versión; **no** enviar datos clínicos detallados (minimizar PHI).

### Configuración
```php
// config/services.php
'whatsapp' => [
    'token'           => env('WHATSAPP_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'api_version'     => env('WHATSAPP_API_VERSION', 'v21.0'),
    'verify_token'    => env('WHATSAPP_VERIFY_TOKEN'),
    'app_secret'      => env('WHATSAPP_APP_SECRET'),
    'template_change' => env('WHATSAPP_TEMPLATE_DIET_CHANGE', 'cambio_dieta'),
    'enabled'         => env('WHATSAPP_ENABLED', false),
],
```
Requiere `QUEUE_CONNECTION=database` (+ `php artisan queue:table && migrate`) y un worker.

---

## 7. Módulos: Reportes PDF y Excel (por implementar)

Skills: `.claude/skills/pdf-reports/SKILL.md`, `.claude/skills/excel-reports/SKILL.md`.

### Dependencias
```bash
composer require barryvdh/laravel-dompdf:^3.0   # PDF desde Blade
composer require maatwebsite/excel:^3.1         # XLSX/CSV (PhpSpreadsheet; requiere ext-zip, ext-gd)
```

### Arquitectura compartida
```
app/Services/Reports/DietReportService.php   // ÚNICA fuente de datos: extraer la lógica de nutritionReport()/aggregateFoodTotals()
app/Http/Controllers/ReportExportController.php
app/Exports/*Export.php                      // Excel
resources/views/pdf/*.blade.php              // PDF (CSS inline, sin Tailwind/Vite)
app/Jobs/GenerateReportJob.php               // reportes grandes → storage/app/reports + notificación
```

Reportes objetivo:
| Reporte | Contenido | Filtros |
|---|---|---|
| Producción de cocina | Conteo por tiempo de comida y consistencia, dietas activas | fecha |
| Listado de dietas activas | Habitación, paciente, tiempos, consistencia, especificaciones, aislamiento | fecha, estado |
| Historial de cambios | Versiones por dieta, usuario, fecha | rango, dieta |
| Consolidado mensual | Totales diarios/mensuales (como `reports-diets-nutricion`) | mes |

Rutas (permiso `generateFiles` o `viewReports`):
```php
Route::get('/reportes/{report}/pdf',   [ReportExportController::class, 'pdf'])->name('reports.pdf');
Route::get('/reportes/{report}/excel', [ReportExportController::class, 'excel'])->name('reports.excel');
```
Nombres de archivo con fecha local: `produccion_2026-10-15_0630.pdf` (`now('America/Bogota')`). Las etiquetas visibles siempre desde `config('*.labels')`.

---

## 8. Convenciones

- Lógica de negocio en `app/Services`; controladores y componentes Livewire delgados.
- Escrituras multi-tabla en `DB::transaction`; eventos que disparan efectos externos con `afterCommit`.
- Autorización: middleware `permission:` en rutas + `authorize()` en FormRequest/Policy; preferir **permisos** sobre roles.
- Catálogos nuevos → archivo en `config/` con `labels` y `colors`.
- Secretos solo en `.env` (nunca en seeders ni código); actualizar `.env.example` al añadir variables.
- Comentarios y mensajes de usuario en español; código (clases, métodos) en inglés salvo nombres de dominio existentes.
- Ejecutar `pint` y `php artisan test` antes de commitear.

## 9. Skills locales

| Skill | Uso |
|---|---|
| `whatsapp-diet-notifications` | Implementar/modificar notificaciones WhatsApp de cambios de dieta |
| `pdf-reports` | Crear o ajustar reportes PDF |
| `excel-reports` | Crear o ajustar exportaciones Excel/CSV |
