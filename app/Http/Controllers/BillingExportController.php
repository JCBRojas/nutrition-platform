<?php

namespace App\Http\Controllers;

use App\Exports\BillingWorkbook;
use App\Services\BillingService;
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

        $workbook = new BillingWorkbook(
            $billing->monthly($month),
            $billing->yearTotals($month),
            config('billing.unit'),
        );
        $spreadsheet = $workbook->build();

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $workbook->fileName(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
