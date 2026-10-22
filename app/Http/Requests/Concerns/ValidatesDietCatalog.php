<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Tiempos de comida, consistencias y especificaciones de una dieta: solo claves de los
 * catálogos de config/ (SCRUM-17). Los tiempos son obligatorios: sin ninguno la dieta
 * nunca se despacha.
 */
trait ValidatesDietCatalog
{
    /** Claves antiguas o variantes ('media_manana', 'Desayuno') → clave del catálogo */
    protected function normalizeTimeFood(): void
    {
        if (! is_array($this->input('timeFood'))) {
            return;
        }

        $keys = array_keys(config('timeFood.labels'));
        $this->merge(['timeFood' => collect($this->input('timeFood'))
            ->map(function ($value) use ($keys) {
                $plain = str_replace('_', '', mb_strtolower(trim((string) $value)));

                return collect($keys)->first(fn ($key) => str_replace('_', '', $key) === $plain) ?? $value;
            })
            ->unique()->values()->all()]);
    }

    protected function catalogRules(): array
    {
        return [
            'timeFood' => ['required', 'array', 'min:1'],
            'timeFood.*' => ['string', 'distinct', Rule::in(array_keys(config('timeFood.labels')))],
            'consistency' => ['nullable', 'array'],
            'consistency.*' => ['string', 'distinct', Rule::in(array_keys(config('consistency.labels')))],
            'specifications' => ['nullable', 'array'],
            'specifications.*' => ['string', 'distinct', Rule::in(array_keys(config('specifications.labels')))],
        ];
    }

    protected function catalogMessages(): array
    {
        return [
            'timeFood.required' => 'Elige al menos un tiempo de comida.',
            'timeFood.min' => 'Elige al menos un tiempo de comida.',
            'timeFood.*.in' => 'Elige los tiempos de comida de la lista.',
            'consistency.*.in' => 'Elige las consistencias de la lista.',
            'specifications.*.in' => 'Elige las especificaciones de la lista.',
        ];
    }
}
