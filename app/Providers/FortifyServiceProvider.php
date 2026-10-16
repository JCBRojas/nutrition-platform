<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\LoginResponse;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\LockoutResponse as LockoutResponseContract;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, LoginResponse::class);

        $this->app->bind(LockoutResponseContract::class, function ($app) {
            return new class($app->make(LoginRateLimiter::class)) implements LockoutResponseContract
            {
                public function __construct(protected LoginRateLimiter $limiter) {}

                public function toResponse($request)
                {
                    $seconds = $this->limiter->availableIn($request);
                    $message = "Demasiados intentos de inicio de sesión. Inténtalo nuevamente en {$seconds} segundos.";

                    return redirect()
                        ->route('login')
                        ->withErrors([
                            Fortify::username() => $message,
                        ])
                        ->withInput($request->only(Fortify::username(), 'remember'));
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower($request->input(Fortify::username())).'|'.$request->ip()
            );

            return Limit::perMinute(3)->by($throttleKey);
        });

        $this->app->router->aliasMiddleware('throttle', \App\Http\Middleware\CustomThrottleLogin::class);

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(3)->by($request->session()->get('login.id'));
        });

        Fortify::authenticateUsing(function (Request $request) {
            $username = $request->input(Fortify::username());
            $throttleKey = Str::transliterate(Str::lower($username).'|'.$request->ip());

            if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                AuditLogger::log('lockout', "Bloqueo de inicio de sesión para {$username}", null, ['email' => $username, 'seconds' => $seconds]);

                throw ValidationException::withMessages([
                    Fortify::username() => "Demasiados intentos de inicio de sesión. Inténtalo nuevamente en {$seconds} segundos.",
                ])->status(302);
            }

            $user = User::where(Fortify::username(), $username)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                RateLimiter::hit($throttleKey);
                AuditLogger::log('login_failed', "Intento fallido con {$username}", $user, ['email' => $username, 'reason' => $user ? 'contraseña' : 'usuario inexistente'], $user?->id);

                throw ValidationException::withMessages([
                    Fortify::username() => 'Credenciales incorrectas.',
                ]);
            }

            RateLimiter::clear($throttleKey);

            if (! $user->is_active) {
                AuditLogger::log('login_failed', "Intento de usuario inactivo {$username}", $user, ['email' => $username, 'reason' => 'inactivo'], $user->id);
                throw ValidationException::withMessages([
                    Fortify::username() => 'Tu usuario está inactivo. Contacta al administrador.',
                ]);
            }

            return $user;
        });
    }
}
