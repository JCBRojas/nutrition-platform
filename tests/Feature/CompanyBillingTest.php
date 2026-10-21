<?php

namespace Tests\Feature;

use App\Exports\BillingWorkbook;
use App\Livewire\PricesManager;
use App\Models\Company;
use App\Models\ServicePrice;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DietService;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/** NUT-RN-003 §6 (SCRUM-10): precios y facturación por empresa */
class CompanyBillingTest extends TestCase
{
    use RefreshDatabase;

    private User $jefe;

    private Company $a;

    private Company $b;

    private BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activatePremium();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        // Cuenta lo programado: la regla de lo recibido se prueba en DispatchFlowTest
        config(['dispatch.required_from' => null, 'dispatch.completion_required_from' => null]);
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));

        $this->jefe = User::role('jefe_nutricion')->firstOrFail();
        $this->a = Company::default();
        $this->a->update(['name' => 'Clínica Alfa']);
        $this->b = Company::create(['name' => 'Clínica Beta', 'nit' => '860002964-4', 'contact_name' => 'B',
            'contact_phone' => '+573001234567', 'contact_email' => 'b@example.com']);
        $this->billing = app(BillingService::class);

        // Una dieta de almuerzo en cada empresa desde el 1 de octubre (sin usuario: sin filtro)
        foreach (['101' => $this->a, '202' => $this->b] as $room => $company) {
            $diet = app(DietService::class)->createDiet(
                ['company_id' => $company->id, 'habitation' => $room, 'name_patient' => "Paciente {$room}",
                    'timeFood' => ['almuerzo'], 'billing_type' => 'normal'],
                $this->jefe->id,
            );
            $diet->forceFill(['created_at' => '2026-10-01 08:00:00'])->saveQuietly();
            $diet->history()->update(['created_at' => '2026-10-01 08:00:00']);
        }
    }

    private function useCompany(Company $company): void
    {
        $this->actingAs($this->jefe);
        app(CurrentCompany::class)->switch($company->id);
    }

    private function almuerzo(array $monthly): array
    {
        return collect($monthly['rows'])->first(fn ($r) => $r['type'] === 'normal' && $r['service'] === 'almuerzo');
    }

    public function test_same_diet_type_can_have_different_prices_per_company(): void
    {
        $this->useCompany($this->a);
        $this->billing->setPrice('normal', 'almuerzo', 17000, CarbonImmutable::parse('2026-01-01'), $this->jefe->id);
        $this->useCompany($this->b);
        $this->billing->setPrice('normal', 'almuerzo', 21000, CarbonImmutable::parse('2026-01-01'), $this->jefe->id);

        // Misma vigencia en dos empresas: dos filas, no se sobrescriben
        $this->assertSame(2, ServicePrice::withoutGlobalScopes()->count());
        $this->assertEquals(21000, $this->billing->pricesAt(now())->get('normal.almuerzo')->price);

        $this->useCompany($this->a);
        $this->assertEquals(17000, $this->billing->pricesAt(now())->get('normal.almuerzo')->price);
        $this->assertSame(1, ServicePrice::count());
    }

    public function test_monthly_billing_only_counts_diets_and_prices_of_the_selected_company(): void
    {
        $this->useCompany($this->a);
        $this->billing->setPrice('normal', 'almuerzo', 17000, CarbonImmutable::parse('2026-01-01'), null);
        $this->useCompany($this->b);
        $this->billing->setPrice('normal', 'almuerzo', 21000, CarbonImmutable::parse('2026-01-01'), null);

        $b = $this->almuerzo($this->billing->monthly(CarbonImmutable::parse('2026-10-01')));
        $this->assertSame(15, $b['count']);
        $this->assertEquals(15 * 21000, $b['value']);

        $this->useCompany($this->a);
        $a = $this->almuerzo($this->billing->monthly(CarbonImmutable::parse('2026-10-01')));
        $this->assertSame(15, $a['count']);
        $this->assertEquals(15 * 17000, $a['value']);
    }

    public function test_company_without_prices_reports_missing_prices(): void
    {
        $this->useCompany($this->a);
        $this->billing->setPrice('normal', 'almuerzo', 17000, CarbonImmutable::parse('2026-01-01'), null);

        $this->useCompany($this->b);
        $m = $this->billing->monthly(CarbonImmutable::parse('2026-10-01'));

        $this->assertContains('normal.almuerzo', $m['missingPrices']);
        $this->assertEquals(0, $m['total']);
    }

    public function test_prices_screen_saves_in_the_selected_company(): void
    {
        $this->useCompany($this->b);

        Livewire::test(PricesManager::class)
            ->assertSee('Clínica Beta')
            ->call('edit', 'normal', 'almuerzo')
            ->set('price', '21.000')
            ->call('save')
            ->assertHasNoErrors();

        $price = ServicePrice::withoutGlobalScopes()->sole();
        $this->assertSame($this->b->id, $price->company_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'price_updated']);
    }

    public function test_price_company_cannot_change(): void
    {
        $this->useCompany($this->a);
        $price = $this->billing->setPrice('normal', 'almuerzo', 17000, CarbonImmutable::parse('2026-01-01'), null);

        $this->expectException(LogicException::class);
        $price->update(['company_id' => $this->b->id]);
    }

    public function test_billing_excel_carries_the_company_in_file_and_sheet(): void
    {
        $this->useCompany($this->b);

        $this->get(route('billing.excel', ['mes' => '2026-10']))
            ->assertOk()
            ->assertDownload('Facturacion 1 al 15 de Octubre 2026 UCI Clinica Beta.xlsx'); // respaldo ASCII del nombre

        $workbook = new BillingWorkbook($this->billing->monthly(CarbonImmutable::parse('2026-10-01')), [], 'UCI', 'Clínica: Beta / Norte [UCI] de prueba larga');
        $this->assertSame('OCTUBRE 2026 Clínica Beta Norte', $workbook->sheetTitle());
        $this->assertLessThanOrEqual(31, mb_strlen($workbook->sheetTitle()));
    }
}
