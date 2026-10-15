<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mapa de colores por especificación (normalizada)
    |--------------------------------------------------------------------------
    |
    | Estas claves están normalizadas en snake_case para usarse en la base de
    | datos o en lógica interna. Los valores son clases Tailwind para aplicar
    | colores en la vista.
    |
    */

    'colors' => [
        'astringente'    => 'bg-gray-500',
        'hipercalorica'  => 'bg-green-500',
        'hipograsa'      => 'bg-violet-500',
        'hipoglucida'    => 'bg-purple-500',
        'hiposodica'     => 'bg-yellow-500',
        'sin_fibra'      => 'bg-indigo-500',
        'sin_gluten'     => 'bg-orange-500',
        'sin_irritantes' => 'bg-blue-500',
        'sin_lacteos'    => 'bg-red-500',
    ],

    /*
    |--------------------------------------------------------------------------
    | Etiquetas visibles para el usuario
    |--------------------------------------------------------------------------
    |
    | Estas etiquetas se muestran en la interfaz. Mantienen tildes y formato
    | correcto, mientras que las claves siguen normalizadas.
    |
    */

    'labels' => [
        'astringente'    => 'Astringente',
        'hipercalorica'  => 'Hipercalórica',
        'hipograsa'      => 'Hipograsa',
        'hipoglucida'    => 'Hipoglucida',
        'hiposodica'     => 'Hiposódica',
        'sin_fibra'      => 'Sin fibra',
        'sin_gluten'     => 'Sin gluten',
        'sin_irritantes' => 'Sin irritantes',
        'sin_lacteos'    => 'Sin lácteos',
    ],

];
