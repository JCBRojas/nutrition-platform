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
        'blanda_mecanica'    => 'bg-gray-500',
        'liquida_clara'  => 'bg-green-500',
        'liquida_completa'      => 'bg-violet-500',
        'normal'    => 'bg-purple-500',
        'supraglotica'     => 'bg-yellow-500',
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
        'blanda_mecanica'    => 'Blanda mecánica',
        'liquida_clara'  => 'Líquida clara',
        'liquida_completa'      => 'Líquida completa',
        'normal'    => 'Normal',
        'supraglotica'     => 'Supraglótica',
    ],

];
