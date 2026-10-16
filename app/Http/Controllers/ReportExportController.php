<?php

namespace App\Http\Controllers;

use App\Exports\MonthlySummaryExport;
use App\Services\AuditLogger;
use App\Services\Reports\DietReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportController extends Controller
{
    public function __construct(private DietReportService $reports) {}

    /**
     * Excel con el total del mes (?mes=YYYY-MM, por defecto el mes actual)
     */
    public function monthlyExcel(Request $request)
    {
        $month = self::monthFromRequest($request);
        AuditLogger::log('export', 'Descargó el reporte nutricional de '.$month->format('Y-m'), null, ['report' => 'reporte_nutricional', 'month' => $month->format('Y-m')]);

        return Excel::download(
            new MonthlySummaryExport($this->reports->monthlyCensus($month, receivedOnly: true)), // solo lo despachado y recibido
            sprintf('reporte-nutricional_%s_%s.xlsx', $month->format('Y-m'), now()->format('Ymd_Hi')),
        );
    }

    public static function monthFromRequest(Request $request): CarbonImmutable
    {
        $data = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
        ]);

        return isset($data['mes'])
            ? CarbonImmutable::createFromFormat('!Y-m', $data['mes'])
            : CarbonImmutable::now()->startOfMonth();
    }
}
