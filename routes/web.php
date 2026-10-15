<?php

use App\Http\Controllers\DietController;
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

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
     // 🔵 NUTRICIONISTA
    Route::middleware(['role:nutricionista'])->group(function () {
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

    Route::middleware(['permission:viewReports|generateFiles'])->group(function () {
        Route::get('/diet-reports', [DietController::class, 'nutritionReport'])->name('diet.reports');
        Route::get('/entrega/index', [DietController::class, 'nutritionReport'])->name('entrega.index');
        Route::get('/reportes/produccion', [DietController::class, 'index'])->name('reports.production');
    });
    // Route::get('/entrega/canceladas', [EntregaController::class, 'canceladas'])->name('dietas.canceladas');
    // Route::get('/entrega/historial', [EntregaController::class, 'historial'])->name('historial.index');
    // Route::get('/entrega/reportes', [EntregaController::class, 'reportes'])->name('reportes.nutricional');
});
