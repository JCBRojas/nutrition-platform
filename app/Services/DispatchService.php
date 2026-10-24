<?php

namespace App\Services;

use App\Models\Diet;
use App\Models\DietDispatch;
use App\Models\DietDispatchVoid;
use App\Services\Reports\DietReportService;
use App\Support\LiveUpdates;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Despacho de dietas por tiempo de comida y confirmación de recepción.
 *
 * 1. El despachador selecciona las dietas listas de un servicio (desayuno, almuerzo…)
 *    y las despacha: se crea un DietDispatch por dieta × día × tiempo.
 * 2. El nutricionista lo ve en su panel y confirma la recepción (received_at).
 *    Al recibirse todos los tiempos del día, la dieta queda Completada (DietDayService).
 * 3. BillingService solo factura los días completados (antes, los tiempos recibidos).
 * 4. Si no llegó, el nutricionista lo reporta; si cocina se equivocó, el despachador lo anula
 *    (voidDispatch). Mientras no se haya recibido, ese tiempo se puede volver a despachar.
 *
 * Solo se despacha una dieta autorizada para el día: por su creación o por «Continuar dieta».
 */
class DispatchService
{
    public function __construct(
        private DietReportService $reports,
        private DietDayService $days,
    ) {}

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
     * $diet debe traer currentVersion; has_pending (withExists) y authorized_for_day
     * (authorizedIds) evitan consultas.
     */
    public function blockReason(Diet $diet, string $meal, ?CarbonInterface $date = null): ?string
    {
        // Alta con envío a otro lugar: ese día solo se despachan los tiempos indicados
        if ($transfer = $diet->transferMealsOn($date)) {
            if (! in_array($meal, $transfer, true)) {
                return 'Alta: no incluye '.mb_strtolower(self::meals()[$meal] ?? $meal).'.';
            }

            $pending = $diet->has_pending ?? $diet->history()->where('modify', 'si')->exists();

            return $pending ? 'Confirma el alta antes de despachar.' : null;
        }

        if (! $diet->isActive()) {
            return 'La dieta no está activa.';
        }

        $authorized = $diet->authorized_for_day ?? $this->days->isAuthorized($diet, $date);
        if (! $authorized) {
            return 'Sin autorización para hoy: nutrición o enfermería debe usar «Continuar dieta».';
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

        $day = CarbonImmutable::parse($date ?? now())->startOfDay();
        $date = $day->toDateString();
        $result = ['dispatched' => [], 'skipped' => []];

        // Inicio del día por si no corrió diets:start-day: autoriza las no continuadas y reactiva las Completadas
        if ($day->isToday()) {
            $this->days->startDay($day);
        }

        DB::transaction(function () use ($dietIds, $meal, $userId, $day, $date, &$result) {
            $diets = Diet::whereIn('id', $dietIds)
                ->withExists(['history as has_pending' => fn ($q) => $q->where('modify', 'si')])
                ->with('currentVersion')
                ->lockForUpdate()
                ->orderByRoom()
                ->get();

            $already = DietDispatch::whereIn('diet_id', $diets->pluck('id'))
                ->whereDate('service_date', $date)
                ->where('meal', $meal)
                ->pluck('diet_id')
                ->all();
            $authorized = $this->days->authorizedIds($diets->pluck('id')->all(), $day);

            foreach ($diets as $diet) {
                $diet->authorized_for_day = in_array($diet->id, $authorized, true);
                $reason = in_array($diet->id, $already, true) ? 'Ya se despachó.' : $this->blockReason($diet, $meal, $day);

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

    /**
     * Tiempos de hoy que aún se pueden enviar a otro lugar al dar de alta: los de la versión
     * actual que cocina todavía no despacha. [meal => etiqueta], en orden cronológico.
     */
    public function transferCandidates(Diet $diet, ?CarbonInterface $date = null): array
    {
        $version = $diet->currentVersion;
        if (! $version) {
            return [];
        }

        $dispatched = DietDispatch::where('diet_id', $diet->id)
            ->whereDate('service_date', CarbonImmutable::parse($date ?? now())->toDateString())
            ->pluck('meal')
            ->all();

        return collect(self::meals())
            ->only(array_diff($this->reports->mealsOf($version), $dispatched))
            ->all();
    }

    /**
     * Lo que conviene saber antes de cancelar o dar de alta: despachos que aún no se
     * confirman como recibidos (salieron de cocina antes del cierre, así que se cobran
     * al confirmar la recepción) y lo ya recibido hoy.
     *
     * @return array{pending: array<int, array{date: string, today: bool, meal: string, at: string}>, receivedToday: array<int, string>, candidates: array<string, string>}
     */
    public function closingSummary(Diet $diet): array
    {
        $today = now()->toDateString();
        $rows = DietDispatch::where('diet_id', $diet->id)
            ->where(fn ($q) => $q->pendingReceipt()->orWhereDate('service_date', $today))
            ->orderBy('service_date')
            ->get();
        $order = array_flip(array_keys(self::meals()));
        $sorted = fn (Collection $c) => $c->sortBy(fn (DietDispatch $d) => [$d->service_date->toDateString(), $order[$d->meal] ?? 99])->values();

        return [
            'pending' => $sorted($rows->whereNull('received_at'))
                ->map(fn (DietDispatch $d) => [
                    'date' => $d->service_date->locale('es')->isoFormat('D [de] MMMM'),
                    'today' => $d->service_date->toDateString() === $today,
                    'meal' => self::meals()[$d->meal] ?? $d->meal,
                    'at' => $d->dispatched_at->format('g:i a'),
                ])->all(),
            'receivedToday' => $sorted($rows->whereNotNull('received_at')->filter(fn (DietDispatch $d) => $d->service_date->toDateString() === $today))
                ->map(fn (DietDispatch $d) => self::meals()[$d->meal] ?? $d->meal)
                ->all(),
            'candidates' => $this->transferCandidates($diet),
        ];
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

    /** Despachos que el nutricionista aún no confirma, por fecha y habitación */
    public function pendingReceipts(): Collection
    {
        return DietDispatch::pendingReceipt()
            ->with(['diet', 'version', 'dispatchedBy'])
            ->orderBy('service_date')
            ->orderByRoom()
            ->get();
    }

    /**
     * El nutricionista confirma que recibió los despachos. Si con eso se reciben todos los
     * tiempos del día, la dieta queda Completada.
     *
     * @return array{received: int, completed: array<int, string>} habitaciones completadas
     */
    public function confirmReceipt(array $dispatchIds, int $userId): array
    {
        return DB::transaction(function () use ($dispatchIds, $userId) {
            $dispatches = DietDispatch::whereIn('id', $dispatchIds)->pendingReceipt()->with('diet')->lockForUpdate()->get();

            DietDispatch::whereIn('id', $dispatches->pluck('id'))
                ->update(['received_by' => $userId, 'received_at' => now()]);
            $dispatches->pluck('diet.company_id')->unique()->each(fn ($companyId) => LiveUpdates::touch(LiveUpdates::DISPATCH, $companyId));

            return [
                'received' => $dispatches->count(),
                'completed' => $this->days->evaluateDispatches($dispatches, $userId),
            ];
        });
    }

    /**
     * Saca un despacho que no se recibió: el nutricionista reporta que no llegó
     * (DietDispatchVoid::NOT_ARRIVED) o el despachador lo anula por error (CANCELLED).
     * Queda la copia en diet_dispatch_voids y ese tiempo se puede volver a despachar.
     * Un despacho ya recibido no se anula.
     */
    public function voidDispatch(int $dispatchId, string $kind, ?string $reason, int $userId): DietDispatchVoid
    {
        abort_unless(array_key_exists($kind, DietDispatchVoid::LABELS), 422, 'Tipo de anulación no válido.');

        return DB::transaction(function () use ($dispatchId, $kind, $reason, $userId) {
            $dispatch = DietDispatch::with('diet')->lockForUpdate()->find($dispatchId);

            if (! $dispatch) {
                throw ValidationException::withMessages(['dispatch' => 'El despacho ya no está disponible.']);
            }
            if ($dispatch->isReceived()) {
                throw ValidationException::withMessages(['dispatch' => 'El despacho ya se recibió: no se puede anular.']);
            }

            $void = DietDispatchVoid::create([
                ...$dispatch->only(['diet_id', 'diet_history_id', 'service_date', 'meal', 'dispatched_by', 'dispatched_at']),
                'kind' => $kind,
                'reason' => trim((string) $reason) ?: null,
                'voided_by' => $userId,
                'voided_at' => now(),
            ]);
            $dispatch->delete();
            LiveUpdates::touch(LiveUpdates::DISPATCH, $dispatch->diet?->company_id);

            // Sin el motivo (texto libre): la auditoría no lleva datos clínicos
            $meal = mb_strtolower(self::meals()[$dispatch->meal] ?? $dispatch->meal);
            AuditLogger::log(
                $kind === DietDispatchVoid::NOT_ARRIVED ? 'dispatch_not_arrived' : 'dispatch_cancelled',
                ($kind === DietDispatchVoid::NOT_ARRIVED ? 'Reportó que no llegó ' : 'Anuló el despacho de ')
                    ."{$meal} de la hab. {$dispatch->diet?->habitation} ({$dispatch->service_date->toDateString()})",
                $void,
                ['diet_id' => $dispatch->diet_id, 'meal' => $dispatch->meal, 'service_date' => $dispatch->service_date->toDateString()],
            );

            return $void;
        });
    }

    /** Última anulación del día y tiempo, por diet_id (aviso «No llegó» al despachador) */
    public function voidsFor(array $dietIds, string $meal, ?CarbonInterface $date = null): Collection
    {
        return DietDispatchVoid::whereIn('diet_id', $dietIds)
            ->whereDate('service_date', CarbonImmutable::parse($date ?? now())->toDateString())
            ->where('meal', $meal)
            ->orderBy('voided_at')
            ->get()
            ->keyBy('diet_id');
    }

    /** Despachos anulados o que no llegaron en un día (módulo "Despachos del día") */
    public function dayVoids(CarbonInterface $date, string $meal = '', string $search = ''): Collection
    {
        return DietDispatchVoid::query()
            ->whereDate('service_date', $date->toDateString())
            ->when($meal !== '', fn ($q) => $q->where('meal', $meal))
            ->when($search !== '', fn ($q) => $q->whereHas('diet', fn ($q) => $q
                ->where('habitation', 'like', "%{$search}%")
                ->orWhere('name_patient', 'like', "%{$search}%")))
            ->with(['diet', 'dispatchedBy', 'voidedBy'])
            ->latest('voided_at')
            ->get();
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
