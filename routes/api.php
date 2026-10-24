<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DietController;
use App\Http\Controllers\Api\V1\DispatchController;
use App\Http\Controllers\Api\V1\DispatchLogController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\ReceiptController;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

/*
| API v1 del cliente de escritorio (Flutter, Windows y macOS; NUT-RN-004). Siempre JSON
| (ForceJsonResponse en el grupo api). Tokens de Sanctum; la empresa activa llega en el
| encabezado X-Company-Id (UseCompanyHeader). Los controladores llaman a los mismos
| servicios que la web.
|
| Límites con ThrottleRequests directo: el alias 'throttle' lo reemplaza CustomThrottleLogin
| (límite del login web, 3 intentos por correo, con redirección a /login).
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/meta', MetaController::class)->middleware(ThrottleRequests::class.':60,1')->name('meta');

    Route::middleware(ThrottleRequests::class.':10,1')->group(function () {
        Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('/auth/two-factor', [AuthController::class, 'twoFactor'])->name('auth.two-factor');
    });

    Route::middleware(['auth:sanctum', 'company.header'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', MeController::class)->name('me');

        // Canal privado «diets» del tiempo real (routes/channels.php) autorizado con el token
        Route::match(['get', 'post'], '/broadcasting/auth', [BroadcastController::class, 'authenticate'])->name('broadcasting.auth');

        // Operación: mismas reglas que la web (política aceptada, 2FA de los roles, empresa asignada)
        Route::middleware(['compliance', 'company'])->group(function () {
            Route::middleware('permission:confirmDelivery')->group(function () {
                Route::get('/dispatch/board', [DispatchController::class, 'board'])->name('dispatch.board');
                Route::post('/dispatch', [DispatchController::class, 'store'])->name('dispatch.store');
                Route::post('/diets/{diet}/confirm-changes', [DispatchController::class, 'confirmChanges'])->whereNumber('diet')->name('diets.confirm-changes');
            });
            // «No llegó» (confirmReception) o «Anular despacho» (confirmDelivery): el permiso depende de kind
            Route::post('/dispatches/{dispatch}/void', [DispatchController::class, 'void'])->whereNumber('dispatch')->name('dispatches.void');

            Route::middleware('permission:confirmReception')->group(function () {
                Route::get('/receipts/pending', [ReceiptController::class, 'pending'])->name('receipts.pending');
                Route::post('/receipts', [ReceiptController::class, 'confirm'])->name('receipts.confirm');
            });

            // Panel de dietas (paso 3): mismos permisos que la web
            Route::get('/diets', [DietController::class, 'index'])->middleware('permission:viewActiveDiets')->name('diets.index');
            Route::post('/diets', [DietController::class, 'store'])->middleware('permission:createDiets')->name('diets.store');
            Route::post('/diets/{diet}/continue', [DietController::class, 'continue'])->middleware('permission:continueDiets')->whereNumber('diet')->name('diets.continue');
            Route::middleware('permission:editDiets')->whereNumber('diet')->group(function () {
                Route::put('/diets/{diet}', [DietController::class, 'update'])->name('diets.update');
                Route::get('/diets/{diet}/closing-summary', [DietController::class, 'closingSummary'])->name('diets.closing-summary');
                Route::post('/diets/{diet}/cancel', [DietController::class, 'cancel'])->name('diets.cancel');
                Route::post('/diets/{diet}/finish', [DietController::class, 'finish'])->name('diets.finish');
            });

            // Despachos del día (Premium)
            Route::get('/dispatches/day', DispatchLogController::class)
                ->middleware(['permission:viewDispatches', 'premium:dispatch_log'])->name('dispatches.day');
        });
    });
});
