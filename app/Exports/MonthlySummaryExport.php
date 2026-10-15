<?php

namespace App\Exports;

use App\Exports\MonthlySummary\DailySheet;
use App\Exports\MonthlySummary\PerDietSheet;
use App\Exports\MonthlySummary\SummarySheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Total del mes del reporte nutricional. $census = DietReportService::monthlyCensus()
 */
class MonthlySummaryExport implements WithMultipleSheets
{
    public function __construct(private array $census) {}

    public function sheets(): array
    {
        return [
            new SummarySheet($this->census),
            new DailySheet($this->census),
            new PerDietSheet($this->census),
        ];
    }
}
