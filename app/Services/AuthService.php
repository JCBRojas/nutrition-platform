<?php

namespace App\Services;

use App\Models\EmailDomain;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Features;

/**
 * Reglas de inicio de sesión compartidas por la web (Fortify::authenticateUsing) y la API del
 * cliente de escritorio (NUT-RN-004): límite de intentos, dominio autorizado (NUT-RN-002),
 * usuario inactivo y auditoría. Los errores van en el campo 'email' con el mensaje genérico.
 */
class AuthService
{
    public const MAX_ATTEMPTS = 3;

    /** Minutos de validez del ticket entre la contraseña y el código 2FA en la API */
    public const TWO_FACTOR_TICKET_MINUTES = 5;

    public function attempt(string $email, string $password, string $ip): User
    {
        $throttleKey = $this->throttleKey($email, $ip);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            AuditLogger::log('lockout', "Bloqueo de inicio de sesión para {$email}", null, ['email' => $email, 'seconds' => $seconds]);

            throw ValidationException::withMessages([
                'email' => "Demasiados intentos de inicio de sesión. Inténtalo nuevamente en {$seconds} segundos.",
            ])->status(429);
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey);
            AuditLogger::log('login_failed', "Intento fallido con {$email}", $user, ['email' => $email, 'reason' => $user ? 'contraseña' : 'usuario inexistente'], $user?->id);

            throw ValidationException::withMessages(['email' => 'Credenciales incorrectas.']);
        }

        // NUT-RN-002-01/-04: dominio no autorizado → mismo mensaje que credenciales inválidas
        if (! EmailDomain::allows($user->email)) {
            RateLimiter::hit($throttleKey);
            $domain = EmailDomain::of($user->email);
            AuditLogger::log('login_blocked_domain', "Ingreso rechazado: dominio {$domain} no autorizado", $user, ['domain' => $domain], $user->id);

            throw ValidationException::withMessages(['email' => 'Credenciales incorrectas.']);
        }

        RateLimiter::clear($throttleKey);

        if (! $user->is_active) {
            AuditLogger::log('login_failed', "Intento de usuario inactivo {$email}", $user, ['email' => $email, 'reason' => 'inactivo'], $user->id);

            throw ValidationException::withMessages(['email' => 'Tu usuario está inactivo. Contacta al administrador.']);
        }

        return $user;
    }

    /** El usuario debe escribir el código 2FA (la 2FA está habilitada en la plataforma y él la confirmó) */
    public function needsTwoFactor(User $user): bool
    {
        return Features::enabled(Features::twoFactorAuthentication()) && $user->hasTwoFactorEnabled();
    }

    /** Ticket cifrado y con vencimiento que identifica al usuario entre la contraseña y el código 2FA */
    public function twoFactorTicket(User $user): string
    {
        return Crypt::encryptString(json_encode([
            'user' => $user->id,
            'expires' => now()->addMinutes(self::TWO_FACTOR_TICKET_MINUTES)->timestamp,
        ]));
    }

    /** Valida el ticket y el código (TOTP o de recuperación); devuelve el usuario */
    public function completeTwoFactor(string $ticket, ?string $code, ?string $recoveryCode, string $ip): User
    {
        $invalid = fn (string $message = 'El código no es válido.') => ValidationException::withMessages(['code' => $message]);

        try {
            $data = json_decode(Crypt::decryptString($ticket), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw $invalid('La sesión de verificación no es válida. Inicia sesión de nuevo.');
        }

        $user = ($data['expires'] ?? 0) >= now()->timestamp ? User::find($data['user'] ?? 0) : null;
        if (! $user || ! $user->is_active || ! $this->needsTwoFactor($user)) {
            throw $invalid('La sesión de verificación venció. Inicia sesión de nuevo.');
        }

        $throttleKey = 'two-factor|'.$user->id.'|'.$ip;
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw $invalid('Demasiados intentos. Inténtalo nuevamente en '.RateLimiter::availableIn($throttleKey).' segundos.')->status(429);
        }

        if ($recoveryCode) {
            $valid = collect($user->recoveryCodes())->first(fn ($c) => hash_equals($c, $recoveryCode));
            if ($valid) {
                $user->replaceRecoveryCode($valid);
            }
        } else {
            $valid = $code && app(TwoFactorAuthenticationProvider::class)->verify(decrypt($user->two_factor_secret), $code);
        }

        if (! $valid) {
            RateLimiter::hit($throttleKey);
            AuditLogger::log('login_failed', "Código 2FA incorrecto para {$user->email}", $user, ['email' => $user->email, 'reason' => '2fa'], $user->id);

            throw $invalid();
        }

        RateLimiter::clear($throttleKey);

        return $user;
    }

    private function throttleKey(string $email, string $ip): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ip);
    }
}
