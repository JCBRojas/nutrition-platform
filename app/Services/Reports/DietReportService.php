<?php

namespace App\Services\Reports;

use App\Models\Diet;
use App\Models\DietDay;
use App\Models\DietDispatch;
use App\Models\DietHistory;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Fuente única de datos para el reporte nutricional (vista web y Excel).
 *
 * Método de conteo ("última versión × días"): cada dieta se cuenta con su
 * ÚLTIMA versión registrada hasta el cierre del mes (sin la versión
 * 'cancelada' o 'finalizada', que son copia de la anterior). Cada tiempo de comida de esa
 * versión es una bandeja por cada día en que la dieta estuvo activa: desde su
 * creación hasta su cancelación o alta, evaluado al cierre del día (23:59:59
 * America/Bogota). Las versiones intermedias no se usan.
 *
 * Día del alta (docs/bussiness-rules/facturacion-alta-paciente.md): solo cuentan
 * los tiempos de comida programados (config('timeFood.schedule')) a la hora del
 * alta o antes. Alta 15:00 con desayuno, almuerzo y cena → desayuno y almuerzo.
 * Los tiempos sin horario (fraccionada) no cuentan ese día. Aplica a todo lo que
 * sale de aquí: reporte, Excel, facturación e indicadores.
 *
 * Solo lo recibido ($receivedOnly, Excel y facturación): desde config('dispatch.required_from')
 * cada tiempo cuenta únicamente si tiene despacho con recepción confirmada por el
 * nutricionista (diet_days sin ningún tiempo recibido no cuentan). Los tiempos
 * descartados se devuelven en `withheld` de cada día.
 *
 * Solo días completados ($receivedOnly): desde config('dispatch.completion_required_from')
 * un día de una dieta cuenta únicamente si quedó Completado (diet_days.completed_at: se
 * recibieron todos sus tiempos exigibles, ver DietDayService), y de ese día los tiempos
 * recibidos. Los días sin completar van completos a `withheld`.
 */
class DietReportService
{
    /** Orden cronológico; claves normalizadas = claves de config/timeFood.php */
    public const MEALS = ['desayuno', 'mediamanana', 'almuerzo', 'algo', 'merienda', 'cena', 'fraccionada'];

    /**
     * Regla compartida por el reporte y la facturación: días transcurridos del mes y,
     * por cada día, las dietas activas con su versión de referencia (última versión
     * no cancelada hasta el cierre del mes).
     *
     * `meals` son los tiempos que cuentan ese día (en el día del alta, solo los anteriores a la hora del alta).
     * Con $receivedOnly, solo los despachados y recibidos (y desde el corte, solo de días completados);
     * `withheld` lista los descartados.
     *
     * @return Collection<int, array{date: CarbonImmutable, withheld: array<int, string>, diets: array<int, array{diet: Diet, version: DietHistory, meals: array<int, string>}>}>
     */
    public function activeDietsByDay(CarbonInterface $month, bool $receivedOnly = false): Collection
    {
        $start = CarbonImmutable::parse($month)->startOfMonth();
        $end = $start->endOfMonth();
        $lastDay = min($end, CarbonImmutable::now()->endOfDay());

        $diets = $this->dietsForPeriod($start, $end)
            ->map(fn (Diet $diet) => $this->lifecycle($diet, $end))
            ->filter(fn ($d) => $d['reference'] !== null);

        $requiredFrom = $receivedOnly ? self::dispatchRequiredFrom() : null;
        $completionFrom = $receivedOnly ? self::completionRequiredFrom() : null;
        $received = $requiredFrom || $completionFrom ? $this->receivedKeys($start, $end) : [];
        $completed = $completionFrom ? $this->completedKeys($start, $end) : [];

        $days = collect();
        for ($day = $start; $day <= $lastDay; $day = $day->addDay()) {
            $entries = $diets->map(fn ($d) => $this->dayEntry($d, $day))->filter()->values();
            $withheld = [];
            $mustComplete = $completionFrom && $day >= $completionFrom;

            if ($mustComplete || ($requiredFrom && $day >= $requiredFrom)) {
                $entries = $entries->map(function ($entry) use ($day, $received, $completed, $mustComplete, &$withheld) {
                    $id = $entry['diet']->id;

                    // Día del alta o de la cancelación: cuenta lo recibido aunque el día no se haya
                    // completado, incluido lo despachado antes del cierre aunque su hora fuera posterior
                    if ($entry['closedAt'] !== null) {
                        $kept = array_values(array_filter($entry['allMeals'], function ($meal) use ($entry, $received, $id, $day) {
                            $dispatchedAt = $received[self::dispatchKey($id, $day, $meal)] ?? null;

                            return $dispatchedAt !== null
                                && (in_array($meal, $entry['meals'], true) || $dispatchedAt <= $entry['closedAt']);
                        }));
                        array_push($withheld, ...array_diff($entry['meals'], $kept));

                        return $kept === [] ? null : ['meals' => $kept] + $entry;
                    }

                    if ($mustComplete && ! isset($completed[self::dayKey($id, $day)])) {
                        array_push($withheld, ...$entry['meals']);

                        return null;
                    }

                    $kept = array_values(array_filter($entry['meals'], fn ($meal) => isset($received[self::dispatchKey($id, $day, $meal)])));
                    array_push($withheld, ...array_diff($entry['meals'], $kept));

                    return $kept === [] ? null : ['meals' => $kept] + $entry;
                })->filter()->values();
            }

            // Sin la regla de lo recibido: el día de la cancelación no cuenta, ni el del alta sin tiempos anteriores al alta
            $entries = $entries->reject(fn ($entry) => $entry['closedAt'] !== null && $entry['meals'] === [])->values();

            $days->push(['date' => $day, 'diets' => $entries->all(), 'withheld' => $withheld]);
        }

        return $days;
    }

    /** Fecha desde la que se exige despacho recibido, o null si no se exige */
    public static function dispatchRequiredFrom(): ?CarbonImmutable
    {
        $from = config('dispatch.required_from');

        return $from ? CarbonImmutable::parse($from)->startOfDay() : null;
    }

    /** Fecha desde la que solo se cuentan días completados, o null si no se exige */
    public static function completionRequiredFrom(): ?CarbonImmutable
    {
        $from = config('dispatch.completion_required_from');

        return $from ? CarbonImmutable::parse($from)->startOfDay() : null;
    }

    public static function dispatchKey(int $dietId, CarbonInterface $date, string $meal): string
    {
        return self::dayKey($dietId, $date).'|'.$meal;
    }

    public static function dayKey(int $dietId, CarbonInterface $date): string
    {
        return $dietId.'|'.$date->format('Y-m-d');
    }

    /** Días completados del rango como conjunto "dietId|Y-m-d" => true */
    private function completedKeys(CarbonInterface $from, CarbonInterface $to): array
    {
        return DietDay::completed()
            ->whereDate('service_date', '>=', $from->toDateString())
            ->whereDate('service_date', '<=', $to->toDateString())
            ->get(['diet_id', 'service_date'])
            ->mapWithKeys(fn ($d) => [self::dayKey($d->diet_id, $d->service_date) => true])
            ->all();
    }

    /** Despachos recibidos del rango: "dietId|Y-m-d|meal" => momento del despacho */
    private function receivedKeys(CarbonInterface $from, CarbonInterface $to): array
    {
        return DietDispatch::received()
            ->whereDate('service_date', '>=', $from->toDateString())
            ->whereDate('service_date', '<=', $to->toDateString())
            ->get(['diet_id', 'service_date', 'meal', 'dispatched_at'])
            ->mapWithKeys(fn ($d) => [self::dispatchKey($d->diet_id, $d->service_date, $d->meal) => CarbonImmutable::parse($d->dispatched_at)])
            ->all();
    }

    /**
     * Tiempos de comida que se sirven antes de $moment el mismo día (regla del alta).
     * Un tiempo cuenta si su hora programada es igual o anterior a la del alta.
     */
    public function mealsServedBefore(array $meals, CarbonInterface $moment): array
    {
        $schedule = config('timeFood.schedule', []);
        $moment = CarbonImmutable::parse($moment);

        return array_values(array_filter($meals, fn ($meal) => isset($schedule[$meal])
            && $moment->startOfDay()->setTimeFromTimeString($schedule[$meal]) <= $moment));
    }

    /**
     * Tiempos de comida de una versión normalizados a MEALS ('media_manana' → 'mediamanana')
     */
    public function mealsOf(DietHistory $version): array
    {
        return $this->normalizeMeals($version->timeFood);
    }

    /** Con $receivedOnly (Excel) solo cuentan los tiempos despachados y recibidos */
    public function monthlyCensus(CarbonInterface $month, bool $receivedOnly = false): array
    {
        $start = CarbonImmutable::parse($month)->startOfMonth();
        $emptyMeals = array_fill_keys(self::MEALS, 0);

        $days = collect();
        $perDiet = [];
        $consistency = [];
        $specifications = [];
        $isolationDays = 0;

        $withheld = 0;

        foreach ($this->activeDietsByDay($start, $receivedOnly) as $active) {
            $withheld += count($active['withheld']);
            $row = ['date' => $active['date'], 'diets' => 0, 'meals' => $emptyMeals, 'total' => 0];

            foreach ($active['diets'] as ['diet' => $diet, 'version' => $version, 'meals' => $meals]) {
                $row['diets']++;

                $perDiet[$diet->id] ??= [
                    'habitation' => $diet->habitation,
                    'patient' => $diet->name_patient,
                    'days' => 0,
                    'meals' => $emptyMeals,
                    'total' => 0,
                ];
                $perDiet[$diet->id]['days']++;

                foreach ($meals as $meal) {
                    $row['meals'][$meal]++;
                    $perDiet[$diet->id]['meals'][$meal]++;
                }
                $row['total'] += count($meals);
                $perDiet[$diet->id]['total'] += count($meals);

                foreach ((array) $version->consistency as $key) {
                    $consistency[$key] = ($consistency[$key] ?? 0) + 1;
                }
                foreach ((array) $version->specifications as $key) {
                    $specifications[$key] = ($specifications[$key] ?? 0) + 1;
                }
                if ($version->isolation === 'Sí') {
                    $isolationDays++;
                }
            }

            $days->push($row);
        }

        $totals = $emptyMeals;
        foreach ($days as $row) {
            foreach ($row['meals'] as $meal => $count) {
                $totals[$meal] += $count;
            }
        }

        $dietDays = $days->sum('diets');
        arsort($consistency);
        arsort($specifications);

        return [
            'month' => $start,
            'daysCounted' => $days->count(),
            'days' => $days,
            'totals' => $totals,
            'totalTrays' => array_sum($totals),
            'dietDays' => $dietDays,
            'avgDailyDiets' => $days->isEmpty() ? 0 : round($dietDays / $days->count(), 1),
            'isolationDays' => $isolationDays,
            'consistency' => $consistency,
            'specifications' => $specifications,
            'perDiet' => collect($perDiet)->sortBy('habitation', SORT_NATURAL)->values(),
            'receivedOnly' => $receivedOnly,
            'notReceived' => $withheld,
        ];
    }

    /**
     * Totales de los últimos $count meses hasta $until (incluido), del más reciente al más antiguo
     */
    public function monthlyHistory(CarbonInterface $until, int $count = 6): Collection
    {
        $until = CarbonImmutable::parse($until)->startOfMonth();

        return collect(range(0, $count - 1))->map(function ($i) use ($until) {
            $census = $this->monthlyCensus($until->subMonths($i));

            return [
                'month' => $census['month'],
                'totals' => $census['totals'],
                'totalTrays' => $census['totalTrays'],
                'avgDailyDiets' => $census['avgDailyDiets'],
            ];
        });
    }

    /**
     * Foto actual: dietas activas con su versión vigente
     */
    public function currentSnapshot(): array
    {
        $diets = Diet::active()->with('currentVersion')->get();

        return [
            'totalActiveDiets' => $diets->count(),
            'topSpecifications' => $diets->flatMap(fn ($d) => (array) ($d->currentVersion?->specifications ?? []))
                ->countBy()->sortDesc()->take(6),
            'isolationCounts' => $diets->countBy(fn ($d) => $d->currentVersion?->isolation ?? 'No'),
        ];
    }

    /* ===================== ETIQUETAS ===================== */

    public static function mealLabels(): array
    {
        return collect(self::MEALS)->mapWithKeys(fn ($m) => [$m => self::label('timeFood', $m)])->all();
    }

    public static function label(string $catalog, string $key): string
    {
        return config("{$catalog}.labels.{$key}") ?? Str::of($key)->replace('_', ' ')->ucfirst()->toString();
    }

    /* ===================== INTERNOS ===================== */

    private function dietsForPeriod(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Diet::query()
            ->where('created_at', '<=', $end)
            ->where(fn ($q) => $q->active()->orWhere('updated_at', '>=', $start))
            ->with(['history' => fn ($q) => $q->orderBy('version')])
            ->get();
    }

    /**
     * Entrada de una dieta en un día, o null si ese día no cuenta.
     * Activa al cierre del día → todos sus tiempos. Día del alta → solo los
     * tiempos anteriores a la hora del alta. Día de la cancelación → ninguno por
     * horario (se evalúa al cierre del día). En ambos, closedAt y allMeals permiten
     * contar después lo despachado y recibido antes del cierre (regla de lo recibido).
     */
    private function dayEntry(array $d, CarbonImmutable $day): ?array
    {
        $dayEnd = $day->endOfDay();

        if ($d['from'] > $dayEnd || ($d['until'] !== null && $d['until'] < $day)) {
            return null;
        }

        $allMeals = $meals = $this->normalizeMeals($d['reference']->timeFood);
        $closesToday = $d['until'] !== null && $d['until'] <= $dayEnd;

        if ($closesToday) {
            $meals = $d['closing'] === 'finalizada' ? $this->mealsServedBefore($meals, $d['until']) : [];
        }

        return [
            'diet' => $d['diet'],
            'version' => $d['reference'],
            'meals' => $meals,
            'allMeals' => $allMeals,
            'closedAt' => $closesToday ? $d['until'] : null,
        ];
    }

    /**
     * Ciclo de vida de una dieta respecto al mes:
     * reference = última versión no cancelada creada hasta el cierre del mes,
     * from = creación (v1), until = momento de cancelación o alta (null si sigue activa),
     * closing = 'cancelada' | 'finalizada' | null.
     */
    private function lifecycle(Diet $diet, CarbonImmutable $monthEnd): array
    {
        $history = $diet->history;
        $closing = $history->first(fn ($h) => in_array($h->changes, Diet::CLOSING_CHANGES, true));

        [$until, $kind] = match (true) {
            $closing !== null => [$closing->created_at, $closing->changes],  // cancelación o alta
            // cierres antiguos sin versión de cierre
            ! $diet->isActive() => [$diet->updated_at, $diet->status === Diet::FINISHED ? 'finalizada' : 'cancelada'],
            default => [null, null],
        };

        return [
            'diet' => $diet,
            'reference' => $history->last(fn ($h) => ! in_array($h->changes, Diet::CLOSING_CHANGES, true) && $h->created_at <= $monthEnd),
            'from' => $history->first()?->created_at ?? $diet->created_at,
            'until' => $until ? CarbonImmutable::parse($until) : null,
            'closing' => $kind,
        ];
    }

    /**
     * 'Desayuno', 'media_manana', 'mediamanana' → claves de MEALS (sin repetir)
     */
    private function normalizeMeals($timeFood): array
    {
        return collect((array) $timeFood)
            ->map(fn ($v) => str_replace('_', '', mb_strtolower((string) $v)))
            ->filter(fn ($v) => in_array($v, self::MEALS, true))
            ->unique()
            ->values()
            ->all();
    }
}
