<?php

namespace App\Exports\MonthlySummary;

use App\Services\Reports\DietReportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DailySheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(private array $census) {}

    public function headings(): array
    {
        return ['Fecha', 'Dietas vigentes', ...array_values(DietReportService::mealLabels()), 'Total bandejas'];
    }

    public function array(): array
    {
        $rows = $this->census['days']->map(fn ($day) => [
            Date::dateTimeToExcel($day['date']),
            $day['diets'],
            ...array_values($day['meals']),
            $day['total'],
        ])->all();

        $rows[] = ['Total', $this->census['dietDays'], ...array_values($this->census['totals']), $this->census['totalTrays']];

        return $rows;
    }

    public function columnFormats(): array
    {
        return ['A' => 'dd/mm/yyyy'];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [
            1 => ['font' => ['bold' => true]],
            $this->census['days']->count() + 2 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Diario';
    }
}
