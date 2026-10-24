<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MetaController;
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
    });
});
