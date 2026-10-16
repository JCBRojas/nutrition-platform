<?php

namespace App\Services\Reports;

use App\Models\Diet;
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
     * @return Collection<int, array{date: CarbonImmutable, diets: array<int, array{diet: Diet, version: DietHistory}>}>
     */
    public function activeDietsByDay(CarbonInterface $month): Collection
    {
        $start = CarbonImmutable::parse($month)->startOfMonth();
        $end = $start->endOfMonth();
        $lastDay = min($end, CarbonImmutable::now()->endOfDay());

        $diets = $this->dietsForPeriod($start, $end)
            ->map(fn (Diet $diet) => $this->lifecycle($diet, $end))
            ->filter(fn ($d) => $d['reference'] !== null);

        $days = collect();
        for ($day = $start; $day <= $lastDay; $day = $day->addDay()) {
            $dayEnd = $day->endOfDay();
            $days->push([
                'date' => $day,
                'diets' => $diets
                    ->filter(fn ($d) => $d['from'] <= $dayEnd && ($d['until'] === null || $d['until'] > $dayEnd))
                    ->map(fn ($d) => ['diet' => $d['diet'], 'version' => $d['reference']])
                    ->values()
                    ->all(),
            ]);
        }

        return $days;
    }

    /**
     * Tiempos de comida de una versión normalizados a MEALS ('media_manana' → 'mediamanana')
     */
    public function mealsOf(DietHistory $version): array
    {
        return $this->normalizeMeals($version->timeFood);
    }

    public function monthlyCensus(CarbonInterface $month): array
    {
        $start = CarbonImmutable::parse($month)->startOfMonth();
        $emptyMeals = array_fill_keys(self::MEALS, 0);

        $days = collect();
        $perDiet = [];
        $consistency = [];
        $specifications = [];
        $isolationDays = 0;

        foreach ($this->activeDietsByDay($start) as $active) {
            $row = ['date' => $active['date'], 'diets' => 0, 'meals' => $emptyMeals, 'total' => 0];

            foreach ($active['diets'] as ['diet' => $diet, 'version' => $version]) {
                $meals = $this->normalizeMeals($version->timeFood);
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
     * Ciclo de vida de una dieta respecto al mes:
     * reference = última versión no cancelada creada hasta el cierre del mes,
     * from = creación (v1), until = momento de cancelación o null si sigue activa.
     */
    private function lifecycle(Diet $diet, CarbonImmutable $monthEnd): array
    {
        $history = $diet->history;
        $closing = $history->first(fn ($h) => in_array($h->changes, Diet::CLOSING_CHANGES, true));

        $until = match (true) {
            $closing !== null => $closing->created_at,            // cancelación o alta
            ! $diet->isActive() => $diet->updated_at,             // cancelaciones antiguas sin versión 'cancelada'
            default => null,
        };

        return [
            'diet' => $diet,
            'reference' => $history->last(fn ($h) => ! in_array($h->changes, Diet::CLOSING_CHANGES, true) && $h->created_at <= $monthEnd),
            'from' => $history->first()?->created_at ?? $diet->created_at,
            'until' => $until,
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
