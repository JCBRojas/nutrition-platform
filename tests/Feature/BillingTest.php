<?php

namespace Tests\Feature;

use App\Exports\BillingWorkbook;
use App\Models\Diet;
use App\Models\DietHistory;
use App\Models\User;
use App\Services\BillingService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private User $nutritionist;

    private BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        // Estas pruebas cubren el conteo sin exigir despacho; el despacho se prueba en DispatchFlowTest
        config(['dispatch.required_from' => null]);
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->nutritionist = User::role('nutricionista')->firstOrFail();
        $this->billing = app(BillingService::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));
    }

    private function diet(string $at, array $timeFood, ?string $type): Diet
    {
        $diet = Diet::create(['habitation' => '101', 'name_patient' => 'Paciente', 'status' => true]);
        $diet->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        $h = DietHistory::create([
            'diet_id' => $diet->id, 'updated_by' => $this->nutritionist->id, 'version' => 1,
            'timeFood' => $timeFood, 'billing_type' => $type, 'modify' => 'no',
        ]);
        $h->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();

        return $diet;
    }

    private function row(array $monthly, string $type, string $service): array
    {
        return collect($monthly['rows'])->first(fn ($r) => $r['type'] === $type && $r['service'] === $service);
    }

    public function test_catalog_matches_the_hospital_format(): void
    {
        $this->assertCount(17, BillingService::types());
        $this->assertCount(81, BillingService::rows());
        $this->assertCount(16, BillingService::selectableTypes());
        $this->assertArrayNotHasKey('entrecomidas', BillingService::selectableTypes());
        $this->assertArrayNotHasKey('fraccionada', BillingService::services());
    }

    public function test_counts_by_type_snacks_go_to_entrecomidas_and_fraccionada_is_not_billed(): void
    {
        // Normal 1–15 oct: desayuno, cena (=COMIDA), merienda (sin fila en Normal) y fraccionada
        $this->diet('2026-10-01 08:00:00', ['desayuno', 'cena', 'merienda', 'fraccionada'], 'normal');
        // Hipoglúcida desde el 11: sí factura merienda en su propia fila
        $this->diet('2026-10-11 07:00:00', ['merienda'], 'hipoglucida');

        $m = $this->billing->monthly(CarbonImmutable::parse('2026-10-01'));

        $this->assertSame(15, $this->row($m, 'normal', 'desayuno')['count']);
        $this->assertSame(15, $this->row($m, 'normal', 'cena')['count']);
        $this->assertSame(15, $this->row($m, 'entrecomidas', 'merienda')['count']);
        $this->assertSame(5, $this->row($m, 'hipoglucida', 'merienda')['count']);
        $this->assertSame([11 => 1, 12 => 1, 13 => 1, 14 => 1, 15 => 1], $this->row($m, 'hipoglucida', 'merienda')['days']);
        $this->assertSame(50, $m['totalTrays']); // fraccionada excluida
    }

    public function test_diets_without_billing_type_are_reported_not_billed(): void
    {
        $this->diet('2026-10-01 08:00:00', ['desayuno'], null);

        $m = $this->billing->monthly(CarbonImmutable::parse('2026-10-01'));

        $this->assertSame(0, $m['totalTrays']);
        $this->assertSame(15, $m['unassigned']->first()['days']);
    }

    public function test_value_uses_the_price_effective_each_day(): void
    {
        $this->diet('2026-10-01 08:00:00', ['almuerzo'], 'normal');
        $this->billing->setPrice('normal', 'almuerzo', 17000, CarbonImmutable::parse('2026-01-01'), null);
        $this->billing->setPrice('normal', 'almuerzo', 18000, CarbonImmutable::parse('2026-10-11'), null);

        $m = $this->billing->monthly(CarbonImmutable::parse('2026-10-01'));
        $row = $this->row($m, 'normal', 'almuerzo');

        $this->assertEquals(10 * 17000 + 5 * 18000, $row['value']);
        $this->assertEquals(18000, $row['price']);
        $this->assertCount(1, $row['priceChanges']);
        $this->assertSame([], $m['missingPrices']);
    }

    public function test_workbook_reproduces_the_format(): void
    {
        $this->diet('2026-10-01 08:00:00', ['desayuno', 'merienda'], 'normal');
        $this->billing->setPrice('normal', 'desayuno', 13708, CarbonImmutable::parse('2026-01-01'), null);
        $this->billing->setPrice('entrecomidas', 'merienda', 9501, CarbonImmutable::parse('2026-01-01'), null);

        $monthly = $this->billing->monthly(CarbonImmutable::parse('2026-10-01'));
        $workbook = new BillingWorkbook($monthly, $this->billing->yearTotals(CarbonImmutable::parse('2026-10-01')), 'UCI');
        $book = $workbook->build();

        $this->assertSame('Facturación 1 al 15 de Octubre 2026 UCI.xlsx', $workbook->fileName());
        $this->assertSame(['OCTUBRE 2026', 'FACTURACIÓN MES A MES '], $book->getSheetNames());

        $s = $book->getSheet(0);
        $this->assertSame('TIPO DE DIETA', $s->getCell('A1')->getValue());
        $this->assertSame('VALOR UND', $s->getCell('D1')->getValue());
        $this->assertSame(31, $s->getCell('AI2')->getValue());
        $this->assertSame('TOTAL SERVICIOS', $s->getCell('AJ1')->getValue());
        $this->assertSame('VALOR TOTAL', $s->getCell('AK1')->getValue());
        $this->assertContains('A3:A5', array_keys($s->getMergeCells())); // NORMAL: 3 servicios

        // Fila 3 = NORMAL / DESAYUNO
        $this->assertSame('NORMAL', $s->getCell('A3')->getValue());
        $this->assertSame('DESAYUNO', $s->getCell('B3')->getValue());
        $this->assertEquals(13708, $s->getCell('D3')->getValue());
        $this->assertSame('=SUM(E3:AI3)', $s->getCell('AJ3')->getValue());
        $this->assertSame('=D3*AJ3', $s->getCell('AK3')->getValue());
        $this->assertEquals(15, $s->getCell('AJ3')->getCalculatedValue());
        $this->assertEquals(15 * 13708, $s->getCell('AK3')->getCalculatedValue());

        // Total del mes = desayunos + meriendas en Entrecomidas
        $totalRow = collect(range(80, 95))->first(fn ($r) => $s->getCell("A{$r}")->getValue() === 'TOTAL MES');
        $this->assertEquals(15 * 13708 + 15 * 9501, $s->getCell("AK{$totalRow}")->getCalculatedValue());

        // Resumen lateral y hoja anual
        $this->assertSame('DIETAS OCTUBRE UCI', $s->getCell('AM3')->getValue());
        $this->assertEquals(30, $s->getCell('AN11')->getCalculatedValue()); // TOTAL servicios
        $year = $book->getSheet(1);
        $this->assertSame("=+'OCTUBRE 2026'!AK{$totalRow}", $year->getCell('C12')->getValue());
        $this->assertEquals(15 * 13708 + 15 * 9501, $year->getCell('C15')->getCalculatedValue());
    }

    public function test_download_requires_manage_prices(): void
    {
        $jefe = User::role('jefe_nutricion')->firstOrFail();

        $response = $this->actingAs($jefe)->get(route('billing.excel', ['mes' => '2026-10']));
        $response->assertOk();
        $this->assertStringContainsString('Facturaci', $response->headers->get('content-disposition'));

        $this->flushSession();
        $this->actingAs($this->nutritionist)->get(route('billing.excel'))->assertForbidden();
    }

    public function test_diet_form_requires_billing_type(): void
    {
        $this->actingAs($this->nutritionist)
            ->post(route('dietas.create'), ['habitation' => '102', 'name_patient' => 'Nuevo', 'timeFood' => ['desayuno']])
            ->assertSessionHasErrors('billing_type');

        $this->actingAs($this->nutritionist)
            ->post(route('dietas.create'), ['habitation' => '102', 'name_patient' => 'Nuevo', 'timeFood' => ['desayuno'], 'billing_type' => 'hiperproteica'])
            ->assertSessionHasNoErrors();

        $this->assertSame('hiperproteica', Diet::where('name_patient', 'Nuevo')->first()->currentVersion->billing_type);
    }
}
