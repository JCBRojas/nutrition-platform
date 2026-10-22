<?php

namespace Tests\Feature;

use App\Livewire\DietsManager;
use App\Livewire\NutritionDiets;
use App\Models\Diet;
use App\Models\DietDay;
use App\Models\DietDispatch;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DietDayService;
use App\Services\DietService;
use App\Services\DispatchService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Cierre diario (Completada), «Continuar dieta» y facturación solo de días completados.
 */
class DietDayCompletionTest extends TestCase
{
    use RefreshDatabase;

    private User $nutritionist;

    private User $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->nutritionist = User::role('nutricionista')->firstOrFail();
        $this->dispatcher = User::role('despachador')->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-10-20 05:00:00'));
        config(['dispatch.required_from' => '2026-10-20', 'dispatch.completion_required_from' => '2026-10-20']);
    }

    private function diet(string $room, array $timeFood = ['desayuno', 'almuerzo']): Diet
    {
        return app(DietService::class)->createDiet(
            ['habitation' => $room, 'name_patient' => "Paciente {$room}", 'timeFood' => $timeFood, 'billing_type' => 'normal'],
            $this->nutritionist->id,
        );
    }

    private function serve(Diet $diet, string $meal): array
    {
        app(DispatchService::class)->dispatch([$diet->id], $meal, $this->dispatcher->id);

        return app(DispatchService::class)->confirmReceipt(
            DietDispatch::where('diet_id', $diet->id)->where('meal', $meal)->pendingReceipt()->pluck('id')->all(),
            $this->nutritionist->id,
        );
    }

    private function billed(string $service): int
    {
        $m = app(BillingService::class)->monthly(CarbonImmutable::parse('2026-10-01'));

        return collect($m['rows'])->first(fn ($r) => $r['type'] === 'normal' && $r['service'] === $service)['count'];
    }

    public function test_day_is_completed_when_every_meal_is_received(): void
    {
        $diet = $this->diet('101');

        $this->assertSame([], $this->serve($diet, 'desayuno')['completed']);
        $this->assertSame(Diet::ACTIVE, $diet->fresh()->status);

        $this->assertSame(['101'], $this->serve($diet, 'almuerzo')['completed']);

        $diet->refresh();
        $this->assertSame(Diet::COMPLETED, $diet->status);
        $this->assertTrue($diet->isActive()); // sigue en curso
        $this->assertSame('completada', $diet->currentVersion->changes);
        $this->assertSame('no', $diet->currentVersion->modify); // no alerta al despachador
        $this->assertNotNull(DietDay::where('diet_id', $diet->id)->value('completed_at'));
    }

    public function test_creation_day_only_requires_meals_served_after_creation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00'));
        $diet = $this->diet('102', ['desayuno', 'almuerzo', 'fraccionada']);

        $this->assertSame(['almuerzo', 'fraccionada'], app(DietDayService::class)->requiredMeals($diet, now()));

        $this->serve($diet, 'almuerzo');
        $this->assertSame(['102'], $this->serve($diet, 'fraccionada')['completed']);
    }

    public function test_only_completed_days_are_billed(): void
    {
        $diet = $this->diet('103');
        $this->serve($diet, 'desayuno');

        // Desayuno recibido, pero el día no está completo: no se factura nada
        $this->assertSame(0, $this->billed('desayuno'));
        $this->assertSame(2, app(BillingService::class)->monthly(CarbonImmutable::parse('2026-10-01'))['notReceived']);

        $this->serve($diet, 'almuerzo');

        $this->assertSame(1, $this->billed('desayuno'));
        $this->assertSame(1, $this->billed('almuerzo'));
        $this->assertSame(0, app(BillingService::class)->monthly(CarbonImmutable::parse('2026-10-01'))['notReceived']);
    }

    public function test_billing_explains_what_is_missing_for_an_incomplete_day(): void
    {
        // SCRUM-14: desayuno despachado y recibido, almuerzo pendiente → el día no se factura y se explica por qué
        $diet = $this->diet('999', ['desayuno', 'almuerzo']);
        $this->serve($diet, 'desayuno');

        $pending = app(BillingService::class)->monthly(CarbonImmutable::parse('2026-10-01'))['pending'];
        $this->assertCount(1, $pending);
        $this->assertSame('day_incomplete', $pending[0]['reason']);
        $this->assertSame(['desayuno'], $pending[0]['received']);
        $this->assertSame(['almuerzo'], $pending[0]['missing']);

        $this->activatePremium();
        $this->actingAs(User::role('jefe_nutricion')->firstOrFail())
            ->get(route('prices.index', ['mes' => '2026-10']))
            ->assertOk()
            ->assertSee('2 servicio(s) sin facturar', false)
            ->assertSee('Hab. 999')
            ->assertSeeInOrder(['recibido Desayuno;', 'falta recibir Almuerzo.'])
            ->assertSee('Se factura cuando el día quede completado.');

        // Al completar el día ya no hay pendientes y se factura aunque no haya precio (cantidad con $ 0)
        $this->serve($diet, 'almuerzo');
        $monthly = app(BillingService::class)->monthly(CarbonImmutable::parse('2026-10-01'));
        $this->assertTrue($monthly['pending']->isEmpty());
        $row = collect($monthly['rows'])->first(fn ($r) => $r['type'] === 'normal' && $r['service'] === 'desayuno');
        $this->assertSame(1, $row['count']);
        $this->assertSame(1, $row['missingPriceTrays']);
    }

    public function test_days_before_the_completion_cutoff_keep_the_received_rule(): void
    {
        config(['dispatch.completion_required_from' => '2026-10-21']);
        $diet = $this->diet('104');
        $this->serve($diet, 'desayuno');

        $this->assertSame(1, $this->billed('desayuno'));
    }

    public function test_next_day_needs_continue_before_dispatching(): void
    {
        $diet = $this->diet('105', ['desayuno']);
        $this->serve($diet, 'desayuno');
        $this->assertSame(Diet::COMPLETED, $diet->fresh()->status);

        // Al día siguiente sin «Continuar dieta»: cocina no puede despachar
        $this->travelTo(CarbonImmutable::parse('2026-10-21 06:00:00'));
        $result = app(DispatchService::class)->dispatch([$diet->id], 'desayuno', $this->dispatcher->id);
        $this->assertSame([], $result['dispatched']);
        $this->assertStringContainsString('Continuar dieta', $result['skipped']['105']);
        $this->assertSame(0, DietDispatch::whereDate('service_date', '2026-10-21')->count());
    }

    public function test_continue_authorizes_tomorrow_and_restarts_the_day(): void
    {
        $diet = $this->diet('106', ['desayuno']);
        $this->serve($diet, 'desayuno');

        Livewire::actingAs($this->nutritionist)->test(NutritionDiets::class)
            ->assertSee('Completada')
            ->call('continueDiet', $diet->id)
            ->assertSet('noticeError', false)
            ->assertSee('Continúa mañana');

        $this->assertSame('continuada', $diet->fresh()->currentVersion->changes);
        $this->assertSame(Diet::COMPLETED, $diet->fresh()->status); // hoy sigue completada

        // Continuar dos veces el mismo día no duplica la autorización
        Livewire::actingAs($this->nutritionist)->test(NutritionDiets::class)
            ->call('continueDiet', $diet->id)
            ->assertSet('noticeError', true);
        $this->assertSame(1, DietDay::whereDate('service_date', '2026-10-21')->count());

        // Día siguiente: inicia el día y se despacha
        $this->travelTo(CarbonImmutable::parse('2026-10-21 00:01:00'));
        $this->artisan('diets:start-day')->assertSuccessful();
        $this->assertSame(Diet::ACTIVE, $diet->fresh()->status);

        $this->travelTo(CarbonImmutable::parse('2026-10-21 06:40:00'));
        $this->assertSame(['106'], app(DispatchService::class)->dispatch([$diet->id], 'desayuno', $this->dispatcher->id)['dispatched']);
    }

    public function test_dispatcher_sees_why_an_unauthorized_diet_cannot_be_dispatched(): void
    {
        $diet = $this->diet('107', ['desayuno']);
        $this->travelTo(CarbonImmutable::parse('2026-10-21 06:40:00'));

        Livewire::actingAs($this->dispatcher)->test(DietsManager::class)
            ->assertSee('Paciente 107')
            ->assertSee('Sin autorización para hoy');
    }

    public function test_nursing_can_continue_and_dispatcher_cannot(): void
    {
        $nursing = Role::firstOrCreate(['name' => 'enfermeria', 'guard_name' => 'web']);
        $nursing->syncPermissions(['viewActiveDiets', 'continueDiets']);
        $nurse = User::factory()->create();
        $nurse->assignRole($nursing);
        $diet = $this->diet('108');

        Livewire::actingAs($nurse)->test(NutritionDiets::class)
            ->assertSee('Continuar dieta')
            ->assertDontSee('Registrar Dieta')
            ->call('continueDiet', $diet->id)
            ->assertSet('noticeError', false);

        $this->assertTrue(app(DietDayService::class)->isAuthorized($diet, now()->addDay()));

        $this->assertFalse($this->dispatcher->can('continueDiets'));
    }

    public function test_discharge_completes_the_day_when_earlier_meals_were_received(): void
    {
        $diet = $this->diet('109', ['desayuno', 'almuerzo', 'cena']);
        $this->serve($diet, 'desayuno');

        $this->travelTo(CarbonImmutable::parse('2026-10-20 13:00:00'));
        $this->serve($diet, 'almuerzo');
        $this->assertNull(DietDay::where('diet_id', $diet->id)->value('completed_at')); // falta la cena

        // Alta a la 1 p. m.: la cena no se exige; el día queda completado y se factura
        app(DietService::class)->finishDiet($diet->fresh(), $this->nutritionist->id);

        $this->assertNotNull(DietDay::where('diet_id', $diet->id)->value('completed_at'));
        $this->assertSame(Diet::FINISHED, $diet->fresh()->status);
        $this->assertSame(1, $this->billed('almuerzo'));
        $this->assertSame(0, $this->billed('cena'));
    }

    public function test_discharge_bills_received_meals_even_if_the_day_is_incomplete(): void
    {
        $diet = $this->diet('111', ['desayuno', 'almuerzo', 'cena']);

        // Sin desayuno; se despacha y recibe el almuerzo y luego se da de alta
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:10:00'));
        $this->serve($diet, 'almuerzo');
        $this->travelTo(CarbonImmutable::parse('2026-10-20 14:00:00'));
        app(DietService::class)->finishDiet($diet->fresh(), $this->nutritionist->id);

        $this->assertNull(DietDay::where('diet_id', $diet->id)->value('completed_at'));
        $this->assertSame(1, $this->billed('almuerzo'));
        $this->assertSame(0, $this->billed('desayuno'));
        $this->assertSame(0, $this->billed('cena'));

        $census = app(\App\Services\Reports\DietReportService::class)->monthlyCensus(CarbonImmutable::parse('2026-10-01'), receivedOnly: true);
        $this->assertSame(1, $census['totals']['almuerzo']);
    }

    public function test_discharge_bills_a_meal_dispatched_before_its_scheduled_time(): void
    {
        $diet = $this->diet('112', ['desayuno', 'almuerzo']);
        $this->serve($diet, 'desayuno');

        // Almuerzo despachado a las 11:00 y alta a las 11:30 (antes de la hora del almuerzo)
        $this->travelTo(CarbonImmutable::parse('2026-10-20 11:00:00'));
        $this->serve($diet, 'almuerzo');
        $this->travelTo(CarbonImmutable::parse('2026-10-20 11:30:00'));
        app(DietService::class)->finishDiet($diet->fresh(), $this->nutritionist->id);

        $this->assertSame(1, $this->billed('desayuno'));
        $this->assertSame(1, $this->billed('almuerzo'));
    }

    public function test_cancellation_bills_meals_received_before_cancelling(): void
    {
        $diet = $this->diet('113', ['desayuno', 'almuerzo', 'cena']);
        $this->serve($diet, 'desayuno');

        // Almuerzo despachado; se cancela antes de que el nutricionista confirme la recepción
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:05:00'));
        app(DispatchService::class)->dispatch([$diet->id], 'almuerzo', $this->dispatcher->id);
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:30:00'));
        app(DietService::class)->cancelDiet($diet->fresh(), $this->nutritionist->id);

        $this->assertSame(1, $this->billed('desayuno'));
        $this->assertSame(0, $this->billed('almuerzo')); // despachado, aún sin recibir

        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:40:00'));
        app(DispatchService::class)->confirmReceipt(
            DietDispatch::where('diet_id', $diet->id)->pendingReceipt()->pluck('id')->all(),
            $this->nutritionist->id,
        );

        $this->assertSame(1, $this->billed('almuerzo'));
        $this->assertSame(0, $this->billed('cena'));
        $this->assertNull(DietDay::where('diet_id', $diet->id)->value('completed_at')); // el día no se completa
    }

    public function test_cancelled_diets_cannot_be_continued(): void
    {
        $diet = $this->diet('110');
        app(DietService::class)->cancelDiet($diet, $this->nutritionist->id);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(DietDayService::class)->continueDiet($diet->fresh(), $this->nutritionist->id);
    }
}
