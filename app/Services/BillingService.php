<?php

namespace App\Services;

use App\Models\ServicePrice;
use App\Services\Reports\DietReportService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Facturación mensual por tipo de dieta × servicio (formato "Facturación ... UCI.xlsx").
 *
 * - Conteo: misma regla que el reporte nutricional (DietReportService::activeDietsByDay):
 *   última versión de cada dieta × días activa. El día del alta solo se facturan los
 *   servicios anteriores a la hora del alta (docs/bussiness-rules/facturacion-alta-paciente.md).
 * - Tipo: diet_histories.billing_type de esa versión. Sin tipo → se lista en 'unassigned'.
 * - Refrigerios de un tipo sin esa fila → config('billing.snack_type') (Entrecomidas).
 * - Fraccionada no se factura (no está en config('billing.services')).
 * - Valor: cada día se valora con el precio vigente ese día (service_prices).
 * - Por empresa (NUT-RN-003-14, -15): dietas y precios son los de la empresa activa
 *   (CompanyScope en Diet y ServicePrice).
 * - Desde config('dispatch.required_from') se factura cada tiempo de comida despachado por
 *   cocina y recibido por el nutricionista (activeDietsByDay con $receivedOnly), aunque el
 *   día no quede Completado. Lo programado sin recepción se informa en 'notReceived'.
 */
class BillingService
{
    public function __construct(private DietReportService $reports) {}

    /* ===================== CATÁLOGO ===================== */

    public static function types(): array
    {
        return config('billing.types');
    }

    public static function services(): array
    {
        return config('billing.services');
    }

    /**
     * Nombre de un servicio en la aplicación: el mismo del catálogo de tiempos de comida
     * (config/timeFood.php), p. ej. «Cena» y «Media mañana» (SCRUM-15). Las etiquetas de
     * config('billing.services') («COMIDA», «M.MAÑANA») son las del formato del hospital y
     * solo se usan en el Excel de facturación.
     */
    public static function serviceLabel(string $service): string
    {
        return config("timeFood.labels.{$service}") ?? ucfirst(mb_strtolower(self::services()[$service] ?? $service));
    }

    /** Tipos que el nutricionista puede elegir en el formulario: clave => nombre */
    public static function selectableTypes(): array
    {
        return collect(self::types())
            ->reject(fn ($t) => ($t['selectable'] ?? true) === false)
            ->map(fn ($t) => $t['name'])
            ->all();
    }

    /** Filas del formato en orden: [type, service] */
    public static function rows(): array
    {
        $rows = [];
        foreach (self::types() as $type => $def) {
            foreach (array_keys(self::services()) as $service) {
                if (in_array($service, $def['services'], true)) {
                    $rows[] = ['type' => $type, 'service' => $service];
                }
            }
        }

        return $rows;
    }

    /* ===================== PRECIOS ===================== */

    public function setPrice(string $type, string $service, float $price, CarbonInterface $validFrom, ?int $userId): ServicePrice
    {
        if (! in_array($service, self::types()[$type]['services'] ?? [], true)) {
            throw new InvalidArgumentException("El tipo {$type} no factura el servicio {$service}.");
        }

        return ServicePrice::updateOrCreate(
            ['company_id' => ServicePrice::defaultCompanyId(), 'billing_type' => $type, 'service' => $service, 'valid_from' => $validFrom->toDateString()],
            ['price' => round($price, 2), 'created_by' => $userId],
        );
    }

    /** Precios vigentes en una fecha: "tipo.servicio" => ServicePrice */
    public function pricesAt(CarbonInterface $date): Collection
    {
        return ServicePrice::where('valid_from', '<=', $date->toDateString())
            ->orderBy('valid_from')
            ->get()
            ->keyBy(fn ($p) => "{$p->billing_type}.{$p->service}"); // el último valid_from gana
    }

    public function history(string $type, string $service, int $limit = 10): Collection
    {
        return ServicePrice::with('createdBy')
            ->where('billing_type', $type)
            ->where('service', $service)
            ->orderByDesc('valid_from')
            ->limit($limit)
            ->get();
    }

    /* ===================== FACTURACIÓN DEL MES ===================== */

    /**
     * @return array{
     *   month: CarbonImmutable, daysInMonth: int, daysCounted: int,
     *   rows: array<int, array{type: string, service: string, days: array<int, int>, count: int,
     *                          price: ?float, priceChanges: array, value: float, missingPriceTrays: int}>,
     *   serviceTotals: array<string, int>, total: float, totalTrays: int,
     *   unassigned: Collection, missingPrices: array<int, string>, notReceived: int, pending: Collection
     * }
     */
    public function monthly(CarbonInterface $month): array
    {
        $start = CarbonImmutable::parse($month)->startOfMonth();
        $types = self::types();
        $services = self::services();
        $snackType = config('billing.snack_type');

        // Conteo por fila y día
        $counts = [];
        foreach (self::rows() as $r) {
            $counts["{$r['type']}.{$r['service']}"] = [];
        }
        $unassigned = [];
        $notReceived = 0;
        $pending = [];

        foreach ($this->reports->activeDietsByDay($start, receivedOnly: true) as $day) {
            $d = (int) $day['date']->format('j');
            // Servicios facturables sin recepción (fraccionada no se factura)
            $notReceived += count(array_filter($day['withheld'], fn ($meal) => isset($services[$meal])));

            // SCRUM-14: qué dieta y qué falta, para explicarlo en pantalla
            foreach ($day['pending'] ?? [] as $p) {
                $billable = array_values(array_filter($p['withheld'], fn ($meal) => isset($services[$meal])));
                if ($billable !== []) {
                    $pending[] = ['date' => $day['date'], 'diet' => $p['diet'],
                        'withheld' => $billable, 'received' => $p['received']];
                }
            }

            foreach ($day['diets'] as ['diet' => $diet, 'version' => $version, 'meals' => $meals]) {
                $type = $version->billing_type;

                if (! $type || ! isset($types[$type])) {
                    $unassigned[$diet->id] ??= ['diet' => $diet, 'days' => 0];
                    $unassigned[$diet->id]['days']++;

                    continue;
                }

                foreach ($meals as $meal) {
                    if (! isset($services[$meal])) {
                        continue; // fraccionada: no se factura
                    }

                    $rowType = in_array($meal, $types[$type]['services'], true) ? $type : $snackType;
                    $key = "{$rowType}.{$meal}";

                    if (! array_key_exists($key, $counts)) {
                        continue; // servicio sin fila ni en Entrecomidas (p. ej. desayuno de un tipo sin desayuno)
                    }

                    $counts[$key][$d] = ($counts[$key][$d] ?? 0) + 1;
                }
            }
        }

        // Valorización con el precio vigente cada día
        $prices = ServicePrice::orderBy('valid_from')->get()->groupBy(fn ($p) => "{$p->billing_type}.{$p->service}");
        $monthEnd = $start->endOfMonth();
        $rows = [];
        $serviceTotals = array_fill_keys(array_keys($services), 0);
        $missingPrices = [];

        foreach (self::rows() as $r) {
            $key = "{$r['type']}.{$r['service']}";
            $rowPrices = $prices->get($key, collect());
            $value = 0.0;
            $missingTrays = 0;

            foreach ($counts[$key] as $d => $n) {
                $price = $rowPrices->last(fn ($p) => $p->valid_from->lte($start->setDay($d)));
                $price ? $value += $n * (float) $price->price : $missingTrays += $n;
            }

            $current = $rowPrices->last(fn ($p) => $p->valid_from->lte($monthEnd));
            $changes = $rowPrices
                ->filter(fn ($p) => $p->valid_from->between($start->addDay(), $monthEnd))
                ->map(fn ($p) => ['from' => $p->valid_from->format('d/m/Y'), 'price' => (float) $p->price])
                ->values()
                ->all();

            $count = array_sum($counts[$key]);
            $serviceTotals[$r['service']] += $count;

            if ($missingTrays > 0) {
                $missingPrices[] = $key;
            }

            $rows[] = [
                'type' => $r['type'],
                'service' => $r['service'],
                'days' => $counts[$key],
                'count' => $count,
                'price' => $current ? (float) $current->price : null,
                'priceChanges' => $changes,
                'value' => $value,
                'missingPriceTrays' => $missingTrays,
            ];
        }

        return [
            'month' => $start,
            'daysInMonth' => $start->daysInMonth,
            'daysCounted' => $start->isAfter(CarbonImmutable::now()) ? 0 : (int) min($monthEnd, CarbonImmutable::now())->format('j'),
            'rows' => $rows,
            'serviceTotals' => $serviceTotals,
            'total' => array_sum(array_column($rows, 'value')),
            'totalTrays' => array_sum($serviceTotals),
            'unassigned' => collect($unassigned)->values(),
            'missingPrices' => $missingPrices,
            'notReceived' => $notReceived,
            // Detalle de lo sin facturar, lo más reciente primero
            'pending' => collect($pending)->sortByDesc(fn ($p) => $p['date']->format('Ymd').str_pad((string) $p['diet']->habitation, 8, '0', STR_PAD_LEFT))->values(),
        ];
    }

    /** Totales de cada mes del año hasta $until (incluido): 'YYYY-MM' => valor */
    public function yearTotals(CarbonInterface $until): array
    {
        $until = CarbonImmutable::parse($until)->startOfMonth();
        $totals = [];

        for ($m = $until->startOfYear(); $m <= $until; $m = $m->addMonth()) {
            $totals[$m->format('Y-m')] = $this->monthly($m)['total'];
        }

        return $totals;
    }
}
