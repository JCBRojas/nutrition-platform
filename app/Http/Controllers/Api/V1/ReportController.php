<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportExportController;
use App\Models\Diet;
use App\Services\Reports\DietReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * «Reporte nutricional» en la app de escritorio: los indicadores y totales por tiempo del
 * reporte web (DietController::nutritionReport) y el mismo Excel del mes. Solo los roles de
 * config('desktop.nutrition_roles') con viewReports.
 */
class ReportController extends Controller
{
    public function __construct(private DietReportService $reports) {}

    /** GET /reports/monthly?mes=YYYY-MM (no futuro; por defecto, el mes actual) */
    public function monthly(Request $request): JsonResponse
    {
        $this->authorizeReports($request);
        $month = ReportExportController::monthFromRequest($request);
        $scheduled = $this->reports->monthlyCensus($month);
        // SCRUM-16: lo programado frente a lo recibido, que es lo que cuenta en el Excel y la facturación
        $received = $this->reports->monthlyCensus($month, receivedOnly: true);
        $active = Diet::active()->with('currentVersion')->get();

        return response()->json([
            'month' => $month->format('Y-m'),
            'label' => ucfirst($month->locale('es')->isoFormat('MMMM YYYY')),
            'days_counted' => $scheduled['daysCounted'],
            'kpis' => [
                'scheduled_trays' => $scheduled['totalTrays'],
                'received_trays' => $received['totalTrays'],
                'avg_daily_diets' => $scheduled['avgDailyDiets'],
                'days_with_diets' => $scheduled['daysWithDiets'],
                'patients' => $scheduled['perDiet']->count(),
                'active_now' => $active->count(),
                'isolated_now' => $active->filter(fn (Diet $d) => $d->currentVersion?->isolation === 'Sí')->count(),
            ],
            'meals' => collect(DietReportService::mealLabels())->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'scheduled' => $scheduled['totals'][$key] ?? 0,
                'received' => $received['totals'][$key] ?? 0,
            ])->values(),
        ]);
    }

    /** GET /reports/monthly/excel?mes=YYYY-MM: el mismo archivo y la misma auditoría que la web */
    public function monthlyExcel(Request $request, ReportExportController $export): BinaryFileResponse
    {
        $this->authorizeReports($request);

        return $export->monthlyExcel($request);
    }

    private function authorizeReports(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(config('desktop.nutrition_roles')) && $user->can('viewReports'), 403, 'El reporte nutricional es solo para nutrición.');
    }
}
