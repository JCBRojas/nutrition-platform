<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;

/**
 * Token CSRF y verificación del origen (Sec-Fetch-Site) de Laravel 13. VerifyCsrfToken del
 * framework quedó como alias obsoleto de PreventRequestForgery.
 */
class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
}
