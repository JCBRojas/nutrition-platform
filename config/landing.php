<?php

/*
|--------------------------------------------------------------------------
| Páginas públicas (login, solicitud de demo, pie de página)
|--------------------------------------------------------------------------
*/

return [

    'contact' => [
        'email' => env('CONTACT_EMAIL', 'contacto@nutritrack.co'),
        'phone' => env('CONTACT_PHONE', '+57 300 000 0000'),
        // Número de WhatsApp en formato internacional sin signos (para wa.me)
        'whatsapp' => env('CONTACT_WHATSAPP', '573000000000'),
        'city' => env('CONTACT_CITY', 'Colombia'),
    ],

    // Correo que recibe las solicitudes de demo (además quedan en /admin/solicitudes-demo)
    'demo_notify_email' => env('DEMO_NOTIFY_EMAIL', env('CONTACT_EMAIL', 'contacto@nutritrack.co')),

];
