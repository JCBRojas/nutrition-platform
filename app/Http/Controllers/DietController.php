<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDietRequest;
use App\Models\Diet;
use App\Models\DietHistory;
use App\Services\DietService;
use App\Services\Reports\DietReportService;
use Illuminate\Http\Request;

class DietController extends Controller
{
    public function __construct(private DietService $dietService) {}

    /**
     * Crea una nueva dieta para el usuario autenticado.
     *
     * @param  StoreDietRequest  $request  La solicitud validada con los datos de la dieta
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
     */
    public function createNewVersionDiet(StoreDietRequest $request, Diet $diet)
    {
        // dd($request->all());
        $this->dietService->createNewVersion($diet, $request->validated(), auth()->id());

        return redirect()->back()->with('success', 'Dieta actualizada');
    }

    public function nutritionDashboard()
    {
        $diets = Diet::with('currentVersion.updatedBy')->latest('updated_at')->get();
        $specLabels = config('specifications.labels');
        $consistencyLabels = config('consistency.labels');
        $foodLabels = config('timeFood.labels');

        return view('dashboard', compact('diets', 'specLabels', 'foodLabels', 'consistencyLabels'));
    }

    public function dispatcherDashboard()
    {
        return view('dashboard-dispatcher');
    }

    public function cancelDiet(Diet $diet)
    {
        $this->dietService->cancelDiet($diet, auth()->id());

        return redirect()->back()->with('success', "Dieta de la habitación {$diet->habitation} cancelada.");
    }

    /**
     * Alta del paciente: la dieta pasa a Finalizada y sale de las dietas activas
     */
    public function finishDiet(Diet $diet)
    {
        $this->dietService->finishDiet($diet, auth()->id());

        return redirect()->back()->with('success', "Dieta de {$diet->name_patient} (hab. {$diet->habitation}) finalizada por alta.");
    }

    public function index()
    {
        $now = now();

        // Última versión de cada dieta activa, creada en el periodo
        $latest = fn () => DietHistory::whereHas('diet', fn ($q) => $q->active())
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

    public function nutritionReport(Request $request, DietReportService $reports)
    {
        $month = ReportExportController::monthFromRequest($request);
        $census = $reports->monthlyCensus($month);

        return view('reports-diets-nutricion', [
            'census' => $census,
            'history' => $reports->monthlyHistory($month, 6),
            'snapshot' => $reports->currentSnapshot(),
            'mealLabels' => DietReportService::mealLabels(),
            'specLabels' => config('specifications.labels'),
            'specColors' => config('specifications.colors'),
            'selectedMonth' => $month->format('Y-m'),
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
