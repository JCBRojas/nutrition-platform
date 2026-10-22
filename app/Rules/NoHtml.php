<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Texto libre sin código HTML: rechaza etiquetas (<b>, </p>, <script…), comentarios (<!--)
 * e instrucciones (<?php). Un «<» suelto («peso < 50 kg») sí se permite.
 */
class NoHtml implements ValidationRule
{
    public static function containsHtml(?string $value): bool
    {
        return (bool) preg_match('/<\s*[\/!?a-z]/i', (string) $value);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::containsHtml($value)) {
            $fail('El campo :attribute no puede contener código HTML (etiquetas como <b> o <script>).');
        }
    }
}
