<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Antes de usar la plataforma el usuario debe:
 * 1. Aceptar la versión vigente de la política de tratamiento de datos (Ley 1581 de 2012).
 * 2. Tener activa la verificación en dos pasos si su rol la exige (admin y jefe de nutrición).
 * Se aplica a las rutas de la aplicación; el perfil (donde se activa 2FA) y la aceptación quedan libres.
 */
class EnsureAccountCompliance
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (config('security.privacy.required') && ! $user->hasAcceptedPrivacyPolicy()) {
            return $this->deny($request, route('privacy.accept'), 'Debes aceptar la política de tratamiento de datos para continuar.', 'privacy_required');
        }

        if ($user->requiresTwoFactor() && ! $user->hasTwoFactorEnabled()) {
            return $this->deny($request, route('profile.show').'#two-factor', 'Tu rol requiere la verificación en dos pasos. Actívala en «Autenticación de dos factores» para continuar.', 'two_factor_setup_required');
        }

        return $next($request);
    }

    private function deny(Request $request, string $url, string $message, string $code): Response
    {
        // API del cliente de escritorio: se resuelve en la web (enlace en 'url')
        if ($request->is('api/*')) {
            return response()->json(['message' => $message, 'code' => $code, 'url' => $url], 403);
        }

        if ($request->expectsJson()) {
            abort(403, $message);
        }

        return redirect()->to($url)->with('compliance', $message);
    }
}
