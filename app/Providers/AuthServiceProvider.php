<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\UserService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;

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

        // Correo de recuperación de contraseña en español
        ResetPassword::toMailUsing(function (User $user, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]);
            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Restablecer contraseña | NutriTrack')
                ->greeting("Hola, {$user->name}")
                ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
                ->action('Crear contraseña nueva', $url)
                ->line("El enlace vence en {$minutes} minutos.")
                ->line('Si no solicitaste el cambio, ignora este correo: tu contraseña actual sigue funcionando.')
                ->salutation('Equipo NutriTrack');
        });
    }
}
