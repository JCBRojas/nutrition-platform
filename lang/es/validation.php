<?php

// Solo los mensajes usados en los formularios de acceso; el resto cae al idioma de respaldo.
return [
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'email' => 'Escribe un :attribute válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'max' => [
        'string' => 'El campo :attribute no debe superar :max caracteres.',
    ],
    'current_password' => 'La contraseña es incorrecta.',
    'mimes' => 'El archivo debe ser de tipo: :values.',
    'password' => [
        'letters' => 'La :attribute debe contener al menos una letra.',
        'mixed' => 'La :attribute debe contener al menos una mayúscula y una minúscula.',
        'numbers' => 'La :attribute debe contener al menos un número.',
        'symbols' => 'La :attribute debe contener al menos un símbolo.',
        'uncompromised' => 'La :attribute aparece en una filtración de datos. Elige otra.',
    ],

    'attributes' => [
        'email' => 'correo electrónico',
        'password' => 'contraseña',
        'token' => 'enlace de recuperación',
        'name' => 'nombre',
        'current_password' => 'contraseña actual',
        'code' => 'código',
        'recovery_code' => 'código de recuperación',
    ],
];
