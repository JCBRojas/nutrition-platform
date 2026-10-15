<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class CustomThrottleLogin
{
    public function handle(Request $request, Closure $next, $maxAttempts = 3, $decayMinutes = 1)
    {
        $throttleKey = Str::transliterate(
            Str::lower($request->input(Fortify::username())) . '|' . $request->ip()
        );

        $maxAttemptsValue = is_numeric($maxAttempts) ? (int) $maxAttempts : 3;

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttemptsValue)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return redirect()
                ->route('login')
                ->withErrors([
                    Fortify::username() => "Demasiados intentos de inicio de sesión. Inténtalo nuevamente en {$seconds} segundos.",
                ])
                ->withInput($request->only(Fortify::username(), 'remember'));
        }

        $response = $next($request);

        if (Auth::check()) {
            RateLimiter::clear($throttleKey);

            return $response;
        }

        RateLimiter::hit($throttleKey, (int) $decayMinutes * 60);

        return $response;
    }
}
