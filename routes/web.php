<?php

use App\Http\Controllers\BillingExportController;
use App\Http\Controllers\DietController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\ReportExportController;
use App\Livewire\Admin\AuditLogViewer;
use App\Livewire\Admin\RolesManager;
use App\Livewire\Admin\UsersManager;
use App\Livewire\DietHistoryViewer;
use App\Livewire\DispatchLog;
use App\Livewire\PricesManager;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    if (auth()->check()) {
        auth()->logout(); // Finaliza la sesión
        session()->invalidate(); // Invalida la sesión actual
        session()->regenerateToken(); // Regenera el token CSRF
    }

    return view('auth.login');
});

// Política de tratamiento de datos (pública; enlazada desde el inicio de sesión)
Route::get('/politica-de-datos', [PrivacyController::class, 'show'])->name('privacy.policy');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    // Aceptación de la política: libre del middleware compliance (si no, no se podría aceptar)
    Route::get('/politica-de-datos/aceptar', [PrivacyController::class, 'acceptForm'])->name('privacy.accept');
    Route::post('/politica-de-datos/aceptar', [PrivacyController::class, 'accept'])->name('privacy.accept.store');
});

// Rutas de la aplicación: exigen política aceptada y 2FA en los roles que la requieren (EnsureAccountCompliance).
// El perfil (/user/profile, donde se activa 2FA) lo registra Jetstream fuera de este grupo.
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'compliance',
])->group(function () {
    // 🔵 DIETAS ACTIVAS (nutricionista y quien tenga el permiso, p. ej. enfermería)
    Route::middleware(['permission:viewActiveDiets'])->group(function () {
        Route::get('/dashboard/nutrition', [DietController::class, 'nutritionDashboard'])
            ->name('dashboard.nutrition');
    });

    // 🟢 DESPACHADOR
    Route::middleware(['role:despachador'])->group(function () {
        Route::get('/dashboard/dispatch', [DietController::class, 'dispatcherDashboard'])
            ->name('dashboard.dispatch');
    });

    Route::post('/dietas', [DietController::class, 'createDiet'])
        ->middleware('permission:createDiets')->name('dietas.create');
    Route::put('/dietas/{diet}/version', [DietController::class, 'createNewVersionDiet'])
        ->middleware('permission:editDiets')->name('dietas.update');
    Route::patch('/dietas/{diet}/cancel', [DietController::class, 'cancelDiet'])
        ->middleware('permission:editDiets')->name('diets.canceled');
    Route::patch('/dietas/{diet}/finalizar', [DietController::class, 'finishDiet'])
        ->middleware('permission:editDiets')->name('diets.finish');

    Route::middleware('permission:managePrices')->group(function () {
        Route::get('/precios', PricesManager::class)->name('prices.index');
        Route::get('/facturacion/excel', [BillingExportController::class, 'excel'])->name('billing.excel');
    });

    Route::get('/despachos', DispatchLog::class)
        ->middleware('permission:viewDispatches')
        ->name('dispatches.index');

    Route::get('/historial', DietHistoryViewer::class)
        ->middleware('permission:viewDiets')->name('diets.history');

    Route::middleware(['permission:viewReports|generateFiles'])->group(function () {
        Route::get('/diet-reports', [DietController::class, 'nutritionReport'])->name('diet.reports');
        Route::get('/entrega/index', [DietController::class, 'nutritionReport'])->name('entrega.index');
        Route::get('/reportes/produccion', [DietController::class, 'index'])->name('reports.production');
        Route::get('/reportes/mensual/excel', [ReportExportController::class, 'monthlyExcel'])->name('reports.monthly.excel');
    });
    // 🟣 ADMINISTRACIÓN
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/usuarios', UsersManager::class)->middleware('permission:viewUsers')->name('users.index');
        Route::get('/roles', RolesManager::class)->middleware('permission:manageRoles')->name('roles.index');
        Route::get('/auditoria', AuditLogViewer::class)->middleware('permission:viewAuditLog')->name('audit.index');
    });

    // Route::get('/entrega/canceladas', [EntregaController::class, 'canceladas'])->name('dietas.canceladas');
    // Route::get('/entrega/historial', [EntregaController::class, 'historial'])->name('historial.index');
    // Route::get('/entrega/reportes', [EntregaController::class, 'reportes'])->name('reportes.nutricional');
});
