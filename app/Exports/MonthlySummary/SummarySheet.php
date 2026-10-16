<?php

namespace App\Exports\MonthlySummary;

use App\Services\Reports\DietReportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SummarySheet implements FromArray, ShouldAutoSize, WithStrictNullComparison, WithStyles, WithTitle
{
    /** Filas con estilo de sección (1-based) */
    private array $sectionRows = [];

    public function __construct(private array $census) {}

    public function array(): array
    {
        $c = $this->census;
        $rows = [
            ['Reporte nutricional — total del mes'],
            ['Mes', ucfirst($c['month']->locale('es')->isoFormat('MMMM YYYY'))],
            ['Días contados', $c['daysCounted']],
            ['Generado', now()->format('d/m/Y h:i a')],
            ['Método', 'Última versión de cada dieta × días activa en el mes'],
            [''],
        ];

        $rows = $this->section($rows, ['Tiempo de comida', 'Bandejas', '% del total']);
        foreach (DietReportService::mealLabels() as $key => $label) {
            $count = $c['totals'][$key];
            $rows[] = [$label, $count, $c['totalTrays'] ? round($count / $c['totalTrays'], 4) : 0];
        }
        $rows[] = ['Total bandejas', $c['totalTrays'], $c['totalTrays'] ? 1 : 0];
        $rows[] = [''];

        $rows = $this->section($rows, ['Indicador', 'Valor']);
        $rows[] = ['Dietas-día (suma diaria de dietas vigentes)', $c['dietDays']];
        $rows[] = ['Promedio diario de dietas', $c['avgDailyDiets']];
        $rows[] = ['Dietas-día en aislamiento', $c['isolationDays']];
        $rows[] = ['Pacientes distintos en el mes', $c['perDiet']->count()];
        $rows[] = [''];

        $rows = $this->section($rows, ['Consistencia', 'Dietas-día']);
        foreach ($c['consistency'] as $key => $count) {
            $rows[] = [DietReportService::label('consistency', $key), $count];
        }
        $rows[] = [''];

        $rows = $this->section($rows, ['Especificación', 'Dietas-día']);
        foreach ($c['specifications'] as $key => $count) {
            $rows[] = [DietReportService::label('specifications', $key), $count];
        }

        return $rows;
    }

    private function section(array $rows, array $header): array
    {
        $rows[] = $header;
        $this->sectionRows[] = count($rows);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $this->array(); // calcula $sectionRows
        $styles = [1 => ['font' => ['bold' => true, 'size' => 14]]];

        foreach ($this->sectionRows as $row) {
            $styles[$row] = ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8EEF7']]];
        }

        // % del total en la primera sección
        $first = $this->sectionRows[0];
        $sheet->getStyle('C'.($first + 1).':C'.($first + count(DietReportService::MEALS) + 1))
            ->getNumberFormat()->setFormatCode('0.0%');
        $sheet->getStyle('A'.($first + count(DietReportService::MEALS) + 1).':C'.($first + count(DietReportService::MEALS) + 1))
            ->getFont()->setBold(true);

        return $styles;
    }

    public function title(): string
    {
        return 'Resumen';
    }
}
