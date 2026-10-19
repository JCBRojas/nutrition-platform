<?php

namespace Tests\Feature;

use App\Models\Diet;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DietHistoryService;
use App\Services\DietService;
use App\Services\Reports\DietReportService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinishDietTest extends TestCase
{
    use RefreshDatabase;

    private User $nutritionist;

    private Diet $diet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activatePremium(); // usa pantallas del plan Premium (precios, despachos del día, historial)
        // Estas pruebas cubren el conteo sin exigir despacho; el despacho se prueba en DispatchFlowTest
        config(['dispatch.required_from' => null]);
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->nutritionist = User::role('nutricionista')->firstOrFail();

        $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00:00'));
        $this->diet = app(DietService::class)->createDiet(
            ['habitation' => '305', 'name_patient' => "Ana O'Neil", 'timeFood' => ['desayuno', 'almuerzo'], 'billing_type' => 'normal'],
            $this->nutritionist->id,
        );
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));
    }

    private function finish()
    {
        return $this->actingAs($this->nutritionist)->patch(route('diets.finish', $this->diet));
    }

    public function test_finish_keeps_the_record_and_changes_status(): void
    {
        $this->finish()->assertRedirect()->assertSessionHas('success');

        $diet = $this->diet->fresh();
        $this->assertSame(Diet::FINISHED, $diet->status);
        $this->assertFalse($diet->isActive());
        $this->assertSame(1, Diet::count()); // no se elimina

        $last = $diet->currentVersion;
        $this->assertSame(2, $last->version);
        $this->assertSame('finalizada', $last->changes);
        $this->assertSame(['desayuno', 'almuerzo'], $last->timeFood); // copia de la versión anterior
        $this->assertSame('normal', $last->billing_type);
    }

    public function test_finished_diet_leaves_active_tab_and_appears_in_finished_tab(): void
    {
        $this->finish();

        $html = $this->actingAs($this->nutritionist)->get(route('dashboard.nutrition'))->assertOk()->getContent();

        $this->assertStringContainsString('Activas (0)', $html);
        $this->assertStringContainsString('Finalizadas (1)', $html);
        $this->assertStringNotContainsString("startClose({$this->diet->id}, 'finish')", html_entity_decode($html, ENT_QUOTES)); // sin botón de alta
        $this->assertStringContainsString("Ana O'Neil", html_entity_decode($html, ENT_QUOTES));
    }

    public function test_active_row_has_icon_buttons_with_accessible_names(): void
    {
        $html = $this->actingAs($this->nutritionist)->get(route('dashboard.nutrition'))->assertOk()->getContent();

        $this->assertStringContainsString("startClose({$this->diet->id}, 'finish')", html_entity_decode($html, ENT_QUOTES));
        foreach (['Ver historial de', 'Editar dieta de', 'Finalizar dieta de', 'Cancelar dieta de'] as $label) {
            $this->assertStringContainsString('aria-label="'.$label, $html);
        }
    }

    public function test_cannot_finish_or_cancel_a_closed_diet(): void
    {
        $this->finish();

        $this->finish()->assertSessionHasErrors('diet');
        $this->actingAs($this->nutritionist)->patch(route('diets.canceled', $this->diet))->assertSessionHasErrors('diet');

        $this->assertSame(2, $this->diet->history()->count());
        $this->assertSame(Diet::FINISHED, $this->diet->fresh()->status);
    }

    public function test_dispatcher_cannot_finish(): void
    {
        $this->actingAs(User::role('despachador')->first())
            ->patch(route('diets.finish', $this->diet))
            ->assertForbidden();
    }

    public function test_report_and_billing_stop_counting_after_discharge(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-10 15:00:00'));
        $this->finish(); // alta el 10 a las 15:00 → días 1–9 completos + desayuno y almuerzo del 10
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));

        $census = app(DietReportService::class)->monthlyCensus(CarbonImmutable::parse('2026-10-01'));
        $this->assertSame(10, $census['totals']['desayuno']);
        $this->assertSame(10, $census['totals']['almuerzo']);
        $this->assertSame(0, app(DietReportService::class)->currentSnapshot()['totalActiveDiets']);

        $billing = app(BillingService::class)->monthly(CarbonImmutable::parse('2026-10-01'));
        $row = collect($billing['rows'])->first(fn ($r) => $r['type'] === 'normal' && $r['service'] === 'almuerzo');
        $this->assertSame(10, $row['count']);
        $this->assertSame(1, $row['days'][10]);
    }

    public function test_history_marks_the_discharge(): void
    {
        $this->finish();

        $timeline = app(DietHistoryService::class)->timeline($this->diet->fresh());

        $this->assertSame('finalizacion', $timeline->first()['type']);
        $this->actingAs($this->nutritionist)->get(route('diets.history', ['dieta' => $this->diet->id]))
            ->assertOk()->assertSee('Alta (finalizada)');
    }
}
