<?php

namespace App\Services\Reports;

use App\Models\Diet;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Fuente única de datos para el reporte nutricional (vista web y Excel).
 *
 * Método de conteo ("censo diario"): para cada día del mes se toma, por dieta,
 * la versión vigente al cierre del día (23:59:59 America/Bogota). Si la dieta
 * está activa ese día, cada tiempo de comida de esa versión cuenta como una
 * bandeja. El total del mes es la suma de los días transcurridos.
 */
class DietReportService
{
    /** Orden cronológico; claves normalizadas = claves de config/timeFood.php */
    public const MEALS = ['desayuno', 'mediamanana', 'almuerzo', 'algo', 'merienda', 'cena', 'fraccionada'];

    public function monthlyCensus(CarbonInterface $month): array
    {
        $start = CarbonImmutable::parse($month)->startOfMonth();
        $end = $start->endOfMonth();
        $lastDay = min($end, CarbonImmutable::now()->endOfDay());

        $diets = $this->dietsForPeriod($start, $end);
        $emptyMeals = array_fill_keys(self::MEALS, 0);

        $days = collect();
        $perDiet = [];
        $consistency = [];
        $specifications = [];
        $isolationDays = 0;

        for ($day = $start; $day <= $lastDay; $day = $day->addDay()) {
            $dayEnd = $day->endOfDay();
            $row = ['date' => $day, 'diets' => 0, 'meals' => $emptyMeals, 'total' => 0];

            foreach ($diets as $diet) {
                $version = $this->effectiveVersion($diet, $dayEnd);

                if (! $version) {
                    continue;
                }

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
        $diets = Diet::where('status', true)->with('currentVersion')->get();

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
            ->where(fn ($q) => $q->where('status', true)->orWhere('updated_at', '>=', $start))
            ->with(['history' => fn ($q) => $q->orderBy('version')])
            ->get();
    }

    /**
     * Versión vigente al cierre del día, o null si la dieta no existía o estaba cancelada
     */
    private function effectiveVersion(Diet $diet, CarbonImmutable $dayEnd)
    {
        $version = $diet->history->last(fn ($h) => $h->created_at <= $dayEnd);

        if (! $version || $version->changes === 'cancelada') {
            return null;
        }

        // Dietas canceladas antes de existir la versión 'cancelada' (solo status=false)
        if (! $diet->status && ! $diet->history->contains('changes', 'cancelada') && $diet->updated_at <= $dayEnd) {
            return null;
        }

        return $version;
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
