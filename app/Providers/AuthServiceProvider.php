<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\UserService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // El administrador tiene todos los permisos; las reglas de negocio
        // (no auto-eliminarse, último admin) se validan en UserService.
        Gate::before(fn (User $user, string $ability) => $user->hasRole(UserService::ADMIN_ROLE) && $ability !== 'delete' ? true : null);
    }
}
