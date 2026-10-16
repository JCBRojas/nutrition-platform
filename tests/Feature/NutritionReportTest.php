<?php

namespace Tests\Feature;

use App\Exports\MonthlySummaryExport;
use App\Models\Diet;
use App\Models\DietHistory;
use App\Models\User;
use App\Services\Reports\DietReportService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class NutritionReportTest extends TestCase
{
    use RefreshDatabase;

    private User $nutritionist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->nutritionist = User::role('nutricionista')->first();

        // "Hoy" = 15/oct/2026 12:00 (Bogotá)
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));
    }

    private function diet(string $created, array $timeFood, bool $status = true): Diet
    {
        $diet = Diet::create(['habitation' => '10'.Diet::count(), 'name_patient' => 'P'.Diet::count(), 'status' => $status]);
        $diet->forceFill(['created_at' => $created, 'updated_at' => $created])->save();
        $this->version($diet, 1, $created, $timeFood);

        return $diet;
    }

    private function version(Diet $diet, int $n, string $at, array $timeFood, ?string $changes = null): void
    {
        $h = DietHistory::create([
            'diet_id' => $diet->id, 'updated_by' => $this->nutritionist->id, 'version' => $n,
            'timeFood' => $timeFood, 'changes' => $changes, 'modify' => 'no',
        ]);
        $h->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }

    public function test_monthly_census_counts_last_version_times_active_days(): void
    {
        // Viene de septiembre: desayuno + almuerzo los días 1–15 → 15 + 15
        $this->diet('2026-09-20 08:00:00', ['desayuno', 'almuerzo']);

        // Creada el 10 con cena; el 12 cambia a cena + media_manana (clave del formulario)
        $changed = $this->diet('2026-10-10 15:00:00', ['cena']);
        $this->version($changed, 2, '2026-10-12 09:00:00', ['cena', 'media_manana']);
        // Se usa solo la última versión (cena + media mañana) en los días 10–15 → 6 + 6

        // Cancelada el 5 a las 10:00 → cuenta días 1–4 (algo)
        $cancelled = $this->diet('2026-09-01 08:00:00', ['algo'], status: false);
        $this->version($cancelled, 2, '2026-10-05 10:00:00', ['algo'], 'cancelada');
        $cancelled->forceFill(['updated_at' => '2026-10-05 10:00:00'])->saveQuietly(); // como DietService::cancelDiet

        // Creada después del periodo: no cuenta
        $this->diet('2026-11-02 08:00:00', ['desayuno']);

        $census = app(DietReportService::class)->monthlyCensus(CarbonImmutable::parse('2026-10-01'));

        $this->assertSame(15, $census['daysCounted']);
        $this->assertSame(15, $census['totals']['desayuno']);
        $this->assertSame(15, $census['totals']['almuerzo']);
        $this->assertSame(6, $census['totals']['cena']);
        $this->assertSame(6, $census['totals']['mediamanana']);
        $this->assertSame(4, $census['totals']['algo']);
        $this->assertSame(46, $census['totalTrays']);
        $this->assertSame(3, $census['perDiet']->count());
        $this->assertSame(2, $census['days'][0]['diets']);  // día 1: base + la que luego se cancela
        $this->assertSame(2, $census['days'][14]['diets']); // día 15: base + la creada el 10
    }

    public function test_intermediate_versions_are_ignored(): void
    {
        $diet = $this->diet('2026-10-01 08:00:00', ['desayuno']);
        $this->version($diet, 2, '2026-10-05 08:00:00', ['desayuno', 'cena']);
        $this->version($diet, 3, '2026-10-12 08:00:00', ['almuerzo']);

        $census = app(DietReportService::class)->monthlyCensus(CarbonImmutable::parse('2026-10-01'));

        // Solo la v3 (almuerzo) × 15 días activos
        $this->assertSame(15, $census['totals']['almuerzo']);
        $this->assertSame(0, $census['totals']['desayuno']);
        $this->assertSame(0, $census['totals']['cena']);
        $this->assertSame(15, $census['totalTrays']);
    }

    public function test_past_month_uses_last_version_up_to_month_end(): void
    {
        $diet = $this->diet('2026-09-21 08:00:00', ['desayuno']);   // septiembre: días 21–30 = 10
        $this->version($diet, 2, '2026-10-03 08:00:00', ['cena']); // posterior a septiembre

        $september = app(DietReportService::class)->monthlyCensus(CarbonImmutable::parse('2026-09-01'));

        $this->assertSame(10, $september['totals']['desayuno']);
        $this->assertSame(0, $september['totals']['cena']);
    }

    public function test_legacy_cancellation_without_version_uses_updated_at(): void
    {
        $diet = $this->diet('2026-09-01 08:00:00', ['desayuno'], status: false);
        $diet->forceFill(['updated_at' => '2026-10-03 18:00:00'])->saveQuietly();

        $census = app(DietReportService::class)->monthlyCensus(CarbonImmutable::parse('2026-10-01'));

        $this->assertSame(2, $census['totals']['desayuno']); // 1 y 2 de octubre
    }

    public function test_report_page_renders_for_both_roles(): void
    {
        $this->diet('2026-10-01 08:00:00', ['desayuno']);

        $this->actingAs($this->nutritionist)->get(route('diet.reports', ['mes' => '2026-10']))
            ->assertOk()
            ->assertSee('Descargar Excel del mes')
            ->assertSee(route('reports.monthly.excel', ['mes' => '2026-10']), false);

        $this->flushSession();
        $this->actingAs(User::role('despachador')->first())->get(route('entrega.index'))
            ->assertOk()
            ->assertSee('Producción diaria');
    }

    public function test_excel_download_contains_month_totals(): void
    {
        Excel::fake();
        $this->diet('2026-10-01 08:00:00', ['desayuno', 'cena']);

        $this->actingAs($this->nutritionist)
            ->get(route('reports.monthly.excel', ['mes' => '2026-10']))
            ->assertOk();

        Excel::assertDownloaded('reporte-nutricional_2026-10_20261015_1200.xlsx', function (MonthlySummaryExport $export) {
            [$summary, $daily, $perDiet] = $export->sheets();

            return $summary->title() === 'Resumen'
                && collect($summary->array())->contains(fn ($row) => ($row[0] ?? null) === 'Total bandejas' && $row[1] === 30)
                && count($daily->array()) === 16      // 15 días + fila total
                && count($perDiet->array()) === 1;
        });
    }

    public function test_excel_file_is_a_real_xlsx(): void
    {
        $this->diet('2026-10-01 08:00:00', ['desayuno']);

        $response = $this->actingAs($this->nutritionist)->get(route('reports.monthly.excel', ['mes' => '2026-10']));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('reporte-nutricional_2026-10', $response->headers->get('content-disposition'));
    }

    public function test_invalid_or_future_month_is_rejected(): void
    {
        $this->actingAs($this->nutritionist)->get(route('reports.monthly.excel', ['mes' => '2026-13']))
            ->assertSessionHasErrors('mes');
        $this->actingAs($this->nutritionist)->get(route('reports.monthly.excel', ['mes' => '2027-01']))
            ->assertSessionHasErrors('mes');
    }

    public function test_users_without_report_permission_cannot_download(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.monthly.excel'))->assertForbidden();
    }
}
