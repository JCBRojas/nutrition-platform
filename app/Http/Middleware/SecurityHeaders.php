<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTPS obligatorio (config security.force_https) y cabeceras de seguridad básicas.
 * Sin Content-Security-Policy: las vistas usan estilos y scripts en línea.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $forceHttps = config('security.force_https');

        if ($forceHttps && ! $request->secure() && $request->isMethodSafe()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($forceHttps && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Páginas con datos de pacientes: que el navegador ni los proxies las guarden
        if ($request->user() && ! $response->headers->has('Content-Disposition')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
