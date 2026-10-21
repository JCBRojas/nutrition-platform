<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAccountCompliance;
use App\Http\Middleware\EnsurePremiumFeature;
use App\Models\Diet;
use App\Models\DietDay;
use App\Models\DietDispatch;
use App\Models\DietHistory;
use App\Services\SubscriptionService;
use App\Support\CurrentCompany;
use App\Support\LiveUpdates;
use Illuminate\Support\Facades\Blade;
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
        // Una instancia por petición: memoriza el plan vigente (el menú lo consulta varias veces)
        $this->app->scoped(SubscriptionService::class);

        // Empresa activa del usuario (NUT-RN-003); la usan los scopes globales de dietas
        $this->app->singleton(CurrentCompany::class);
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
        Livewire::addPersistentMiddleware([EnsureAccountCompliance::class, EnsurePremiumFeature::class]);

        // @premium('history') … @endpremium: la función está en el plan de la institución (admin siempre)
        Blade::if('premium', fn (string $feature) => app(SubscriptionService::class)->hasFeature($feature));

        // Contraseñas: mínimo 10 caracteres con mayúsculas, minúsculas y números
        Password::defaults(fn () => Password::min(10)->letters()->mixedCase()->numbers());

        // Tiempo real: toda escritura de dietas, despachos o días avisa a las pantallas abiertas.
        // Las actualizaciones masivas por consulta (sin eventos de modelo) llaman a LiveUpdates::touch.
        // Cada aviso lleva la empresa de la dieta: las pantallas de otras empresas lo ignoran.
        DietHistory::created(fn (DietHistory $h) => LiveUpdates::touch(LiveUpdates::DIET, LiveUpdates::companyOf($h->diet_id)));
        Diet::saved(fn (Diet $d) => LiveUpdates::touch(LiveUpdates::DIET, $d->company_id));
        DietDispatch::saved(fn (DietDispatch $d) => LiveUpdates::touch(LiveUpdates::DISPATCH, LiveUpdates::companyOf($d->diet_id)));
        DietDay::saved(fn (DietDay $d) => LiveUpdates::touch(LiveUpdates::DAY, LiveUpdates::companyOf($d->diet_id)));
    }
}
