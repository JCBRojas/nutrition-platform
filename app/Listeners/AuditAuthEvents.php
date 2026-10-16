<?php

namespace App\Listeners;

use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

/**
 * Auditoría de accesos: inicios y cierres de sesión, intentos fallidos, bloqueos,
 * restablecimiento de contraseña y cambios de 2FA. Nunca guarda contraseñas.
 */
class AuditAuthEvents
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => fn (Login $e) => AuditLogger::log('login', "Ingresó {$e->user->email}", $e->user, ['remember' => $e->remember], $e->user->getAuthIdentifier()),
            Logout::class => fn (Logout $e) => $e->user && AuditLogger::log('logout', "Salió {$e->user->email}", $e->user, [], $e->user->getAuthIdentifier()),
            Failed::class => fn (Failed $e) => AuditLogger::log('login_failed', 'Intento fallido con '.($e->credentials['email'] ?? '—'), $e->user, ['email' => $e->credentials['email'] ?? null], $e->user?->getAuthIdentifier()),
            Lockout::class => fn (Lockout $e) => AuditLogger::log('lockout', 'Bloqueo de inicio de sesión para '.($e->request->input('email') ?? '—'), null, ['email' => $e->request->input('email')]),
            PasswordReset::class => fn (PasswordReset $e) => AuditLogger::log('password_reset', "Restableció la contraseña {$e->user->email}", $e->user, [], $e->user->getAuthIdentifier()),
            TwoFactorAuthenticationConfirmed::class => fn ($e) => AuditLogger::log('two_factor_enabled', "Activó 2FA {$e->user->email}", $e->user, [], $e->user->getKey()),
            TwoFactorAuthenticationDisabled::class => fn ($e) => AuditLogger::log('two_factor_disabled', "Desactivó 2FA {$e->user->email}", $e->user, [], $e->user->getKey()),
            RecoveryCodesGenerated::class => fn ($e) => AuditLogger::log('recovery_codes', "Regeneró códigos de recuperación {$e->user->email}", $e->user, [], $e->user->getKey()),
        ];
    }
}
