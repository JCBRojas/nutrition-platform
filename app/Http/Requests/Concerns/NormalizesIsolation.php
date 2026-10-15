<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * diet_histories.isolation es enum('Sí','No'). Acepta variantes de los
 * formularios ('Si', 'si', 'no') y las convierte al valor del enum.
 */
trait NormalizesIsolation
{
    public const ISOLATION_VALUES = ['Sí', 'No'];

    protected function prepareForValidation(): void
    {
        if (! $this->filled('isolation')) {
            return;
        }

        $value = mb_strtolower(trim((string) $this->input('isolation')));

        $this->merge(['isolation' => match ($value) {
            'sí', 'si' => 'Sí',
            'no' => 'No',
            default => $this->input('isolation'),
        }]);
    }

    protected function isolationRules(): array
    {
        return ['nullable', Rule::in(self::ISOLATION_VALUES)];
    }
}
