<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un despachador sin una empresa específica activa no puede ver ninguna pantalla de la
 * aplicación (NUT-RN-003-24): recibe el aviso «Sin empresa asignada» (403). El perfil y el
 * cierre de sesión quedan libres (fuera del grupo de rutas de la app).
 */
class EnsureCompanyAssigned
{
    public const MESSAGE = 'Sin empresa asignada: pide al jefe de despacho que te asocie una empresa.';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->lacksRequiredCompany()) {
            if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
                abort(403, self::MESSAGE);
            }

            return response()->view('company-required', ['message' => self::MESSAGE], 403);
        }

        return $next($request);
    }
}
