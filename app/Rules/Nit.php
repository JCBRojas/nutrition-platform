<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * NIT colombiano con dígito de verificación (900123456-8), calculado con el
 * algoritmo de la DIAN (módulo 11 con pesos primos de derecha a izquierda).
 */
class Nit implements ValidationRule
{
    private const WEIGHTS = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];

    public static function checkDigit(string $number): int
    {
        $sum = 0;
        foreach (array_reverse(str_split($number)) as $i => $digit) {
            $sum += (int) $digit * self::WEIGHTS[$i];
        }
        $remainder = $sum % 11;

        return $remainder > 1 ? 11 - $remainder : $remainder;
    }

    /** Quita puntos y espacios: «900.123.456 - 8» → «900123456-8» */
    public static function normalize(?string $value): string
    {
        return preg_replace('/[\s.]/', '', (string) $value);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^(\d{6,15})-(\d)$/', self::normalize($value), $m)) {
            $fail('El NIT debe tener el formato 900123456-8 (número, guion y dígito de verificación).');

            return;
        }

        if (self::checkDigit($m[1]) !== (int) $m[2]) {
            $fail('El dígito de verificación del NIT no es válido.');
        }
    }
}
