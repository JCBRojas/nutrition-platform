<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tipos de cambio de una versión de dieta (diet_histories.changes)
    |--------------------------------------------------------------------------
    |
    | Mismas claves que el select "Cambios en la dieta" del formulario y las
    | versiones de cierre de DietService (cancelada, finalizada) y las del día
    | (completada, continuada; Diet::DAY_CHANGES).
    |
    */

    'labels' => [
        'NA' => 'Sin tipo',
        'con_cambios' => 'Con cambios',
        'aislado' => 'Se aisló',
        'ya_no_aislado' => 'Ya no está aislado',
        'no_aislado' => 'Ya no está aislado',
        'cancelada' => 'Cancelada',
        'finalizada' => 'Finalizada (alta)',
        'completada' => 'Día completado',
        'continuada' => 'Continuada al día siguiente',
    ],

    'colors' => [
        'con_cambios' => 'bg-yellow-100 text-yellow-800',
        'aislado' => 'bg-purple-100 text-purple-800',
        'ya_no_aislado' => 'bg-green-100 text-green-800',
        'no_aislado' => 'bg-green-100 text-green-800',
        'cancelada' => 'bg-red-100 text-red-800',
        'finalizada' => 'bg-blue-100 text-blue-800',
        'completada' => 'bg-teal-100 text-teal-800',
        'continuada' => 'bg-sky-100 text-sky-800',
    ],

];
