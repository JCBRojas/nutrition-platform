<?php

namespace App\Services;

use App\Models\Diet;
use App\Services\Reports\DietReportService;
use Illuminate\Support\Collection;

/**
 * Línea de tiempo de versiones de una dieta con la diferencia de cada versión
 * respecto a la anterior.
 */
class DietHistoryService
{
    /** Campos de lista: se comparan como conjuntos (agregados / quitados) */
    public const LIST_FIELDS = ['timeFood', 'consistency', 'specifications'];

    /** Campos simples: se comparan valor anterior / valor nuevo */
    public const SCALAR_FIELDS = ['billing_type', 'observations', 'isolation'];

    private const CATALOGS = [
        'timeFood' => 'timeFood',
        'consistency' => 'consistency',
        'specifications' => 'specifications',
    ];

    /**
     * Versiones de la más reciente a la más antigua.
     *
     * Cada elemento: version (DietHistory), type (creacion|edicion|cancelacion),
     * fields[campo] = ['items' => [[label, state]], 'changed' => bool] para listas
     * o ['value', 'old', 'changed'] para campos simples.
     */
    public function timeline(Diet $diet): Collection
    {
        $versions = $diet->history()->with('updatedBy')->orderBy('version')->get();
        $previous = null;
        $rows = [];

        foreach ($versions as $version) {
            $fields = [];

            foreach (self::LIST_FIELDS as $field) {
                $fields[$field] = $this->diffList($field, $previous?->{$field}, $version->{$field}, $previous === null);
            }

            foreach (self::SCALAR_FIELDS as $field) {
                $old = $previous?->{$field};
                $new = $version->{$field};
                if ($field === 'billing_type') {
                    $names = BillingService::selectableTypes();
                    $old = $old ? ($names[$old] ?? $old) : null;
                    $new = $new ? ($names[$new] ?? $new) : null;
                }
                $fields[$field] = [
                    'value' => $new,
                    'old' => $old,
                    'changed' => $previous !== null && (string) $old !== (string) $new,
                ];
            }

            $rows[] = [
                'version' => $version,
                'type' => match (true) {
                    $version->changes === 'cancelada' => 'cancelacion',
                    $version->changes === 'finalizada' => 'finalizacion',
                    $previous === null => 'creacion',
                    default => 'edicion',
                },
                'fields' => $fields,
            ];

            $previous = $version;
        }

        return collect($rows)->reverse()->values();
    }

    /**
     * @return array{items: array<int, array{label: string, state: string}>, changed: bool}
     *                                                                                      state: kept | added | removed
     */
    private function diffList(string $field, $old, $new, bool $isFirst): array
    {
        $old = $this->normalize($field, $old);
        $new = $this->normalize($field, $new);

        if ($isFirst) {
            return ['items' => $this->items($field, $new, 'kept'), 'changed' => false];
        }

        $items = array_merge(
            $this->items($field, array_intersect($new, $old), 'kept'),
            $this->items($field, array_diff($new, $old), 'added'),
            $this->items($field, array_diff($old, $new), 'removed'),
        );

        return ['items' => $items, 'changed' => count(array_diff($new, $old)) + count(array_diff($old, $new)) > 0];
    }

    private function items(string $field, array $keys, string $state): array
    {
        return array_map(fn ($key) => [
            'label' => DietReportService::label(self::CATALOGS[$field], $key),
            'state' => $state,
        ], array_values($keys));
    }

    /**
     * timeFood guardado como 'media_manana' o 'mediamanana' → misma clave
     */
    private function normalize(string $field, $values): array
    {
        $values = array_map('strval', (array) ($values ?? []));

        if ($field === 'timeFood') {
            $values = array_map(fn ($v) => str_replace('_', '', mb_strtolower($v)), $values);
        }

        return array_values(array_unique($values));
    }
}
