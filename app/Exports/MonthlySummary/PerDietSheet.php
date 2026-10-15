<?php

namespace App\Exports\MonthlySummary;

use App\Services\Reports\DietReportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PerDietSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(private array $census) {}

    public function headings(): array
    {
        return ['Habitación', 'Paciente', 'Días con dieta', ...array_values(DietReportService::mealLabels()), 'Total bandejas'];
    }

    public function array(): array
    {
        return $this->census['perDiet']->map(fn ($d) => [
            $d['habitation'],
            $d['patient'],
            $d['days'],
            ...array_values($d['meals']),
            $d['total'],
        ])->all();
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('C2');

        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Por paciente';
    }
}
