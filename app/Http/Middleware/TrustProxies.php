<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * Detrás de un balanceador o Cloudflare: TRUSTED_PROXIES="*" o la lista de IP, para que
     * la app reconozca HTTPS (X-Forwarded-Proto) y la IP real en la auditoría.
     */
    public function __construct()
    {
        $proxies = config('security.trusted_proxies');
        $this->proxies = $proxies === '*' ? '*' : ($proxies ? array_map('trim', explode(',', $proxies)) : null);
    }

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
