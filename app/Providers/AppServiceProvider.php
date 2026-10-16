<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAccountCompliance;
use App\Models\Diet;
use App\Models\DietDay;
use App\Models\DietDispatch;
use App\Models\DietHistory;
use App\Support\LiveUpdates;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // HTTPS obligatorio (SecurityHeaders redirige; aquí las URL generadas)
        if (config('security.force_https')) {
            URL::forceScheme('https');
        }

        // Las acciones de Livewire (/livewire/update) también exigen política aceptada y 2FA
        Livewire::addPersistentMiddleware([EnsureAccountCompliance::class]);

        // Contraseñas: mínimo 10 caracteres con mayúsculas, minúsculas y números
        Password::defaults(fn () => Password::min(10)->letters()->mixedCase()->numbers());

        // Tiempo real: toda escritura de dietas, despachos o días avisa a las pantallas abiertas.
        // Las actualizaciones masivas por consulta (sin eventos de modelo) llaman a LiveUpdates::touch.
        DietHistory::created(fn () => LiveUpdates::touch(LiveUpdates::DIET));
        Diet::saved(fn () => LiveUpdates::touch(LiveUpdates::DIET));
        DietDispatch::saved(fn () => LiveUpdates::touch(LiveUpdates::DISPATCH));
        DietDay::saved(fn () => LiveUpdates::touch(LiveUpdates::DAY));
    }
}
