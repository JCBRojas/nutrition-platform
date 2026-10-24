<?php

namespace App\Services\Reports;

use App\Models\Diet;
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
 * cuenta cada tiempo con despacho y recepción confirmada por nutrición, uno por uno: no
 * hace falta que el día quede Completado, y cuenta aunque la última versión ya no incluya
 * ese tiempo o sea el día del alta o la cancelación. Los tiempos programados sin
 * recepción se devuelven en `withheld` de cada día.
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
     * Con $receivedOnly, los recibidos por nutrición (cada uno cuenta);
     * `withheld` lista los descartados.
     *
     * `pending` explica, por dieta, lo descartado (ver addPending).
     *
     * @return Collection<int, array{date: CarbonImmutable, withheld: array<int, string>, pending: array<int, array>, diets: array<int, array{diet: Diet, version: DietHistory, meals: array<int, string>}>}>
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
        $received = $requiredFrom ? $this->receivedMeals($start, $end) : [];

        $days = collect();
        for ($day = $start; $day <= $lastDay; $day = $day->addDay()) {
            $entries = $diets->map(fn ($d) => $this->dayEntry($d, $day))->filter()->values();
            $withheld = [];
            $pending = []; // detalle de lo descartado por dieta (para explicarlo en pantalla)

            if ($requiredFrom && $day >= $requiredFrom) {
                // Cuenta cada tiempo con recepción confirmada por nutrición, aunque el día no se haya
                // completado o la última versión ya no lo incluya (también el día del alta o la cancelación)
                $entries = $entries->map(function ($entry) use ($day, $received, &$withheld, &$pending) {
                    $receivedToday = $received[self::dayKey($entry['diet']->id, $day)] ?? [];
                    $kept = array_values(array_filter(self::MEALS, fn ($meal) => isset($receivedToday[$meal])));
                    $missing = array_values(array_diff($entry['meals'], $kept));

                    array_push($withheld, ...$missing);
                    $this->addPending($pending, $entry, $missing, $kept);

                    return $kept === [] ? null : ['meals' => $kept] + $entry;
                })->filter()->values();
            }

            // Sin la regla de lo recibido: el día de la cancelación no cuenta, ni el del alta sin tiempos anteriores al alta
            $entries = $entries->reject(fn ($entry) => $entry['closedAt'] !== null && $entry['meals'] === [])->values();

            $days->push(['date' => $day, 'diets' => $entries->all(), 'withheld' => $withheld, 'pending' => $pending]);
        }

        return $days;
    }

    /** Fecha desde la que se exige despacho recibido, o null si no se exige */
    public static function dispatchRequiredFrom(): ?CarbonImmutable
    {
        $from = config('dispatch.required_from');

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

    /** Tiempos recibidos del rango agrupados por día de dieta: "dietId|Y-m-d" => [meal => true] */
    private function receivedMeals(CarbonInterface $from, CarbonInterface $to): array
    {
        $meals = [];
        DietDispatch::received()
            ->whereDate('service_date', '>=', $from->toDateString())
            ->whereDate('service_date', '<=', $to->toDateString())
            ->get(['diet_id', 'service_date', 'meal'])
            ->each(function ($d) use (&$meals) {
                $meals[self::dayKey($d->diet_id, $d->service_date)][str_replace('_', '', $d->meal)] = true;
            });

        return $meals;
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
        // SCRUM-16: el promedio se calcula sobre los días con al menos una dieta, no sobre todos los días del mes
        $daysWithDiets = $days->where('diets', '>', 0)->count();
        arsort($consistency);
        arsort($specifications);

        return [
            'month' => $start,
            'daysCounted' => $days->count(),
            'days' => $days,
            'totals' => $totals,
            'totalTrays' => array_sum($totals),
            'dietDays' => $dietDays,
            'daysWithDiets' => $daysWithDiets,
            'avgDailyDiets' => $daysWithDiets === 0 ? 0 : round($dietDays / $daysWithDiets, 1),
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
     * horario (se evalúa al cierre del día); con la regla de lo recibido cuenta lo recibido.
     */
    private function dayEntry(array $d, CarbonImmutable $day): ?array
    {
        $dayEnd = $day->endOfDay();

        if ($d['from'] > $dayEnd || ($d['until'] !== null && $d['until'] < $day)) {
            return null;
        }

        $meals = $this->normalizeMeals($d['reference']->timeFood);
        $closesToday = $d['until'] !== null && $d['until'] <= $dayEnd;

        if ($closesToday) {
            $meals = $d['closing'] === 'finalizada' ? $this->mealsServedBefore($meals, $d['until']) : [];
        }

        return [
            'diet' => $d['diet'],
            'version' => $d['reference'],
            'meals' => $meals,
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
     * Detalle de lo que no cuenta de una dieta en un día: `withheld` (tiempos programados sin
     * recepción) y `received` (los recibidos, que sí cuentan).
     */
    private function addPending(array &$pending, array $entry, array $withheld, array $received): void
    {
        if ($withheld === []) {
            return;
        }

        $pending[] = [
            'diet' => $entry['diet'],
            'withheld' => array_values($withheld),
            'received' => array_values($received),
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
