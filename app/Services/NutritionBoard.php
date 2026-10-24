<?php

namespace App\Services;

use App\Models\Diet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Panel de dietas del nutricionista (y de quien tenga viewActiveDiets): pestañas, búsqueda e
 * indicadores. Lo comparten la pantalla web (App\Livewire\NutritionDiets) y la API del
 * cliente de escritorio (Api\V1\DietController).
 */
class NutritionBoard
{
    /** Pestañas => estados de la dieta (activas incluye las completadas hoy) */
    public const TABS = [
        'active' => Diet::OPEN_STATUSES,
        'cancelled' => [Diet::CANCELLED],
        'finished' => [Diet::FINISHED],
    ];

    /** Dietas de la pestaña; la búsqueda aplica a la habitación o al paciente */
    public function diets(string $tab, string $search, int $perPage): LengthAwarePaginator
    {
        $search = trim($search);

        return Diet::with('currentVersion.updatedBy')
            ->whereIn('status', self::TABS[$tab] ?? self::TABS['active'])
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('habitation', 'like', "%{$search}%")
                ->orWhere('name_patient', 'like', "%{$search}%")))
            ->orderByRoom()
            ->latest('updated_at') // misma habitación (dietas cerradas): la más reciente primero
            ->paginate($perPage);
    }

    /** Total por pestaña, sobre todas las dietas (no solo la página) */
    public function counts(): array
    {
        $counts = Diet::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(self::TABS)->map(fn ($statuses) => (int) collect($statuses)->sum(fn ($s) => $counts[$s] ?? 0))->all();
    }

    /** Indicadores de las dietas activas: en aislamiento y con cambios */
    public function stats(): array
    {
        $versions = Diet::active()->with('currentVersion')->get()->pluck('currentVersion')->filter();

        return [
            'isolated' => $versions->where('isolation', 'Sí')->count(),
            'changes' => $versions->reject(fn ($v) => in_array($v->changes, [null, '', 'NA', ...Diet::DAY_CHANGES], true))->count(),
        ];
    }
}
