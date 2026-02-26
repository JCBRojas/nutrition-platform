<?php

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
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {

        $pacientes = App\Models\Paciente::all();
        if (Auth::user()->hasRole('despachador')) {
            return view('dashboard-live');
        }else {
            return view('dashboard', compact('pacientes'));
        }
        // return view('dashboard-live');
    })->name('dashboard');

});
    Route::post('/pacientes', [App\Http\Controllers\PacienteController::class, 'savePaciente'])->name('pacientes.save');
