---
name: pdf-reports
description: Crea o modifica reportes PDF de nutrition-platform (producción de cocina, dietas activas, historial de cambios, consolidado mensual) con barryvdh/laravel-dompdf. Usar al añadir un reporte PDF, cambiar su plantilla Blade o su ruta de descarga.
---

# Reportes PDF

Contexto en `CLAUDE.md` §7. Librería: `barryvdh/laravel-dompdf` ^3.0.

## Instalación (una vez)

```bash
composer require barryvdh/laravel-dompdf:^3.0
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```
En `config/dompdf.php`: `default_paper_size => 'letter'`, `default_font => 'DejaVu Sans'` (soporta tildes/ñ), `enable_remote => false`.

## Regla principal

Los datos salen **siempre** de `App\Services\Reports\DietReportService` (compartido con Excel y con las vistas web). No consultar Eloquent desde la vista PDF ni duplicar `aggregateFoodTotals`.

```php
class DietReportService
{
    public function kitchenProduction(CarbonInterface $date): array;
    public function activeDiets(?CarbonInterface $date = null): Collection;
    public function changeHistory(CarbonInterface $from, CarbonInterface $to, ?int $dietId = null): Collection;
    public function monthlySummary(CarbonInterface $month): array;
}
```
Fechas en `America/Bogota`; rangos con `startOfDay()/endOfDay()`.

## Estructura

```
app/Http/Controllers/ReportExportController.php   // pdf(Request, string $report)
resources/views/pdf/layout.blade.php              // encabezado (logo, título, fecha generación, usuario), pie con paginación
resources/views/pdf/kitchen-production.blade.php
resources/views/pdf/active-diets.blade.php
resources/views/pdf/change-history.blade.php
resources/views/pdf/monthly-summary.blade.php
```

Controlador:
```php
public function pdf(Request $request, string $report)
{
    $this->authorize('generateFiles');            // o middleware permission:generateFiles|viewReports
    $data = $this->reportData($report, $request); // match($report) { 'produccion' => ..., default => abort(404) }

    return Pdf::loadView("pdf.{$view}", $data + [
            'generatedAt' => now(),               // timezone app = America/Bogota
            'generatedBy' => $request->user()->name,
            'labels' => [
                'timeFood' => config('timeFood.labels'),
                'consistency' => config('consistency.labels'),
                'specifications' => config('specifications.labels'),
            ],
        ])
        ->setPaper('letter', $landscape ? 'landscape' : 'portrait')
        ->download(sprintf('%s_%s.pdf', $report, now()->format('Y-m-d_Hi')));
}
```
Validar filtros (`date`, `from`, `to`, `month`) con FormRequest. Usar `->stream()` para previsualizar.

## Plantillas Blade para dompdf

- CSS **inline** en `<style>`; sin Tailwind, Vite ni JS. Soporta CSS 2.1 + parte de CSS3 (no flex/grid → usar tablas).
- Imágenes con ruta absoluta local: `public_path('assets/images/logo-dark.png')`.
- Paginación: `<script type="text/php">` con `$pdf->page_text(...)` (requiere `isPhpEnabled => true`) o contador CSS `counter(page)`.
- Tablas largas: `thead { display: table-header-group; }` y `tr { page-break-inside: avoid; }`.
- Traducir claves con `$labels['specifications'][$key] ?? $key`.
- Fechas: `$d->locale('es')->isoFormat('D MMM YYYY, h:mm a')`.

## Reportes grandes

Si superan ~500 filas o >5 s: `GenerateReportJob` (ShouldQueue) → `Pdf::loadView(...)->save(storage_path("app/reports/{$file}"))` → notificar al usuario con enlace firmado (`URL::temporarySignedRoute`, 30 min). Limpiar archivos > 7 días con tarea en `Console\Kernel`.

## Tests

```php
$this->actingAs($despachador)->get(route('reports.pdf', 'produccion'))
     ->assertOk()->assertHeader('content-type', 'application/pdf');
```
Más: usuario sin permiso → 403; reporte desconocido → 404; datos del servicio probados por separado (unit).

## Checklist

1. `DietReportService` con el método del reporte (y test unit).
2. Vista `resources/views/pdf/*.blade.php` extendiendo `pdf.layout`.
3. Caso en `ReportExportController::reportData`.
4. Botón en la vista web: `route('reports.pdf', ['report' => 'produccion', 'date' => ...])`.
5. Tests + `pint`.
