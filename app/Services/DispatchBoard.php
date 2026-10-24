<?php

namespace App\Services;

use App\Models\Diet;
use App\Models\DietHistory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Tablero del despachador (cocina): la consulta de dietas por pestaña y servicio, con el estado
 * de despacho de cada una, y la lista de cambios sin confirmar. Lo comparten la pantalla web
 * (App\Livewire\DietsManager) y la API del cliente de escritorio (Api\V1\DispatchController).
 */
class DispatchBoard
{
    /** Campos que se comparan para mostrar qué cambió */
    public const COMPARED_FIELDS = ['timeFood', 'consistency', 'specifications', 'observations', 'isolation'];

    public function __construct(
        private DispatchService $dispatches,
        private DietDayService $days,
        private DietHistoryService $history,
    ) {}

    /**
     * Dietas de la pestaña ('active' | 'closed'), las de cambios sin confirmar primero. En
     * 'active', cada dieta trae su estado en el servicio $meal: dispatch_record, dispatch_missing
     * (nutrición reportó que no llegó), dispatch_block (por qué no se puede despachar) y
     * authorized_for_day. Todas traen pending_changes y last_change.
     */
    public function diets(string $tab, string $meal, bool $showAll, string $search, int $perPage): LengthAwarePaginator
    {
        $active = $tab === 'active';
        $statuses = $active ? Diet::OPEN_STATUSES : [Diet::CANCELLED, Diet::FINISHED];

        $diets = Diet::query()
            // Activas: también las dadas de alta hoy con comida por enviar a otro lugar
            ->where(fn (Builder $q) => $q->whereIn('status', $statuses)
                ->when($active, fn (Builder $q) => $q->orWhere(fn (Builder $q) => $q->transferOn())))
            ->when($active && ! $showAll, fn (Builder $q) => $this->appliesToMeal($q, $meal))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('habitation', 'like', "%{$search}%")
                ->orWhere('name_patient', 'like', "%{$search}%")))
            ->withExists(['history as has_pending' => fn (Builder $q) => $q->where('modify', 'si')])
            ->with(['currentVersion.updatedBy', 'history'])
            ->orderByDesc('has_pending') // los cambios sin confirmar siempre arriba; luego por habitación
            ->orderByRoom()
            ->latest('updated_at')
            ->paginate($perPage);

        $page = $diets->getCollection();
        $page->each(function (Diet $diet) {
            $diet->pending_changes = $diet->has_pending ? $this->changesSinceConfirmed($diet) : [];
            $diet->last_change = $this->history->lastChangeLabel($diet); // columna «Último cambio» (SCRUM-28)
        });

        if (! $active) {
            return $diets;
        }

        // Estado de despacho de cada dieta de la página en el tiempo elegido
        $ids = $page->pluck('id')->all();
        $records = $this->dispatches->dispatchesFor($ids, $meal);
        $voids = $this->dispatches->voidsFor($ids, $meal);
        $authorized = $this->days->authorizedIds($ids);

        $page->each(function (Diet $diet) use ($records, $voids, $authorized, $meal) {
            $diet->authorized_for_day = in_array($diet->id, $authorized, true);
            $diet->dispatch_record = $records->get($diet->id);
            // Nutrición reportó que no llegó: aviso para volver a despacharlo
            $void = $diet->dispatch_record ? null : $voids->get($diet->id);
            $diet->dispatch_missing = $void?->isNotArrived() ? $void : null;
            $diet->dispatch_block = $this->dispatches->blockReason($diet, $meal);
        });

        return $diets;
    }

    /** IDs de la página que se pueden despachar en el servicio (sin despacho y sin bloqueo) */
    public function dispatchable(Collection $page): array
    {
        return $page->filter(fn (Diet $d) => ! $d->dispatch_record && ! $d->dispatch_block)->pluck('id')->all();
    }

    /** Dietas con versiones sin confirmar, la más reciente primero (con last_change) */
    public function pending(): Collection
    {
        return Diet::query()
            ->whereHas('history', fn (Builder $q) => $q->where('modify', 'si'))
            ->with(['currentVersion.updatedBy', 'history'])
            ->get()
            ->each(fn (Diet $d) => $d->last_change = $this->history->lastChangeLabel($d))
            ->sortByDesc(fn (Diet $d) => $d->currentVersion?->created_at)
            ->values();
    }

    /** Clave «dieta:versión» de un cambio pendiente: una alerta por cada versión nueva */
    public static function pendingKey(Diet $diet): string
    {
        return $diet->id.':'.($diet->currentVersion?->version ?? 0);
    }

    /** Contadores de las pestañas */
    public function counts(Collection $pending, string $meal, bool $showAll): array
    {
        $open = fn (Diet $d) => $d->isActive() || $d->transferMealsOn();

        return [
            'active' => Diet::active()->count(),
            'closed' => Diet::whereIn('status', [Diet::CANCELLED, Diet::FINISHED])->count(),
            'pendingActive' => $pending->filter($open)->count(),
            'pendingClosed' => $pending->reject($open)->count(),
            'transfers' => Diet::transferOn()->count(),
            'applies' => $showAll ? null : $this->appliesToMeal(Diet::active(), $meal)->count(),
        ];
    }

    /**
     * Dietas cuya versión actual incluye el servicio elegido, más las que tienen cambios
     * sin confirmar (el despachador debe verlas aunque el cambio haya quitado ese tiempo).
     */
    public function appliesToMeal(Builder $query, string $meal): Builder
    {
        // Claves guardadas de un mismo tiempo: 'mediamanana' / 'media_manana' (JSON: ["almuerzo","cena"])
        $keys = $meal === 'mediamanana' ? ['mediamanana', 'media_manana'] : [$meal];

        return $query->where(fn (Builder $q) => $q
            ->whereHas('currentVersion', fn (Builder $v) => $v->where(function (Builder $v) use ($keys) {
                foreach ($keys as $key) {
                    $v->orWhere('timeFood', 'like', '%"'.$key.'"%');
                }
            }))
            ->orWhereHas('history', fn (Builder $h) => $h->where('modify', 'si')));
    }

    /**
     * Diferencias entre la versión actual y la última que el despachador confirmó
     * (si el nutricionista editó varias veces, se ven todos los cambios acumulados).
     */
    public function changesSinceConfirmed(Diet $diet): array
    {
        $versions = $diet->history->sortBy('version')->values();
        $current = $versions->last();
        $baseline = $versions->last(fn (DietHistory $h) => $h->version < $current->version && $h->modify === 'no')
            ?? $versions->get($versions->count() - 2);

        if (! $current || ! $baseline) {
            return [];
        }

        $changes = [];
        foreach (self::COMPARED_FIELDS as $field) {
            $old = $baseline->$field;
            $new = $current->$field;

            if (is_array($old) || is_array($new)) {
                $old = array_values((array) $old);
                $new = array_values((array) $new);
                $added = array_values(array_diff($new, $old));
                $removed = array_values(array_diff($old, $new));
                if ($added || $removed) {
                    $changes[$field] = ['added' => $added, 'removed' => $removed];
                }
            } elseif ((string) $old !== (string) $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }
}
