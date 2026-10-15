<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDietRequest;
use App\Http\Requests\StoreDietVersionRequest;
use App\Models\Diet;
use App\Models\DietHistory;
use App\Services\DietService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DietController extends Controller
{


    public function __construct(private DietService $dietService) {}


    /**
     * Crea una nueva dieta para el usuario autenticado.
     *
     * @param StoreDietRequest $request La solicitud validada con los datos de la dieta
     * @return \Illuminate\Http\RedirectResponse Redirige a la página anterior con mensaje de éxito
     */
    public function createDiet(StoreDietRequest $request)
    {
        $diet = $this->dietService->createDiet($request->validated(), auth()->id());
        return redirect()->back()->with('success', 'Dieta creada');
    }

    /**
     * Edita o crea una nueva versión de dieta
     *
     * Este método permite editar una dieta existente o crear una nueva versión
     * de la misma, permitiendo mantener un historial de cambios en las dietas
     * de los usuarios.
     *
     */
    public function createNewVersionDiet(StoreDietRequest $request, Diet $diet)
    {
        // dd($request->all());
        $this->dietService->createNewVersion($diet, $request->validated(), auth()->id());
        return redirect()->back()->with('success', 'Dieta actualizada');
    }

    public function nutritionDashboard()
    {
        $diets = Diet::all();
        $specLabels = config('specifications.labels');
        $consistencyLabels = config('consistency.labels');
        $foodLabels = config('timeFood.labels');
        return view('dashboard', compact('diets','specLabels','foodLabels','consistencyLabels'));
    }

    public function dispatcherDashboard()
    {
        return view('dashboard-dispatcher');
    }

    public function cancelDiet(Diet $diet)
    {
        $this->dietService->cancelDiet($diet, auth()->id());
        return redirect()->back()->with('success', 'Dieta cancelada');
    }

    public function index()
    {
        $now = now();

        // Última versión de cada dieta activa, creada en el periodo
        $latest = fn () => DietHistory::whereHas('diet', fn ($q) => $q->where('status', true))
            ->whereRaw('version = (SELECT MAX(h2.version) FROM diet_histories h2 WHERE h2.diet_id = diet_histories.diet_id)');

        // PRODUCCIÓN DE COCINA HOY
        $kitchenToday = [(object) $this->aggregateFoodTotals(
            $latest()->whereDate('created_at', $now->toDateString())->get()
        )];

        // TOTALES GLOBALES (mes actual)
        $monthHistories = $latest()
            ->whereBetween('created_at', [$now->clone()->startOfMonth(), $now->clone()->endOfMonth()])
            ->get();
        $global = [(object) array_merge(
            $this->aggregateFoodTotals($monthHistories),
            ['total_bandejas' => $monthHistories->count()]
        )];

        // PRODUCCIÓN MENSUAL
        $monthly = $latest()->get()
            ->groupBy(fn ($h) => $h->created_at->format('Y-m'))
            ->map(fn ($group, $mes) => (object) array_merge(['mes' => $mes], $this->aggregateFoodTotals($group)))
            ->sortByDesc('mes')
            ->values();

        return view('reports-diets', [
            'kitchenToday' => $kitchenToday,
            'global' => $global,
            'monthly' => $monthly,
        ]);
    }


    public function nutritionReport()
    {
        $now = now();

        // Get active diets with current version
        $diets = Diet::where('status', true)
            ->with('currentVersion')
            ->get();

        // Get current month histories with latest version
        $histories = DietHistory::whereHas('diet', fn($q) => $q->where('status', true))
            ->where('version', DB::raw('(SELECT MAX(version) FROM diet_histories WHERE diet_id = diet_histories.diet_id)'))
            ->whereBetween('created_at', [$now->clone()->startOfMonth(), $now->clone()->endOfMonth()])
            ->get();

        // Calculate totals by grouping histories
        $dailyTotals = $histories->groupBy(fn($h) => $h->created_at->format('Y-m-d'))
            ->map(fn($group) => $this->aggregateFoodTotals($group))
            ->map(fn($totals, $date) => (object)array_merge(['fecha' => $date], $totals));

        $monthlyTotals = $histories->groupBy(fn($h) => $h->created_at->format('Y-m'))
            ->map(fn($group) => $this->aggregateFoodTotals($group))
            ->map(fn($totals, $month) => (object)array_merge(['mes' => $month], $totals))
            ->sortByDesc('mes');

        // Global totals
        $globalTotals = (object)$this->aggregateFoodTotals($histories);

        // Statistics
        $topSpecifications = $diets->flatMap(fn($d) => $d->currentVersion?->specifications ?? [])
            ->countBy()
            ->sortDesc()
            ->take(6);

        $isolationCounts = $diets->countBy(fn($d) => $d->currentVersion?->isolation ?? 'No');

        // Chart data
        $chartLabels = $monthlyTotals->pluck('mes')->values();
        $chartBreakfast = $monthlyTotals->pluck('desayuno')->values();
        $chartLunch = $monthlyTotals->pluck('almuerzo')->values();
        $chartDinner = $monthlyTotals->pluck('cena')->values();

        return view('reports-diets-nutricion', [
            'dailyTotals' => $dailyTotals,
            'monthlyTotals' => $monthlyTotals,
            'globalTotals' => $globalTotals,
            'chartLabels' => $chartLabels,
            'chartBreakfast' => $chartBreakfast,
            'chartLunch' => $chartLunch,
            'chartDinner' => $chartDinner,
            'totalActiveDiets' => $diets->count(),
            'topSpecifications' => $topSpecifications,
            'isolationCounts' => $isolationCounts,
            'specColors' => config('specifications.colors'),
            'labelsColors' => config('specifications.labels'),
        ]);
    }

    private function aggregateFoodTotals($histories): array
    {
        $meals = ['desayuno', 'media_manana', 'almuerzo', 'algo', 'merienda', 'cena', 'fraccionada'];
        $totals = array_fill_keys($meals, 0);

        // Normaliza claves guardadas: 'Desayuno', 'mediamanana', 'media_manana' → 'desayuno', 'mediamanana'
        $normalize = fn ($value) => str_replace('_', '', mb_strtolower((string) $value));

        foreach ($histories as $history) {
            $timeFood = array_map($normalize, (array) ($history->timeFood ?? []));
            foreach ($meals as $meal) {
                if (in_array($normalize($meal), $timeFood, true)) {
                    $totals[$meal]++;
                }
            }
        }

        return $totals;
    }

    // end


}
