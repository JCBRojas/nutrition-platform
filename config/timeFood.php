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
        'algo'     => 'bg-yellow-500',
        'almuerzo'  => 'bg-green-500',
        'cena'      => 'bg-violet-500',
        'desayuno'    => 'bg-gray-500',
        'fraccionada'      => 'bg-blue-500',
        'mediamanana'    => 'bg-purple-500',
        'merienda'     => 'bg-orange-500',
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
        'algo'         => 'Algo',
        'almuerzo'     => 'Almuerzo',
        'cena'         => 'Cena',
        'desayuno'     => 'Desayuno',
        'fraccionada'  => 'Fraccionada',
        'mediamanana'  => 'Media mañana',
        'merienda'     => 'Merienda',
    ],

];
