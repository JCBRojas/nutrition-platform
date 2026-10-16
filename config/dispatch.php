<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Despacho y recepción de dietas
    |--------------------------------------------------------------------------
    |
    | Desde esta fecha (YYYY-MM-DD) los Excel (reporte nutricional y facturación)
    | solo cuentan los tiempos de comida que cocina despachó y el nutricionista
    | confirmó como recibidos (tabla diet_dispatches). Los días anteriores se
    | cuentan con la regla previa, porque no tienen despachos registrados.
    | Vacío = no se exige.
    |
    */
    'required_from' => env('DISPATCH_REQUIRED_FROM', '2026-10-15'),

    /*
    |--------------------------------------------------------------------------
    | Facturación solo de días completados
    |--------------------------------------------------------------------------
    |
    | Desde esta fecha la facturación y el Excel del reporte solo cuentan los días
    | en que la dieta quedó Completada (todos sus tiempos despachados y recibidos;
    | tabla diet_days). Antes de la fecha se usa la regla de lo recibido.
    | Vacío = no se exige.
    |
    */
    'completion_required_from' => env('COMPLETION_REQUIRED_FROM', '2026-10-16'),

];
