<?php

/*
| Cliente de escritorio (Flutter, Windows y macOS) y su API v1 (NUT-RN-004).
*/
return [
    // Versión mínima de la app; si la instalada es menor, pide actualizar (GET /api/v1/meta)
    'min_version' => env('MIN_DESKTOP_VERSION', '1.0.0'),

    // Minutos de validez del token de la app (Sanctum). Al vencer, se vuelve a iniciar sesión.
    'token_minutes' => (int) env('DESKTOP_TOKEN_MINUTES', 720),

    // WebSocket que ve la app (puede diferir del host interno que usa Laravel para publicar)
    'realtime' => [
        'host' => env('DESKTOP_WS_HOST', env('PUSHER_HOST')),
        'port' => (int) env('DESKTOP_WS_PORT', env('PUSHER_PORT', 443)),
        'scheme' => env('DESKTOP_WS_SCHEME', env('PUSHER_SCHEME', 'https')),
    ],
];
