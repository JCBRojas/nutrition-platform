<?php

return [

    // Verificar el dígito de verificación del NIT con el algoritmo de la DIAN (App\Rules\Nit).
    // Apagado por ahora para poder registrar NIT de prueba; el formato 900123456-8 sí se exige.
    'nit_check_digit' => env('NIT_VERIFY_CHECK_DIGIT', false),

];
