<?php

/*
|--------------------------------------------------------------------------
| Suscripción del operador del despacho
|--------------------------------------------------------------------------
|
| Plan básico (gratis) y plan premium (mensual o anual). El premium habilita las
| funciones de `premium_features`. El rol admin tiene acceso a todo sin importar el plan.
| El Premium lo paga el operador de la cocina (jefe de despacho), no cada empresa cliente
| (NUT-RN-003-17). El pago es manual: se paga por uno de los métodos y se adjunta el
| comprobante; un administrador lo revisa y, al aprobarlo, se activa o extiende el premium.
|
*/

return [

    'plans' => [
        'basic' => ['name' => 'Básico', 'description' => 'Dietas, despacho, recepción y reporte nutricional.'],
        'premium' => ['name' => 'Premium', 'description' => 'Todo lo del básico, más varias empresas atendidas, precios y facturación por empresa, despachos del día e historial de cambios.'],
    ],

    // Funciones exclusivas del premium: clave => etiqueta (middleware premium:{clave}, directiva @premium)
    'premium_features' => [
        'prices' => 'Precios y facturación',
        'dispatch_log' => 'Despachos del día',
        'history' => 'Historial de cambios',
        // Crear, ver y cambiar entre empresas (NUT-RN-003-16). Sin premium: solo la predeterminada (003-18, -19)
        'companies' => 'Varias empresas atendidas',
    ],

    'periods' => [
        'monthly' => ['name' => 'Mensual', 'months' => 1],
        'annual' => ['name' => 'Anual', 'months' => 12],
    ],

    // Precios iniciales en COP (el admin los cambia en /suscripcion; quedan en plan_prices)
    'default_prices' => [
        'monthly' => (int) env('SUBSCRIPTION_MONTHLY_PRICE', 250000),
        'annual' => (int) env('SUBSCRIPTION_ANNUAL_PRICE', 2500000),
    ],

    // Métodos de pago manuales y los datos que se muestran para pagar
    'payment_methods' => [
        'transferencia' => [
            'name' => 'Transferencia bancaria',
            'instructions' => env('PAYMENT_BANK_DETAILS', 'Banco [nombre] · Cuenta de ahorros [número] · Titular [razón social] · NIT [NIT]'),
        ],
        'consignacion' => [
            'name' => 'Consignación en efectivo',
            'instructions' => env('PAYMENT_DEPOSIT_DETAILS', 'Consigna en cualquier oficina o corresponsal del banco a la cuenta indicada en «Transferencia bancaria».'),
        ],
        'nequi' => [
            'name' => 'Nequi',
            'instructions' => env('PAYMENT_NEQUI_DETAILS', 'Envía el pago al número Nequi [número] a nombre de [titular].'),
        ],
        'daviplata' => [
            'name' => 'Daviplata',
            'instructions' => env('PAYMENT_DAVIPLATA_DETAILS', 'Envía el pago al número Daviplata [número] a nombre de [titular].'),
        ],
    ],

    // Comprobante: imagen o PDF, en KB
    'receipt_max_kb' => 5120,

    // Días antes del vencimiento en que se avisa para renovar
    'renewal_notice_days' => 7,

];
