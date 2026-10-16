<?php

/*
|--------------------------------------------------------------------------
| Facturación de dietas
|--------------------------------------------------------------------------
|
| Estructura tomada del formato "Facturación 1 al 31 de Enero 2026 UCI.xlsx".
| Los precios NO viven aquí: se administran en /precios (tabla service_prices).
|
*/

return [

    // Unidad que aparece en el título y nombre del archivo ("DIETAS ENERO UCI")
    'unit' => env('BILLING_UNIT', 'UCI'),

    /*
    | Servicios facturables, en el orden del formato. Clave = tiempo de comida
    | de config/timeFood.php. "Comida" del formato = cena. Fraccionada no se factura.
    */
    'services' => [
        'desayuno' => 'DESAYUNO',
        'mediamanana' => 'M.MAÑANA',
        'almuerzo' => 'ALMUERZO',
        'algo' => 'ALGO',
        'cena' => 'COMIDA',
        'merienda' => 'MERIENDA',
    ],

    // Refrigerios de tipos sin esa fila se facturan en este tipo
    'snack_type' => 'entrecomidas',

    /*
    | Tipos de dieta, en el orden del formato, con los servicios que factura cada uno.
    | 'label' = texto del Excel; 'name' = texto en la interfaz.
    | 'selectable' => false: no se elige en el formulario (solo agrupa refrigerios).
    */
    'types' => [
        'normal' => ['label' => 'NORMAL', 'name' => 'Normal', 'services' => ['desayuno', 'almuerzo', 'cena']],
        'blanda_mecanica' => ['label' => 'BLANDA MECÁNICA', 'name' => 'Blanda mecánica', 'services' => ['desayuno', 'almuerzo', 'cena']],
        'blanda_quimica' => ['label' => 'BLANDA QUÍMICA', 'name' => 'Blanda química', 'services' => ['desayuno', 'mediamanana', 'almuerzo', 'algo', 'cena', 'merienda']],
        'hipoglucida' => ['label' => 'HIPOGLÚCIDA', 'name' => 'Hipoglúcida', 'services' => ['desayuno', 'mediamanana', 'almuerzo', 'algo', 'cena', 'merienda']],
        'hiposodica' => ['label' => 'HIPOSÓDICA', 'name' => 'Hiposódica', 'services' => ['desayuno', 'almuerzo', 'cena']],
        'hipograsa' => ['label' => 'HIPOGRASA', 'name' => 'Hipograsa', 'services' => ['desayuno', 'almuerzo', 'cena']],
        'controlada_carbohidratos' => ['label' => 'CONTROLADA EN CARBOHIDRATOS', 'name' => 'Controlada en carbohidratos', 'services' => ['desayuno', 'mediamanana', 'almuerzo', 'algo', 'cena', 'merienda']],
        'hipoproteica_control_lr' => ['label' => 'HIPOPROTEICA-CONTROL LR', 'name' => 'Hipoproteica - control LR', 'services' => ['desayuno', 'almuerzo', 'cena']],
        'hiperproteica' => ['label' => 'HIPERPROTEICA', 'name' => 'Hiperproteica', 'services' => ['desayuno', 'mediamanana', 'almuerzo', 'algo', 'cena', 'merienda']],
        'liquida_clara' => ['label' => 'LÍQUIDA CLARA', 'name' => 'Líquida clara', 'services' => ['desayuno', 'mediamanana', 'almuerzo', 'algo', 'cena', 'merienda']],
        'liquida_completa' => ['label' => 'LÍQUIDA COMPLETA', 'name' => 'Líquida completa', 'services' => ['desayuno', 'mediamanana', 'almuerzo', 'algo', 'cena', 'merienda']],
        'supraglotica' => ['label' => 'SUPRAGLÓTICA', 'name' => 'Supraglótica', 'services' => ['desayuno', 'mediamanana', 'almuerzo', 'algo', 'cena', 'merienda']],
        'neutropenica' => ['label' => 'NEUTROPÉNICA', 'name' => 'Neutropénica', 'services' => ['desayuno', 'almuerzo', 'cena']],
        'pediatrica_1' => ['label' => 'PEDIÁTRICA 1', 'name' => 'Pediátrica 1', 'services' => ['desayuno', 'mediamanana', 'almuerzo', 'algo', 'cena', 'merienda']],
        'pediatrica_2' => ['label' => 'PEDIÁTRICA 2', 'name' => 'Pediátrica 2', 'services' => ['desayuno', 'mediamanana', 'almuerzo', 'algo', 'cena', 'merienda']],
        'pediatrica_3' => ['label' => 'PEDIÁTRICA 3', 'name' => 'Pediátrica 3', 'services' => ['desayuno', 'mediamanana', 'almuerzo', 'algo', 'cena', 'merienda']],
        'entrecomidas' => ['label' => 'ENTRECOMIDAS', 'name' => 'Entrecomidas', 'services' => ['mediamanana', 'algo', 'merienda'], 'selectable' => false],
    ],

];
