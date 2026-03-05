<?php

use App\Models\Diet;
use Illuminate\Support\Facades\Auth;
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
    return view('auth.login-b');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {

        $diets = Diet::all();
        if (Auth::user()->hasRole('despachador')) {
            return view('dashboard-dispatcher');
        }else {
            return view('dashboard', compact('diets'));
        }
    })->name('dashboard');

    Route::post('/dietas', [App\Http\Controllers\DietController::class, 'createDiet'])->name('dietas.create');
    Route::put('/dietas/{diet}/version', [App\Http\Controllers\DietController::class, 'createNewVersionDiet'])->name('dietas.update');

});
