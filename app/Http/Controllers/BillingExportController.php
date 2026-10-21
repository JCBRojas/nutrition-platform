<?php

namespace App\Http\Controllers;

use App\Exports\BillingWorkbook;
use App\Services\AuditLogger;
use App\Services\BillingService;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BillingExportController extends Controller
{
    /**
     * Excel de facturación del mes en el formato del hospital (?mes=YYYY-MM)
     */
    public function excel(Request $request, BillingService $billing)
    {
        $month = ReportExportController::monthFromRequest($request);
        // Dietas y precios de la empresa activa (CompanyScope); su nombre va en el archivo y la hoja
        $company = app(CurrentCompany::class)->get();

        $workbook = new BillingWorkbook(
            $billing->monthly($month),
            $billing->yearTotals($month),
            config('billing.unit'),
            $company?->name,
        );
        $spreadsheet = $workbook->build();
        AuditLogger::log('export', 'Descargó la facturación de '.$month->format('Y-m'), null, ['report' => 'facturacion', 'month' => $month->format('Y-m'), 'company_id' => $company?->id]);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $workbook->fileName(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
