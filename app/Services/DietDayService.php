<?php

namespace App\Services;

use App\Models\Diet;
use App\Models\DietDay;
use App\Models\DietDispatch;
use App\Services\Reports\DietReportService;
use App\Support\LiveUpdates;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo diario de una dieta (tabla diet_days).
 *
 * 1. Autorización: crear la dieta autoriza el día de creación; cada día siguiente lo
 *    autoriza «Continuar dieta» (permiso continueDiets: nutrición y enfermería), en
 *    cualquier momento del día anterior. Sin autorización no se despacha (DispatchService).
 * 2. Cierre: cuando se reciben todos los tiempos exigibles del día, el día queda
 *    completado, la dieta pasa a Completada y se registra la versión 'completada'.
 *    Solo los días completados se facturan (DietReportService::activeDietsByDay).
 * 3. Inicio del día (diets:start-day, 00:01): las dietas Completadas autorizadas para hoy
 *    vuelven a Activa.
 *
 * Tiempos exigibles: los de la versión vigente ese día. El día de creación, solo los que
 * se sirven a la hora de creación o después (más los sin horario, como fraccionada). El día
 * del alta, los anteriores a la hora del alta (regla de facturación del alta). El día de una
 * cancelación no se completa.
 */
class DietDayService
{
    public function __construct(
        private DietService $diets,
        private DietReportService $reports,
    ) {}

    /* ===================== AUTORIZACIÓN ===================== */

    public function isAuthorized(Diet $diet, ?CarbonInterface $date = null): bool
    {
        return $diet->days()->on(self::day($date))->authorized()->exists();
    }

    /** IDs de las dietas autorizadas para despachar ese día */
    public function authorizedIds(array $dietIds, ?CarbonInterface $date = null): array
    {
        return DietDay::whereIn('diet_id', $dietIds)->on(self::day($date))->authorized()->pluck('diet_id')->all();
    }

    /**
     * «Continuar dieta»: autoriza el despacho de mañana y lo registra en el historial
     * (versión 'continuada'). No cambia el estado de hoy.
     */
    public function continueDiet(Diet $diet, int $userId): DietDay
    {
        if (! $diet->isActive()) {
            throw ValidationException::withMessages([
                'diet' => "La dieta de la habitación {$diet->habitation} está {$diet->statusLabel()} y no se puede continuar.",
            ]);
        }

        $tomorrow = self::day()->addDay();

        return DB::transaction(function () use ($diet, $userId, $tomorrow) {
            Diet::whereKey($diet->id)->lockForUpdate()->first();

            if ($this->isAuthorized($diet, $tomorrow)) {
                throw ValidationException::withMessages([
                    'diet' => "La dieta de la habitación {$diet->habitation} ya está autorizada para mañana.",
                ]);
            }

            $this->diets->recordDayVersion($diet, 'continuada', $userId);

            return DietDay::upsertFor($diet->id, $tomorrow, [
                'authorized_via' => DietDay::VIA_CONTINUATION,
                'authorized_by' => $userId,
                'authorized_at' => now(),
            ]);
        });
    }

    /* ===================== CIERRE DEL DÍA ===================== */

    /**
     * Tiempos de comida que deben recibirse ese día para completarlo (claves de DietReportService::MEALS)
     */
    public function requiredMeals(Diet $diet, CarbonInterface $date): array
    {
        $dayStart = self::day($date);
        $dayEnd = $dayStart->endOfDay();
        $history = $diet->history()->orderBy('version')->get();

        $created = CarbonImmutable::parse($history->first()?->created_at ?? $diet->created_at);
        $closing = $history->first(fn ($h) => in_array($h->changes, Diet::CLOSING_CHANGES, true));
        $closedAt = $closing ? CarbonImmutable::parse($closing->created_at) : null;
        $version = $history->last(fn ($h) => ! in_array($h->changes, Diet::CLOSING_CHANGES, true) && $h->created_at <= $dayEnd);

        if (! $version || $created > $dayEnd || ($closedAt && $closedAt < $dayStart)) {
            return [];
        }

        $meals = $this->reports->mealsOf($version);

        // Día de creación: lo que ya se había servido no se exige
        if ($created >= $dayStart) {
            $meals = array_diff($meals, $this->reports->mealsServedBefore($meals, $created->subSecond()));
        }

        // Día del cierre: alta → solo lo anterior a la hora del alta; cancelación → no se completa
        if ($closedAt && $closedAt <= $dayEnd) {
            $meals = $closing->changes === 'finalizada'
                ? array_intersect($meals, $this->reports->mealsServedBefore($meals, $closedAt))
                : [];
        }

        return array_values($meals);
    }

    /**
     * Revisa si el día de la dieta quedó completo (llamado al confirmar una recepción).
     * Devuelve true si se completó ahora.
     */
    public function evaluate(Diet $diet, CarbonInterface $date, int $userId): bool
    {
        $date = self::day($date);

        if ($diet->days()->on($date)->completed()->exists()) {
            return false;
        }

        $required = $this->requiredMeals($diet, $date);
        if ($required === []) {
            return false;
        }

        $received = DietDispatch::where('diet_id', $diet->id)
            ->whereDate('service_date', $date->toDateString())
            ->received()
            ->pluck('meal')
            ->all();

        if (array_diff($required, $received) !== []) {
            return false;
        }

        DB::transaction(function () use ($diet, $date, $userId) {
            $history = null;

            // Dada de alta ese día: el día cuenta, pero la dieta ya está cerrada (sin versión ni cambio de estado)
            if ($diet->isActive()) {
                $history = $this->diets->recordDayVersion($diet, 'completada', $userId);

                if ($date->isToday()) {
                    $diet->update(['status' => Diet::COMPLETED]);
                }
            }

            DietDay::upsertFor($diet->id, $date, ['completed_at' => now(), 'completed_history_id' => $history?->id]);
        });

        return true;
    }

    /**
     * Evalúa los días de los despachos recibidos. Devuelve las habitaciones que se completaron.
     *
     * @param  Collection<int, DietDispatch>  $dispatches
     */
    public function evaluateDispatches(Collection $dispatches, int $userId): array
    {
        $completed = [];

        $dispatches->unique(fn (DietDispatch $d) => $d->diet_id.'|'.$d->service_date->toDateString())
            ->each(function (DietDispatch $d) use ($userId, &$completed) {
                if ($d->diet && $this->evaluate($d->diet, $d->service_date, $userId)) {
                    $completed[] = $d->diet->habitation;
                }
            });

        return $completed;
    }

    /* ===================== INICIO DEL DÍA ===================== */

    /**
     * Las dietas Completadas que tienen autorización para el día (y aún no lo completan)
     * vuelven a Activa. Devuelve cuántas cambiaron.
     */
    public function startDay(?CarbonInterface $date = null): int
    {
        $date = self::day($date);

        $count = Diet::where('status', Diet::COMPLETED)
            ->whereHas('days', fn ($q) => $q->on($date)->authorized())
            ->whereDoesntHave('days', fn ($q) => $q->on($date)->completed())
            ->update(['status' => Diet::ACTIVE]);

        if ($count > 0) {
            LiveUpdates::touch(LiveUpdates::DAY);
        }

        return $count;
    }

    /* ===================== CONSULTAS PARA LA UI ===================== */

    /**
     * Estado del día por dieta: [dietId => ['completed' => bool, 'authorizedToday' => bool, 'authorizedTomorrow' => bool]]
     */
    public function statusByDiet(array $dietIds, ?CarbonInterface $date = null): array
    {
        $date = self::day($date);
        $days = DietDay::whereIn('diet_id', $dietIds)
            ->whereDate('service_date', '>=', $date->toDateString())
            ->whereDate('service_date', '<=', $date->addDay()->toDateString())
            ->get()
            ->groupBy('diet_id');

        return collect($dietIds)->mapWithKeys(function ($id) use ($days, $date) {
            $rows = $days->get($id, collect());
            $today = $rows->first(fn (DietDay $d) => $d->service_date->isSameDay($date));
            $tomorrow = $rows->first(fn (DietDay $d) => $d->service_date->isSameDay($date->addDay()));

            return [$id => [
                'completed' => (bool) $today?->isCompleted(),
                'authorizedToday' => (bool) $today?->isAuthorized(),
                'authorizedTomorrow' => (bool) $tomorrow?->isAuthorized(),
            ]];
        })->all();
    }

    private static function day(?CarbonInterface $date = null): CarbonImmutable
    {
        return CarbonImmutable::parse($date ?? now())->startOfDay();
    }
}
