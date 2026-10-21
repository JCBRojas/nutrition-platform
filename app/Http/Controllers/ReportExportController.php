<?php

namespace App\Http\Controllers;

use App\Exports\MonthlySummaryExport;
use App\Services\AuditLogger;
use App\Services\Reports\DietReportService;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        // Solo datos de la empresa activa (CompanyScope); su nombre va en el archivo
        $company = app(CurrentCompany::class)->get();
        AuditLogger::log('export', 'Descargó el reporte nutricional de '.$month->format('Y-m'), null, ['report' => 'reporte_nutricional', 'month' => $month->format('Y-m'), 'company_id' => $company?->id]);

        return Excel::download(
            new MonthlySummaryExport($this->reports->monthlyCensus($month, receivedOnly: true)), // solo lo despachado y recibido
            sprintf('reporte-nutricional_%s%s_%s.xlsx', $company ? Str::slug($company->name).'_' : '', $month->format('Y-m'), now()->format('Ymd_Hi')),
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
