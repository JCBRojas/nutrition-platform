<?php

namespace App\Http\Middleware;

use App\Support\Pagination;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja ?per_page= en un valor válido (15/30/50) antes de que Livewire lo asigne a
 * la propiedad `int $perPage`: con texto (p. ej. ?per_page=abc) fallaba con un error 500.
 */
class NormalizePerPage
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->query->has('per_page')) {
            $request->query->set('per_page', Pagination::perPage($request->query('per_page')));
        }

        return $next($request);
    }
}
