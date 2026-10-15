---
name: excel-reports
description: Crea o modifica exportaciones Excel/CSV de nutrition-platform (dietas activas, producción de cocina, historial de cambios, consolidado mensual) con maatwebsite/excel. Usar al añadir una exportación XLSX/CSV, cambiar columnas/formato o su ruta.
---

# Reportes Excel

Contexto en `CLAUDE.md` §7. Librería: `maatwebsite/excel` ^3.1 (PhpSpreadsheet). Requiere `ext-zip`, `ext-gd`, `ext-xml`.

## Instalación (una vez)

```bash
composer require maatwebsite/excel:^3.1
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
```

## Regla principal

Los datos provienen de `App\Services\Reports\DietReportService` (mismo servicio que PDF y vistas web, ver skill `pdf-reports`). Los Exports solo dan forma: columnas, encabezados, formato.

## Estructura

```
app/Exports/ActiveDietsExport.php        // FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
app/Exports/KitchenProductionExport.php  // FromArray
app/Exports/ChangeHistoryExport.php      // FromQuery + ShouldQueue si es grande
app/Exports/MonthlySummaryExport.php     // WithMultipleSheets: hoja "Diario" + hoja "Mensual"
app/Http/Controllers/ReportExportController.php  // excel(Request, string $report)
```

Ejemplo:
```php
class ActiveDietsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle, WithColumnFormatting
{
    public function __construct(private Collection $diets) {}

    public function collection(): Collection { return $this->diets; }

    public function headings(): array
    {
        return ['Habitación', 'Paciente', 'Tiempos de comida', 'Consistencia', 'Especificaciones', 'Aislamiento', 'Versión', 'Actualizado'];
    }

    public function map($diet): array
    {
        $v = $diet->currentVersion;
        return [
            $diet->habitation,
            $diet->name_patient,
            $this->labels('timeFood', $v?->timeFood),
            $this->labels('consistency', $v?->consistency),
            $this->labels('specifications', $v?->specifications),
            $v?->isolation ?? 'No',
            $v?->version,
            $v ? Date::dateTimeToExcel($v->updated_at->timezone('America/Bogota')) : null,
        ];
    }

    private function labels(string $catalog, ?array $keys): string
    {
        return collect($keys ?? [])->map(fn ($k) => config("{$catalog}.labels.{$k}", $k))->implode(', ');
    }

    public function columnFormats(): array { return ['H' => 'dd/mm/yyyy hh:mm']; }
    public function styles(Worksheet $sheet): array { return [1 => ['font' => ['bold' => true]]]; }
    public function title(): string { return 'Dietas activas'; }
}
```

Controlador:
```php
public function excel(Request $request, string $report)
{
    $this->authorize('generateFiles');
    $export = match ($report) {
        'dietas-activas' => new ActiveDietsExport($this->reports->activeDiets()),
        'produccion'     => new KitchenProductionExport($this->reports->kitchenProduction($date)),
        default          => abort(404),
    };
    $csv = $request->query('format') === 'csv';

    return Excel::download(
        $export,
        sprintf('%s_%s.%s', $report, now()->format('Y-m-d_Hi'), $csv ? 'csv' : 'xlsx'),
        $csv ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX,
    );
}
```

## Reglas de formato

- Encabezados en español, etiquetas desde `config('*.labels')`, nunca claves snake_case crudas.
- Fechas como fecha Excel (`Date::dateTimeToExcel`) + `columnFormats`, ya convertidas a `America/Bogota`.
- Arrays JSON (`timeFood`, `specifications`) → texto separado por comas; para conteos usar columnas por tiempo de comida.
- CSV: UTF-8 con BOM (`config/excel.php` → `csv.use_bom => true`) para que Excel muestre tildes.
- Exportaciones grandes (>5.000 filas): `FromQuery` + `chunk`, `->queue()` y notificar con enlace firmado.

## Tests

```php
Excel::fake();
$this->actingAs($despachador)->get(route('reports.excel', 'dietas-activas'))->assertOk();
Excel::assertDownloaded('dietas-activas_' . now()->format('Y-m-d_Hi') . '.xlsx',
    fn (ActiveDietsExport $e) => $e->collection()->count() === 2);
```
Más: sin permiso → 403; `map()` traduce etiquetas (unit).

## Checklist

1. Método en `DietReportService` (si no existe).
2. Clase en `app/Exports`.
3. Caso en `ReportExportController::excel`.
4. Botón en vista web junto al de PDF.
5. Tests + `pint`.
