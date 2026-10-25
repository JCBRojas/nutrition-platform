<?php

namespace App\Http\Middleware;

use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API del cliente de escritorio: la empresa activa llega en el encabezado X-Company-Id (no hay
 * sesión). Se valida contra las empresas elegibles del usuario y queda en CurrentCompany, así
 * CompanyScope filtra igual que en la web. Sin encabezado se usa la última empresa del usuario.
 */
class UseCompanyHeader
{
    public const HEADER = 'X-Company-Id';

    public function __construct(private CurrentCompany $company) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $header = $request->header(self::HEADER);

        if ($user && $header !== null && $header !== '') {
            if (! ctype_digit((string) $header) || ! $this->company->useForRequest($user, (int) $header)) {
                return response()->json(['message' => 'No tienes acceso a esa empresa.', 'code' => 'company_forbidden'], 403);
            }
        }

        return $next($request);
    }
}
