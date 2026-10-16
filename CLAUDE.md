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
| Otros | `spatie/laravel-backup` (diario 01:00) · Tiempo real con Echo + protocolo Pusher (Soketi en local; ver «Tiempo real») |
| Entorno | Laravel Herd (`herd.yml`), cola `sync` |

## 2. Comandos

```bash
composer install && npm install
php artisan migrate --seed          # crea roles/permisos + usuarios demo
php artisan db:seed --class=DietDemoSeeder  # +50 dietas × 5 versiones de prueba (solo agrega; no corre en producción)
npm run dev                         # Vite
php artisan test                    # PHPUnit 10, SQLite :memory: (phpunit.xml). Requiere npm run build
./vendor/bin/pint                   # formato PSR-12
php artisan schedule:work           # backups programados y diets:start-day
npx -p node@18 -p @soketi/soketi@1.6.1 soketi start  # WebSockets local (puerto 6001); Soketi no corre en Node 25
php artisan queue:work              # requerido cuando QUEUE_CONNECTION != sync
php artisan permission:cache-reset  # tras cambiar roles/permisos
```

> ⚠️ Los tests usan `RefreshDatabase`. **Nunca** quitar `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` de `phpunit.xml`: con MySQL borran la BD `nutrition`.

## 3. Arquitectura actual

```
Request → routes/web.php (auth:sanctum + jetstream + verified [+ role:*])
        → DietController ──→ DietService (transacciones, versionado)
                          └→ FormRequest (StoreDietRequest: authorize por permiso — createDiets al registrar, editDiets al versionar)
        → Livewire\DietsManager (vista despachador, tiempo real)
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
| `Diet` | `diets` | `habitation`, `name_patient`, `status` (tinyint: `Diet::ACTIVE`=1, `CANCELLED`=0, `FINISHED`=2 alta, `COMPLETED`=3 día completado; cast `integer`, **nunca** `boolean`) | `history()` hasMany, `currentVersion()` hasOne latestOfMany(`version`), `days()` |
| `DietDay` | `diet_days` | `diet_id`, `service_date` (único por dieta), `authorized_via`/`authorized_by`/`authorized_at`, `completed_at`, `completed_history_id` | `diet()`; crear/actualizar con `DietDay::upsertFor()` (busca con `whereDate`) |
| `DietHistory` | `diet_histories` | `diet_id`, `updated_by`, `version`, `timeFood`/`consistency`/`specifications` (JSON array), `observations`, `isolation` (`Sí`/`No`), `changes`, `modify` | `diet()`, `updatedBy()` → User |
| `User` | `users` | Jetstream + `HasRoles` | — |
| `App\Models\Role` | — | **No usar**: duplicado; el rol real es `Spatie\Permission\Models\Role` |

### Flujo de dieta (versionado inmutable)

1. **Crear** `POST /dietas` → `DietService::createDiet` → `Diet` + `DietHistory` v1 (`modify='no'`).
2. **Editar** `PUT /dietas/{diet}/version` → `createNewVersion`: `lockForUpdate` sobre max(version), inserta v+1 con `modify='si'`. Si `changes === 'cancelada'` → `diets.status = false`.
3. **Despacho** `DietsManager` (tiempo real; respaldo cada 3 s): toda versión nueva (edición, cancelación o alta) queda `modify='si'` = cambio sin confirmar. Se lista primero, con banner, contador en las pestañas y en el título del navegador; al llegar un aviso (`checkPending`) dispara `diet-changes-arrived` (sonido Web Audio + notificación del navegador si el despachador pulsó "Activar alertas"). Compara la versión actual contra la **última confirmada** (cambios acumulados). `confirmChanges($dietId)` (permiso `confirmDelivery`) → `DietService::confirmChanges` pone `modify='no'` en todas las versiones pendientes de la dieta. La v1 se crea con `modify='no'` (las dietas nuevas no alertan). La migración `2026_10_18_000000_confirm_old_closing_versions` confirma los cierres de más de un día para no alertar por históricos.
4. **Reportes** `GET /diet-reports` y `/entrega/index` → `nutritionReport()` (totales diarios/mensuales por tiempo de comida sobre la última versión de dietas activas).

5. **Finalizar (alta)** `PATCH /dietas/{diet}/finalizar` (`diets.finish`, permiso `editDiets`) → `DietService::finishDiet`: versión copia con `changes='finalizada'` y `status=FINISHED`. No borra nada. Sale de las dietas activas (pestaña "Finalizadas" del nutricionista; el despachador la ve en "Canceladas y finalizadas"). Cancelar y finalizar solo aplican a dietas activas (`closeDiet`). `Diet::CLOSING_CHANGES` (`cancelada`, `finalizada`) marca el fin del conteo en el reporte y la facturación. Para filtrar activas, usar `Diet::active()` / `isActive()`.

6. **Despacho y recepción** (`diet_dispatches`, `App\Services\DispatchService`): un registro por **dieta × día × tiempo de comida**. En `DietsManager` el despachador elige el servicio (`?servicio=`, por defecto el último cuya hora de `timeFood.schedule` ya pasó), marca las dietas listas (checkbox) y pulsa «Despachar» (permiso `confirmDelivery`). No se puede despachar una dieta con cambios sin confirmar, que no incluya ese tiempo o ya despachada. El nutricionista ve los pendientes en `App\Livewire\DispatchReceipts` (arriba de su panel, tiempo real) y confirma la recepción (permiso `confirmReception` → `received_at`/`received_by`).
7. **Excel solo de lo recibido**: desde `config('dispatch.required_from')` (`DISPATCH_REQUIRED_FROM`, por defecto `2026-10-15`; vacío = no se exige) `DietReportService::activeDietsByDay($mes, receivedOnly: true)` solo deja los tiempos con despacho recibido (los demás en `withheld`). Lo usan `BillingService::monthly()` (aviso `notReceived` en `/precios`) y el Excel del reporte (`monthlyCensus($mes, receivedOnly: true)`, fila «Tiempos sin recepción» en la hoja Resumen). Los días anteriores al corte conservan la regla previa. La vista web del reporte nutricional sigue mostrando lo programado.
8. **Ciclo diario** (`App\Services\DietDayService`, tabla `diet_days`):
   - **Autorización:** crear la dieta autoriza el día de creación; cada día siguiente lo autoriza «Continuar dieta» (`NutritionDiets::continueDiet`, permiso `continueDiets`: nutricionista, enfermería, admin), en cualquier momento del día anterior. Crea la versión `continuada`. Sin autorización, `DispatchService::blockReason` impide el despacho.
   - **Completada:** al confirmar la recepción (`DispatchService::confirmReceipt`) o al dar de alta, `evaluate()` revisa si se recibieron todos los tiempos exigibles del día (`requiredMeals`: el día de creación, los de hora igual o posterior a la creación más fraccionada; el día del alta, los anteriores a la hora del alta; el día de una cancelación no se completa). Si es así: `diet_days.completed_at`, versión `completada` y `status = COMPLETED`.
   - `Diet::active()` / `isActive()` = en curso (`OPEN_STATUSES`: ACTIVE y COMPLETED). `Diet::DAY_CHANGES` (`completada`, `continuada`) son copias sin cambio clínico: `modify='no'`, salvo que ya haya cambios pendientes. No cuentan como «Con cambios».
   - `diets:start-day` (programado 00:01; también se ejecuta al despachar) vuelve a Activa las Completadas que se continuaron para hoy.
   - **Facturación:** desde `config('dispatch.completion_required_from')` (`COMPLETION_REQUIRED_FROM`, por defecto `2026-10-16`) `activeDietsByDay($mes, receivedOnly: true)` solo cuenta los días completados (los demás en `withheld`). Excepción: el día del alta o de la cancelación cuenta lo recibido aunque el día no se haya completado (alta: los tiempos anteriores a la hora del alta y los despachados antes; cancelación: los despachados antes de cancelar). Aplica a `BillingService::monthly()` y al Excel del reporte. Tests: `tests/Feature/DietDayCompletionTest.php`.
9. **Despachos del día** `/despachos` (`dispatches.index`, permiso `viewDispatches`: nutricionista y despachador): `App\Livewire\DispatchLog`, filtros `?fecha=`, `servicio`, `estado` (`pending`/`received`), `q`; resumen por tiempo (`DispatchService::daySummary`). La columna Tiempo de las tablas del despachador y del nutricionista marca el estado del día con `x-meal-tag` (`DispatchService::statusByDiet`): turquesa sólido con check = entregado (recibido), borde punteado con camión = despachado sin recibir; leyenda `x-meal-legend`.

### Tiempo real (sin polling)

- Servidor: `App\Support\LiveUpdates::touch($kind)` junta los cambios (`diet`, `dispatch`, `day`) y, al confirmar la transacción, emite **un** `App\Events\DietsChanged` (`ShouldBroadcastNow`, canal privado `diets`, evento `.diets.changed`, payload solo `kinds`: sin datos clínicos). Lo disparan los eventos de modelo registrados en `AppServiceProvider` (`DietHistory` created, `Diet`/`DietDispatch`/`DietDay` saved) y las actualizaciones por consulta (`confirmChanges`, `confirmReceipt`, `startDay`), que llaman a `touch` a mano. Si el servidor WebSocket falla, `rescue` registra el error y la operación sigue (timeout `PUSHER_TIMEOUT`, 2 s). `LiveUpdates::muted()` para seeders/cargas masivas.
- Canal `diets` (`routes/channels.php`): usuarios activos con `viewActiveDiets`, `viewDiets`, `viewDispatches`, `confirmDelivery` o `confirmReception`.
- Navegador: `resources/js/live.js` → `window.ntLive(callback, { kinds, canRun, fallbackMs, safetyMs })` en el `x-init` de `DietsManager` (3 s), `DispatchReceipts` y `DispatchLog` (`kinds: ['dispatch']`, 5 s / 10 s) y `NutritionDiets` (5 s, `canRun` = sin modal abierto). Sin conexión WebSocket consulta cada `fallbackMs` (como el antiguo poll); conectado, solo cada 60 s por seguridad. No consulta con la pestaña oculta. **No volver a usar `wire:poll`.** `ntLive` devuelve `{ stop() }` y nunca una función: Alpine ejecuta en el acto la función que devuelve una expresión de `x-init`. Los bloques `@script` deben empezar con `let`/`const` (no con un comentario): Livewire los evalúa con Alpine y, si no, los trata como expresión y fallan.
- `.env`: `BROADCAST_DRIVER=pusher`, `PUSHER_APP_ID/KEY/SECRET`, `PUSHER_HOST`, `PUSHER_PORT`, `PUSHER_SCHEME` (local Soketi: `app-id`/`app-key`/`app-secret`, `127.0.0.1`, `6001`, `http`). Tras cambiar las `VITE_PUSHER_*`, `npm run build`. Sin `VITE_PUSHER_APP_KEY` no hay WebSocket y todo funciona con el respaldo. Tests: `tests/Feature/LiveUpdatesTest.php`.

Nunca se edita un `DietHistory` existente (salvo el flag `modify`); todo cambio clínico es una nueva versión.

### Roles y permisos (seeder `RolesAndPermissionsSeeder`)

| Rol | Permisos |
|---|---|
| `nutricionista` | `createDiets`, `editDiets`, `viewDiets`, `viewReports`, `confirmReception`, `viewDispatches`, `viewActiveDiets`, `continueDiets` |
| `enfermeria` (creado desde `/admin/roles`) | según la matriz; la migración `2026_10_22_000000_create_diet_days_table` le da `continueDiets` |
| `despachador` | `viewDiets`, `confirmDelivery`, `viewDispatches`, `generateFiles` |

Convención de permisos: **camelCase verbo+Recurso**. Middleware alias registrados: `role`, `permission`, `role_or_permission`.

### Deuda técnica conocida

Corregido el 2026-10-15: columnas `modify`/`confirmed` (migración idempotente), `cancelDiet` (crea versión `cancelada`), agregación de `timeFood` (normaliza `media_manana`/`mediamanana`, incluye `merienda`), `index()` ahora en `/reportes/produccion`, rutas protegidas con `permission:`, registro público tras `REGISTRATION_ENABLED` (por defecto off), contraseñas del seeder vía `SEED_USER_PASSWORD`, eliminados `PacienteActualizado` y `App\Models\Role`.

Pendiente:
- Claves de `timeFood` inconsistentes entre `config/timeFood.php` (`mediamanana`, `merienda`) y `components/form-diet.blade.php` (`media_manana`, sin `merienda`). Unificar y migrar los datos.
- `resources/views/navigation-menu.blade.php` no se usa (el layout incluye `navigation.blade.php`) y referencia rutas inexistentes (`dashboard`, `dietas.canceladas`, `historial.index`, `costos.index`).
- `diets.confirmed` no se usa (se eliminó `DietsManager::confirmRecord`; la confirmación del despachador es `diet_histories.modify`).
- Tests de auth fallan sin `npm run build` (falta el manifest de Vite) y sin `APP_KEY` en el entorno de tests.

---

## 4. Configuración regional

Aplicado en `config/app.php` y `config/database.php`:

```php
'timezone'        => env('APP_TIMEZONE', 'America/Bogota'),
'locale'          => env('APP_LOCALE', 'es'),
'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
'faker_locale'    => env('APP_FAKER_LOCALE', 'es_ES'),   // Faker no incluye es_CO
// mysql
'timezone'        => env('DB_TIMEZONE', '-05:00'),   // Colombia no tiene horario de verano
```

`.env`:
```
APP_TIMEZONE=America/Bogota
APP_LOCALE=es
APP_FAKER_LOCALE=es_ES
DB_TIMEZONE=-05:00
```

Reglas:
- La sesión MySQL en `-05:00` hace que `CURDATE()`, `NOW()` y `DATE(created_at)` coincidan con `now()` de Laravel.
- **Datos existentes**: los `created_at` previos se guardaron en UTC. Si importan para reportes históricos, migrar con `UPDATE … SET created_at = CONVERT_TZ(created_at,'+00:00','-05:00')` (una sola vez, con backup).
- Traducciones: `composer require laravel-lang/common --dev && php artisan lang:add es` (validación, auth, paginación).
- Fechas en vistas: `$date->locale('es')->isoFormat('D [de] MMMM YYYY, h:mm a')`. Carbon toma el locale de la app.
- Moneda/números (si aplica a costos): `Number::currency($v, 'COP', 'es_CO')` requiere `ext-intl`.

---

## 5. Módulo: Administración de usuarios (implementado)

Rol `admin` con CRUD de usuarios, asignación de roles y matriz de permisos. Sin dependencias nuevas.

| Pieza | Archivo |
|---|---|
| Migración | `2026_10_16_000000_add_admin_fields_to_users_table` → `phone` (E.164), `is_active`, `whatsapp_opt_in`, `deleted_at` |
| Lógica | `app/Services/UserService.php` (create/update/toggleActive/delete/restore) |
| Autorización | `app/Policies/UserPolicy.php` + `Gate::before` para `admin` en `AuthServiceProvider` |
| UI | `app/Livewire/Admin/UsersManager.php` (`/admin/usuarios`, `admin.users.index`) y `RolesManager.php` (`/admin/roles`, `admin.roles.index`), vistas en `resources/views/livewire/admin/` |
| Tests | `tests/Feature/Admin/UsersManagementTest.php` |

Guard: roles y permisos siempre con `guard_name` = `UserService::GUARD` (`web`), explícito al crearlos y al consultarlos. Las rutas usan `auth:sanctum`, que durante la petición cambia el guard por defecto a `sanctum`; sin guard explícito Spatie crea el rol en `sanctum` y falla al asignarle permisos (corregido con la migración `2026_10_20_000000_move_sanctum_roles_to_web_guard`).

Permisos: `viewUsers`, `createUsers`, `editUsers`, `deleteUsers`, `manageRoles`, `viewAuditLog` (el rol `admin` siempre tiene todos; `RolesManager` no permite quitárselos).

Reglas de negocio (en `UserService`, lanzan `ValidationException`):
- No desactivarse ni eliminarse a sí mismo; debe quedar ≥ 1 admin activo.
- Eliminar = `is_active=false` + soft delete; restaurable. **Nunca** borrado físico: `diet_histories.updated_by` borra en cascada. `Actions/Jetstream/DeleteUser` también hace soft delete.
- Desactivar/eliminar cierra las sesiones del usuario (`sessions` en BD). El login (`FortifyServiceProvider::authenticateUsing`) rechaza usuarios inactivos; los eliminados no se encuentran.
- Email único incluyendo eliminados (restaurar en lugar de recrear). Teléfono `^\+[1-9]\d{7,14}$`.
- Contraseña en edición: vacía = se conserva.
- Un rol solo se elimina si no tiene usuarios (incluidos los eliminados) y no es `admin`.

`Gate::before` concede todo al admin **excepto** la habilidad `delete`, para que `UserPolicy::delete` impida la autoeliminación. Tras cambiar permisos se ejecuta `forgetCachedPermissions()`.

Login: `App\Http\Responses\LoginResponse` (registrado en `FortifyServiceProvider::register`, también para 2FA) y `RedirectIfAuthenticated` usan `User::homeUrl()`: nutricionista → `dashboard.nutrition`, despachador → `dashboard.dispatch`, admin → `admin.users.index`, sin rol → `profile.show`. No usar `Fortify::redirects()` para esto (solo lee configuración). La ruta `/` cierra la sesión: nunca redirigir allí a un usuario autenticado. El seeder usa `firstOrCreate`, así que cambiar una contraseña en el seeder no modifica usuarios que ya existen. El seeder crea los usuarios demo y `admin@nutritrack.com` (`SEED_ADMIN_EMAIL`) con contraseña `SEED_USER_PASSWORD` o una aleatoria que se imprime (nunca contraseñas fijas).

Tests de Livewire/HTTP: usar `$this->withoutVite()` y `flushSession()` al cambiar de usuario en un mismo test (Jetstream `AuthenticateSession`).

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

## 7. Módulos: Reportes PDF y Excel

### Implementado: reporte nutricional + Excel del mes

- **Regla del alta** (`docs/bussiness-rules/facturacion-alta-paciente.md`, obligatoria en reportes, facturación, Excel, PDF e indicadores): el día del alta solo cuentan los tiempos con hora en `config('timeFood.schedule')` igual o anterior a la hora del alta (alta 15:00 → desayuno y almuerzo, no cena); los tiempos sin horario (fraccionada) no cuentan ese día. Implementada en `DietReportService::activeDietsByDay()`, que devuelve por dieta y día la lista `meals` que cuenta: usar esa lista, **no** `timeFood` de la versión. El día de una cancelación no cuenta por horario (cierre del día); con la regla de lo recibido (facturación y Excel) sí cuentan los tiempos despachados antes de cancelar y recibidos. Tests: `tests/Feature/DischargeMealCutoffTest.php`.
- `app/Services/Reports/DietReportService.php` — fuente única de datos:
  - `monthlyCensus($mes)`: **última versión × días**. Cada dieta usa su última versión registrada hasta el cierre del mes (sin la versión `cancelada`, que es copia). Sus tiempos de comida cuentan 1 bandeja por cada día activo: desde la creación (v1) hasta la cancelación (versión `cancelada` o, si es antigua, `diets.updated_at`), evaluado al cierre de cada día. Las versiones intermedias **no** se usan. Devuelve `days`, `totals`, `totalTrays`, `dietDays`, `avgDailyDiets`, `consistency`, `specifications`, `isolationDays` y `perDiet`.
  - `monthlyHistory($mes, 6)`, `currentSnapshot()` (dietas activas hoy) y `mealLabels()`/`label()` (etiquetas de `config`, con respaldo legible para claves fuera del catálogo).
  - `MEALS` en orden cronológico; normaliza `media_manana`/`mediamanana`/`Desayuno`.
- Vista `reports-diets-nutricion` (rutas `diet.reports` y `entrega.index`, `?mes=YYYY-MM`): selector de mes, KPIs, barras apiladas diarias (Chart.js, paleta categórica validada en orden fijo), tabla diaria (alternativa accesible), totales por tiempo, últimos 6 meses con Excel por fila, especificaciones y aislamiento de hoy. Visible con `viewReports` o `generateFiles`.
- Excel `GET /reportes/mensual/excel?mes=YYYY-MM` (`reports.monthly.excel`, `ReportExportController::monthlyExcel`) → `app/Exports/MonthlySummaryExport.php` con hojas `Resumen`, `Diario` y `Por paciente`. Valida `mes` (formato y no futuro).
- **Laravel Excel:** las hojas implementan `WithStrictNullComparison` (si no, los `0` quedan como celdas vacías), y los separadores son `['']`, no `[]`, porque las filas vacías se descartan y desplazan los estilos por número de fila.
- Tests: `tests/Feature/NutritionReportTest.php` (usa `travelTo`).

### Pendiente

Skills: `.claude/skills/pdf-reports/SKILL.md`, `.claude/skills/excel-reports/SKILL.md`.

### Dependencias
```bash
composer require barryvdh/laravel-dompdf:^3.0   # PDF desde Blade
composer require maatwebsite/excel:^3.1         # instalado (3.1.70)
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

## 7b. Historial, precios y menú

- **Historial** `/historial?dieta=ID` (`diets.history`, permiso `viewDiets`): `App\Livewire\DietHistoryViewer` + `App\Services\DietHistoryService::timeline()`. Versiones de la más nueva a la más vieja, con el tipo (creación, edición o cancelación). En listas marca cada elemento como mantenido, agregado o quitado; en campos simples guarda el valor anterior. Enlazado desde la fila de la tabla del nutricionista.
- **Facturación** (formato del hospital `Facturación 1 al 31 de Enero 2026 UCI.xlsx`):
  - Catálogo en `config/billing.php`: 17 tipos de dieta con sus servicios (81 filas), `services` (Comida = cena; Fraccionada **no** se factura), `snack_type` = `entrecomidas`, `unit` = `BILLING_UNIT` (UCI).
  - `diet_histories.billing_type`: obligatorio en los formularios (`StoreDietRequest`/`StoreDietVersionRequest`), lo elige el nutricionista y se copia al cancelar. Las versiones antiguas sin tipo aparecen como advertencia y no se facturan.
  - Precios por tipo × servicio en `service_prices` (`billing_type`, `service`, `price`, `valid_from`; único por los tres). Los digita el jefe en `/precios` (`PricesManager`, permiso `managePrices`).
  - `App\Services\BillingService::monthly()`: usa `DietReportService::activeDietsByDay()` (misma regla que el reporte). Los refrigerios de tipos sin esa fila van a Entrecomidas, y cada día se valora con su precio vigente.
  - Excel `GET /facturacion/excel?mes=YYYY-MM` (`billing.excel`) → `App\Exports\BillingWorkbook` (PhpSpreadsheet directo). Hoja `{MES} {AÑO}`: días 1–31 (los inexistentes en gris), `AJ=SUM`, `AK=D*AJ`; si el precio cambió en el mes, `AK` lleva el valor calculado y un comentario. Incluye resumen AM:AN, subtotal, total del mes y la hoja `FACTURACIÓN MES A MES `.
- Rol `jefe_nutricion`: `viewDiets`, `viewReports`, `generateFiles`, `managePrices`; al iniciar sesión va a `prices.index`.
- `navigation.blade.php`: cada enlace se condiciona con el mismo rol o permiso que exige su ruta (`@role` / `@can`), para no mostrar enlaces que terminan en 403.
- Acceso (`auth/login`, `auth/forgot-password`, `auth/reset-password`): comparten `<x-auth-shell>` (CSS propio, sin Tailwind ni Vite; tema claro/oscuro con tokens en `:root`, `localStorage('nt-theme')` y `prefers-color-scheme`; panel con la descripción de la plataforma y el ícono animado, que respeta `prefers-reduced-motion`) y `<x-auth-alerts>`.
- Recuperar contraseña: correo en español (`ResetPassword::toMailUsing` en `AuthServiceProvider`), textos en `lang/es/passwords.php` y `lang/es/validation.php` (parcial; lo demás cae a `en`). Si el SMTP falla, `User::sendPasswordResetNotification` registra el error y muestra un mensaje en el formulario en lugar de un 500. En local `MAIL_MAILER=log` (el enlace queda en `storage/logs/laravel.log`); en producción hay que configurar un SMTP real.

## 7d. Seguridad y cumplimiento

Detalle y lista de tareas de cada institución: `docs/seguridad/cumplimiento.md`. Configuración en `config/security.php`.

- **Política de datos (Ley 1581):** pública en `/politica-de-datos` (`privacy.policy`, enlazada en el login). Al ingresar, `EnsureAccountCompliance` (alias `compliance`, en el grupo de rutas de la app y como middleware persistente de Livewire) envía a `/politica-de-datos/aceptar` hasta que el usuario acepte la versión vigente (`users.privacy_accepted_at`/`privacy_version`, `PRIVACY_POLICY_VERSION`). El texto (`resources/views/legal/policy-text.blade.php`) es un borrador para revisión jurídica.
- **2FA obligatorio** para los roles de `TWO_FACTOR_ROLES` (por defecto `admin,jefe_nutricion`): sin 2FA confirmada se redirige a `profile.show#two-factor`. En local se desactiva con `TWO_FACTOR_ROLES=`.
- **HTTPS:** `FORCE_HTTPS` (por defecto en producción) → `SecurityHeaders` redirige a https y envía HSTS; `URL::forceScheme`; cookie de sesión `secure`. Detrás de un proxy: `TRUSTED_PROXIES`. `SecurityHeaders` envía además `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` y `no-store` en páginas con sesión.
- **Perfil (`/user/profile`):** en español (`lang/es.json` traduce los textos de Jetstream/Fortify; `lang/es/validation.php`). Sin «Eliminar cuenta» (`Features::accountDeletion()` desactivado: solo el admin elimina usuarios). El correo no se edita: `UpdateUserProfileInformation` solo guarda el nombre y la vista lo muestra de solo lectura; lo cambia el admin en `/admin/usuarios`.
- **Contraseñas:** `Password::defaults()` = mínimo 10, mayúsculas, minúsculas y números. El seeder ya no tiene contraseñas fijas (`SEED_USER_PASSWORD` o aleatoria).
- **Auditoría:** `App\Services\AuditLogger::log($event, $descripcion, $subject, $meta)` → `audit_logs` (`App\Models\AuditLog`, inmutable; eventos en `AuditLog::EVENTS`). Accesos por `App\Listeners\AuditAuthEvents` y `FortifyServiceProvider::authenticateUsing` (intentos fallidos y bloqueos; el callback lanza la excepción sin el evento `Failed`). También `UserService`, `RolesManager`, `PricesManager`, exportaciones Excel y `DietHistoryViewer` (consulta de historial). **No poner datos clínicos ni nombres de pacientes en la descripción** (solo habitación o IDs). Visor `/admin/auditoria` (`admin.audit.index`, permiso `viewAuditLog`, solo admin).
- **Respaldos:** cifrados con `BACKUP_ARCHIVE_PASSWORD`; programados `backup:run` 01:00, `backup:clean` 01:30, `backup:monitor` 02:00 y `backups:verify` los lunes 03:00 (último respaldo reciente, cifrado, legible y con las tablas de dietas). En local no hay `mysqldump`, así que no se generan respaldos.
- Tests: `ComplianceTest`, `AuditLogTest`, `VerifyBackupTest`. `Tests\TestCase` desactiva la aceptación de la política, el 2FA obligatorio y el HTTPS forzado; `ComplianceTest` los activa.

## 7c. Paginación de tablas

Skill `.claude/skills/table-pagination`: 15 por página, parámetros `page` y `per_page` (`App\Support\Pagination::perPage()` acepta 15/30/50; cualquier otro → 15), control común `resources/views/pagination/table.blade.php` (Anterior/Siguiente, números, selector de página, "1–15 de N registros"; en Livewire `->links('pagination.table', ['livewire' => true])`). Los filtros se conservan (`withQueryString()` / `#[Url]`) y al cambiar un filtro se vuelve a la página 1.
- Panel de dietas activas (`/dashboard/nutrition`, permiso `viewActiveDiets`, no rol; lo tiene el nutricionista y se asigna a otros roles como enfermería): sin `createDiets`/`editDiets` queda de solo lectura (sin botones ni modales). `User::homeUrl()` envía a los roles sin panel propio según su permiso (`viewActiveDiets` → panel, `viewDispatches` → despachos, `viewDiets` → historial).
- Panel del nutricionista: `App\Livewire\NutritionDiets` (indicadores, pestañas `?tab=active|cancelled|finished`, búsqueda en vivo `?q=` de la pestaña abierta, `page`/`per_page`). Se refresca en tiempo real con `$wire.$refresh()` y lo aplaza si hay un `.overlay.open` o `.chip-select.open` (edición en curso). Los modales de edición llevan `wire:ignore` y la fila `wire:key` con la versión (si la dieta cambia, el modal se recrea con los datos nuevos); el modal de registro está fuera del componente. Cambiar de pestaña o de búsqueda vuelve a la página 1.
- Despachador, historial (`DietHistoryViewer`) y usuarios: Livewire `WithPagination`.
- Orden: las tablas de dietas y despachos van por habitación ascendente en orden natural (`Diet::orderByRoom()` / `DietDispatch::orderByRoom()`: `LENGTH(habitation)`, luego `habitation`; 12 < 101 < 1001). En el despachador las dietas con cambios sin confirmar siguen arriba.
- Despachador, pestaña activas: por defecto solo lista las dietas cuya versión actual incluye el servicio elegido (`media_manana`/`mediamanana` equivalen) más las que tienen cambios sin confirmar; `?todas=1` («Mostrar todas las activas») quita el filtro.
- No se paginan tablas de agregados o catálogos (reportes, facturación, precios, matriz de roles).

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
