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

];
