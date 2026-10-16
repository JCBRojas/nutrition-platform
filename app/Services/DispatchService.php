<?php

namespace App\Services;

use App\Models\Diet;
use App\Models\DietDispatch;
use App\Services\Reports\DietReportService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Despacho de dietas por tiempo de comida y confirmación de recepción.
 *
 * 1. El despachador selecciona las dietas listas de un servicio (desayuno, almuerzo…)
 *    y las despacha: se crea un DietDispatch por dieta × día × tiempo.
 * 2. El nutricionista lo ve en su panel y confirma la recepción (received_at).
 * 3. BillingService solo factura los tiempos con despacho recibido.
 */
class DispatchService
{
    public function __construct(private DietReportService $reports) {}

    /** Tiempos de comida que se pueden despachar, en orden: clave => etiqueta */
    public static function meals(): array
    {
        return DietReportService::mealLabels();
    }

    /**
     * Servicio por defecto según la hora: el último cuya hora programada ya pasó
     * (antes del desayuno, el desayuno).
     */
    public static function currentMeal(?CarbonInterface $at = null): string
    {
        $at = CarbonImmutable::parse($at ?? now());
        $current = array_key_first(config('timeFood.schedule'));

        foreach (config('timeFood.schedule') as $meal => $time) {
            if ($at->startOfDay()->setTimeFromTimeString($time) <= $at) {
                $current = $meal;
            }
        }

        return $current;
    }

    /**
     * Motivo por el que una dieta no se puede despachar en ese tiempo, o null si se puede.
     * $diet debe traer currentVersion; has_pending (withExists) evita una consulta.
     */
    public function blockReason(Diet $diet, string $meal): ?string
    {
        if (! $diet->isActive()) {
            return 'La dieta no está activa.';
        }

        $pending = $diet->has_pending ?? $diet->history()->where('modify', 'si')->exists();
        if ($pending) {
            return 'Tiene cambios sin confirmar.';
        }

        if (! $diet->currentVersion || ! in_array($meal, $this->reports->mealsOf($diet->currentVersion), true)) {
            return 'No incluye '.mb_strtolower(self::meals()[$meal] ?? $meal).'.';
        }

        return null;
    }

    /**
     * Despacha las dietas seleccionadas. Las que no se pueden despachar (o ya se
     * despacharon en ese tiempo) se omiten y se informan.
     *
     * @return array{dispatched: array<int, string>, skipped: array<string, string>} habitaciones
     */
    public function dispatch(array $dietIds, string $meal, int $userId, ?CarbonInterface $date = null): array
    {
        abort_unless(array_key_exists($meal, self::meals()), 422, 'Tiempo de comida no válido.');

        $date = CarbonImmutable::parse($date ?? now())->toDateString();
        $result = ['dispatched' => [], 'skipped' => []];

        DB::transaction(function () use ($dietIds, $meal, $userId, $date, &$result) {
            $diets = Diet::whereIn('id', $dietIds)
                ->withExists(['history as has_pending' => fn ($q) => $q->where('modify', 'si')])
                ->with('currentVersion')
                ->lockForUpdate()
                ->orderBy('habitation')
                ->get();

            $already = DietDispatch::whereIn('diet_id', $diets->pluck('id'))
                ->whereDate('service_date', $date)
                ->where('meal', $meal)
                ->pluck('diet_id')
                ->all();

            foreach ($diets as $diet) {
                $reason = in_array($diet->id, $already, true) ? 'Ya se despachó.' : $this->blockReason($diet, $meal);

                if ($reason) {
                    $result['skipped'][$diet->habitation] = $reason;

                    continue;
                }

                DietDispatch::create([
                    'diet_id' => $diet->id,
                    'diet_history_id' => $diet->currentVersion->id,
                    'service_date' => $date,
                    'meal' => $meal,
                    'dispatched_by' => $userId,
                    'dispatched_at' => now(),
                ]);
                $result['dispatched'][] = $diet->habitation;
            }
        });

        return $result;
    }

    /** Despachos de un día y tiempo, por diet_id */
    public function dispatchesFor(array $dietIds, string $meal, ?CarbonInterface $date = null): Collection
    {
        return DietDispatch::whereIn('diet_id', $dietIds)
            ->whereDate('service_date', CarbonImmutable::parse($date ?? now())->toDateString())
            ->where('meal', $meal)
            ->get()
            ->keyBy('diet_id');
    }

    /** Despachos que el nutricionista aún no confirma, el más reciente primero */
    public function pendingReceipts(): Collection
    {
        return DietDispatch::pendingReceipt()
            ->with(['diet', 'version', 'dispatchedBy'])
            ->latest('dispatched_at')
            ->get();
    }

    /** El nutricionista confirma que recibió los despachos; devuelve cuántos confirmó */
    public function confirmReceipt(array $dispatchIds, int $userId): int
    {
        return DietDispatch::whereIn('id', $dispatchIds)
            ->pendingReceipt()
            ->update(['received_by' => $userId, 'received_at' => now()]);
    }

    /**
     * Estado de despacho del día por dieta y tiempo: [dietId => [meal => 'received'|'dispatched']]
     * (indicador de la columna Tiempo).
     */
    public function statusByDiet(array $dietIds, ?CarbonInterface $date = null): array
    {
        return DietDispatch::whereIn('diet_id', $dietIds)
            ->whereDate('service_date', CarbonImmutable::parse($date ?? now())->toDateString())
            ->get(['diet_id', 'meal', 'received_at'])
            ->groupBy('diet_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($d) => [$d->meal => $d->isReceived() ? 'received' : 'dispatched'])->all())
            ->all();
    }

    /**
     * Consulta de despachos de un día (módulo "Despachos del día").
     * $status: '' | 'pending' | 'received'
     */
    public function dayQuery(CarbonInterface $date, string $meal = '', string $status = '', string $search = '')
    {
        return DietDispatch::query()
            ->whereDate('service_date', $date->toDateString())
            ->when($meal !== '', fn ($q) => $q->where('meal', $meal))
            ->when($status === 'pending', fn ($q) => $q->pendingReceipt())
            ->when($status === 'received', fn ($q) => $q->received())
            ->when($search !== '', fn ($q) => $q->whereHas('diet', fn ($q) => $q
                ->where('habitation', 'like', "%{$search}%")
                ->orWhere('name_patient', 'like', "%{$search}%")));
    }

    /**
     * Resumen del día por tiempo: [meal => ['dispatched' => n, 'received' => n, 'planned' => ?n]].
     * 'planned' (dietas activas que incluyen el tiempo) solo para hoy.
     */
    public function daySummary(CarbonInterface $date): array
    {
        $date = CarbonImmutable::parse($date);
        $rows = DietDispatch::whereDate('service_date', $date->toDateString())->get(['meal', 'received_at']);
        $planned = $date->isToday()
            ? Diet::active()->with('currentVersion')->get()
                ->flatMap(fn (Diet $d) => $d->currentVersion ? $this->reports->mealsOf($d->currentVersion) : [])
                ->countBy()
            : null;

        return collect(self::meals())->map(fn ($label, $meal) => [
            'label' => $label,
            'dispatched' => $rows->where('meal', $meal)->count(),
            'received' => $rows->where('meal', $meal)->whereNotNull('received_at')->count(),
            'planned' => $planned?->get($meal, 0),
        ])->all();
    }
}
