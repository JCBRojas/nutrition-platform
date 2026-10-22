<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Teléfono colombiano sin indicativo: 10 dígitos (celular 3001234567 o fijo 6011234567).
 * El indicativo del país (+57) se agrega al usarlo (p. ej. WhatsApp), no se digita.
 */
class LocalPhone implements ValidationRule
{
    /** Quita espacios, guiones, puntos y paréntesis: «300 123-4567» → «3001234567» */
    public static function normalize(?string $value): string
    {
        return preg_replace('/[\s.\-()]/', '', (string) $value);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $phone = self::normalize($value);

        if (str_starts_with($phone, '+')) {
            $fail('Escribe el número sin indicativo (sin +57), p. ej. 3001234567.');
        } elseif (! preg_match('/^\d{10}$/', $phone)) {
            $fail('El teléfono debe tener 10 dígitos, sin indicativo, p. ej. 3001234567.');
        }
    }
}
