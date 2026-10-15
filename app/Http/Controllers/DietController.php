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

    public function cancelDiet(Diet $dietId)
    {
        $this->dietService->setDietCanceled($dietId);
    }

    public function index()
    {
        /*
        -----------------------------
        PRODUCCIÓN DE COCINA HOY
        -----------------------------
        */
        $kitchenToday = DB::select("
            SELECT
            SUM(timeFood='desayuno') AS desayuno,
            SUM(timeFood='media_manana') AS media_manana,
            SUM(timeFood='almuerzo') AS almuerzo,
            SUM(timeFood='algo') AS algo,
            SUM(timeFood='merienda') AS merienda,
            SUM(timeFood='cena') AS cena

            FROM diet_histories

            WHERE DATE(created_at)=CURDATE()

            GROUP BY timeFood
            ORDER BY timeFood
            ");

        /*
        -----------------------------
        TOTALES GLOBALES
        -----------------------------
        */

        $global = DB::select("
            SELECT

            SUM(timeFood='desayuno') AS desayuno,
            SUM(timeFood='media_manana') AS media_manana,
            SUM(timeFood='almuerzo') AS almuerzo,
            SUM(timeFood='algo') AS algo,
            SUM(timeFood='merienda') AS merienda,
            SUM(timeFood='cena') AS cena,
            SUM(timeFood='fraccionada') AS fraccionada,

            COUNT(*) AS total_bandejas

            FROM diet_histories

            WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())

            GROUP BY timeFood
            ");


        /*
        -----------------------------
        PRODUCCIÓN MENSUAL
        -----------------------------
        */

        $monthly = DB::select("
            SELECT

            DATE_FORMAT(created_at,'%Y-%m') AS mes,

            SUM(timeFood='desayuno') AS desayuno,
            SUM(timeFood='media_manana') AS media_manana,
            SUM(timeFood='almuerzo') AS almuerzo,
            SUM(timeFood='algo') AS algo,
            SUM(timeFood='merienda') AS merienda,
            SUM(timeFood='cena') AS cena

            FROM diet_histories

            GROUP BY mes
            ORDER BY mes DESC
            ");


        return view('reports-diets', [
            'kitchenToday' => $kitchenToday,
            'global' => $global,
            'monthly' => $monthly
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
        $meals = ['desayuno', 'media_manana', 'almuerzo', 'algo', 'cena', 'fraccionada'];
        $totals = array_fill_keys($meals, 0);

        foreach ($histories as $history) {
            $timeFood = $history->timeFood ?? [];
            foreach ($meals as $meal) {
                if (in_array(ucfirst($meal), $timeFood)) {
                    $totals[$meal]++;
                }
            }
        }

        return $totals;
    }

    // end


}
