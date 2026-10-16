<?php

namespace App\Exports;

use App\Services\BillingService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Replica el formato "Facturación 1 al 31 de Enero 2026 UCI.xlsx":
 *  - Hoja "{MES} {AÑO}": tipo × servicio, valor unitario, días 1–31, total servicios
 *    (=SUM) y valor total (=D*AJ), resumen por servicio (AM:AN), subtotal y total del mes.
 *  - Hoja "FACTURACIÓN MES A MES": total de cada mes del año.
 */
class BillingWorkbook
{
    private const MONEY = '"$"\ #,##0';

    private const HEADER_FILL = 'EEECE1';

    private const NO_DAY_FILL = 'D9D9D9';

    private const FIRST_ROW = 3;

    private const DAY_COL = 5;   // E

    private const DAYS = 31;     // E..AI siempre, como el formato

    private const MONTHS = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];

    /** Fila del TOTAL MES en la hoja del mes (la usa la hoja anual) */
    private int $totalRow = 0;

    /**
     * @param  array  $monthly  BillingService::monthly()
     * @param  array  $yearTotals  BillingService::yearTotals(): 'YYYY-MM' => valor
     */
    public function __construct(private array $monthly, private array $yearTotals, private string $unit) {}

    public function sheetTitle(): string
    {
        return self::MONTHS[$this->monthly['month']->month - 1].' '.$this->monthly['month']->year;
    }

    public function fileName(): string
    {
        $m = $this->monthly['month'];
        $lastDay = $this->monthly['daysCounted'] ?: $m->daysInMonth;

        return sprintf('Facturación 1 al %d de %s %d %s.xlsx', $lastDay, ucfirst(mb_strtolower(self::MONTHS[$m->month - 1])), $m->year, $this->unit);
    }

    public function build(): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle($this->fileName())->setCreator('NutriTrack');

        $month = $book->getActiveSheet();
        $month->setTitle($this->sheetTitle());
        $this->buildMonthSheet($month);

        $year = $book->createSheet();
        $year->setTitle('FACTURACIÓN MES A MES ');
        $this->buildYearSheet($year);

        $book->setActiveSheetIndex(0);

        return $book;
    }

    /* ===================== HOJA DEL MES ===================== */

    private function buildMonthSheet(Worksheet $s): void
    {
        $types = BillingService::types();
        $services = BillingService::services();
        $lastDayCol = self::DAY_COL + self::DAYS - 1;           // AI
        $aj = Coordinate::stringFromColumnIndex($lastDayCol + 1); // AJ
        $ak = Coordinate::stringFromColumnIndex($lastDayCol + 2); // AK
        $firstDay = Coordinate::stringFromColumnIndex(self::DAY_COL);
        $lastDay = Coordinate::stringFromColumnIndex($lastDayCol);

        // Encabezado
        $s->setCellValue('A1', 'TIPO DE DIETA')->mergeCells('A1:A2');
        $s->setCellValue('B1', 'SERVICIO')->mergeCells('B1:C2');
        $s->setCellValue('D1', 'VALOR UND')->mergeCells('D1:D2');
        $s->mergeCells("{$firstDay}1:{$lastDay}1");
        $s->setCellValue("{$aj}1", 'TOTAL SERVICIOS')->mergeCells("{$aj}1:{$aj}2");
        $s->setCellValue("{$ak}1", 'VALOR TOTAL')->mergeCells("{$ak}1:{$ak}2");
        for ($d = 1; $d <= self::DAYS; $d++) {
            $s->setCellValue([self::DAY_COL + $d - 1, 2], $d);
        }

        // Filas tipo × servicio
        $r = self::FIRST_ROW;
        $serviceRows = array_fill_keys(array_keys($services), []);
        $typeStart = [];

        foreach ($this->monthly['rows'] as $row) {
            $typeStart[$row['type']] ??= $r;
            if ($typeStart[$row['type']] === $r) {
                $s->setCellValue("A{$r}", $types[$row['type']]['label']);
            }

            $s->setCellValue("B{$r}", $services[$row['service']]);
            if ($row['price'] !== null) {
                $s->setCellValue("D{$r}", $row['price']);
            }
            foreach ($row['days'] as $d => $n) {
                $s->setCellValue([self::DAY_COL + $d - 1, $r], $n);
            }

            $s->setCellValue("{$aj}{$r}", "=SUM({$firstDay}{$r}:{$lastDay}{$r})");

            if ($row['priceChanges']) {
                // El precio cambió dentro del mes: valor calculado día a día (no cabe en =D*AJ)
                $s->setCellValue("{$ak}{$r}", round($row['value']));
                $note = 'Precio cambiado en el mes: '.collect($row['priceChanges'])
                    ->map(fn ($c) => 'desde '.$c['from'].' $'.number_format($c['price'], 0, ',', '.'))
                    ->implode('; ').'. Valor calculado con el precio vigente cada día.';
                $s->getComment("{$ak}{$r}")->getText()->createTextRun($note);
            } else {
                $s->setCellValue("{$ak}{$r}", "=D{$r}*{$aj}{$r}");
            }

            $serviceRows[$row['service']][] = $r;
            $r++;
        }
        $lastRow = $r - 1;

        // Celdas de tipo combinadas verticalmente
        $ends = [];
        foreach ($this->monthly['rows'] as $i => $row) {
            $ends[$row['type']] = self::FIRST_ROW + $i;
        }
        foreach ($typeStart as $type => $start) {
            if ($ends[$type] > $start) {
                $s->mergeCells("A{$start}:A{$ends[$type]}");
            }
        }

        // Subtotal y total del mes
        $subtotal = $lastRow + 1;
        $this->totalRow = $lastRow + 4;
        $s->setCellValue("A{$subtotal}", 'SUBTOTAL DIETAS');
        $s->setCellValue("{$ak}{$subtotal}", "=SUM({$ak}".self::FIRST_ROW.":{$ak}{$lastRow})");
        $s->setCellValue("A{$this->totalRow}", 'TOTAL MES');
        $s->setCellValue("{$ak}{$this->totalRow}", "={$ak}{$subtotal}");

        // Resumen por servicio (AM:AN)
        $am = Coordinate::stringFromColumnIndex($lastDayCol + 4);
        $an = Coordinate::stringFromColumnIndex($lastDayCol + 5);
        $s->setCellValue("{$am}3", 'DIETAS '.self::MONTHS[$this->monthly['month']->month - 1].' '.$this->unit)->mergeCells("{$am}3:{$an}3");
        $s->setCellValue("{$am}4", 'SERVICIO');
        $s->setCellValue("{$an}4", 'CANTIDAD');
        $summaryRow = 5;
        $summaryLabels = ['mediamanana' => 'MEDIA MAÑANA'];
        foreach ($services as $service => $label) {
            $s->setCellValue("{$am}{$summaryRow}", $summaryLabels[$service] ?? $label);
            $cells = array_map(fn ($row) => "{$aj}{$row}", $serviceRows[$service]);
            $s->setCellValue("{$an}{$summaryRow}", $cells ? '='.implode('+', $cells) : 0);
            $summaryRow++;
        }
        $s->setCellValue("{$am}{$summaryRow}", 'TOTAL');
        $s->setCellValue("{$an}{$summaryRow}", "=SUM({$an}5:{$an}".($summaryRow - 1).')');

        $this->styleMonthSheet($s, $lastRow, $subtotal, $aj, $ak, $am, $an, $summaryRow);
    }

    private function styleMonthSheet(Worksheet $s, int $lastRow, int $subtotal, string $aj, string $ak, string $am, string $an, int $summaryEnd): void
    {
        $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '808080']]]];
        $header = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ];

        $s->getStyle("A1:{$ak}2")->applyFromArray($header + $thin);
        $s->getStyle("A3:{$ak}{$lastRow}")->applyFromArray($thin);
        $s->getStyle("A3:A{$lastRow}")->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $s->getStyle("E3:{$aj}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $s->getStyle("D3:D{$lastRow}")->getNumberFormat()->setFormatCode(self::MONEY);
        $s->getStyle("{$ak}3:{$ak}{$this->totalRow}")->getNumberFormat()->setFormatCode(self::MONEY);
        $s->getStyle("A{$subtotal}:{$ak}{$subtotal}")->getFont()->setBold(true);
        $s->getStyle("A{$this->totalRow}:{$ak}{$this->totalRow}")->applyFromArray(['font' => ['bold' => true, 'size' => 12]] + $thin);

        // Días que no existen en el mes (29–31 según el mes), en gris
        for ($d = $this->monthly['daysInMonth'] + 1; $d <= self::DAYS; $d++) {
            $col = Coordinate::stringFromColumnIndex(self::DAY_COL + $d - 1);
            $s->getStyle("{$col}2:{$col}{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::NO_DAY_FILL);
        }

        $s->getStyle("{$am}3:{$an}{$summaryEnd}")->applyFromArray($thin);
        $s->getStyle("{$am}3:{$an}4")->applyFromArray($header);
        $s->getStyle("{$am}{$summaryEnd}:{$an}{$summaryEnd}")->getFont()->setBold(true);

        $s->getColumnDimension('A')->setWidth(26);
        $s->getColumnDimension('B')->setWidth(12);
        $s->getColumnDimension('C')->setWidth(2);
        $s->getColumnDimension('D')->setWidth(11);
        for ($c = self::DAY_COL; $c < self::DAY_COL + self::DAYS; $c++) {
            $s->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(4);
        }
        $s->getColumnDimension($aj)->setWidth(11);
        $s->getColumnDimension($ak)->setWidth(15);
        $s->getColumnDimension($am)->setWidth(16);
        $s->getColumnDimension($an)->setWidth(11);
        $s->getRowDimension(1)->setRowHeight(18);
        $s->freezePane('E3');
    }

    /* ===================== HOJA ANUAL ===================== */

    private function buildYearSheet(Worksheet $s): void
    {
        $year = $this->monthly['month']->year;
        $selected = $this->monthly['month']->format('Y-m');
        $ak = Coordinate::stringFromColumnIndex(self::DAY_COL + self::DAYS + 1);

        $s->setCellValue('B1', "FACTURAS {$this->unit}  {$year}")->mergeCells('B1:C1');
        $s->setCellValue('B2', 'MES');
        $s->setCellValue('C2', 'VALOR ');

        foreach (self::MONTHS as $i => $name) {
            $r = 3 + $i;
            $key = sprintf('%d-%02d', $year, $i + 1);
            $s->setCellValue("B{$r}", $name);

            if ($key === $selected) {
                $s->setCellValue("C{$r}", "=+'{$this->sheetTitle()}'!{$ak}{$this->totalRow}");
            } elseif (array_key_exists($key, $this->yearTotals)) {
                $s->setCellValue("C{$r}", round($this->yearTotals[$key]));
            }
        }
        $s->setCellValue('B15', 'TOTAL');
        $s->setCellValue('C15', '=SUM(C3:C14)');

        $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '808080']]]];
        $s->getStyle('B1:C2')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ] + $thin);
        $s->getStyle('B3:C15')->applyFromArray($thin);
        $s->getStyle('B15:C15')->getFont()->setBold(true);
        $s->getStyle('C3:C15')->getNumberFormat()->setFormatCode(self::MONEY);
        $s->getColumnDimension('B')->setWidth(16);
        $s->getColumnDimension('C')->setWidth(18);
    }
}
