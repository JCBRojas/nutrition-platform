<?php

namespace Tests\Feature;

use App\Livewire\DietsManager;
use App\Livewire\NutritionDiets;
use App\Models\Diet;
use App\Models\DietDay;
use App\Models\DietDispatch;
use App\Models\DietHistory;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DietDayService;
use App\Services\DietService;
use App\Services\DispatchService;
use App\Services\Reports\DietReportService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Cierre diario (Completada), «Continuar dieta» y facturación de cada tiempo recibido.
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
        config(['dispatch.required_from' => '2026-10-20']);
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

    public function test_each_received_meal_is_billed_even_if_the_day_is_incomplete(): void
    {
        $diet = $this->diet('103');
        $this->serve($diet, 'desayuno');

        // Desayuno recibido y almuerzo pendiente: el día no está completo, pero el desayuno se factura
        $this->assertNull(DietDay::where('diet_id', $diet->id)->value('completed_at'));
        $this->assertSame(1, $this->billed('desayuno'));
        $this->assertSame(0, $this->billed('almuerzo'));
        $this->assertSame(1, app(BillingService::class)->monthly(CarbonImmutable::parse('2026-10-01'))['notReceived']);

        $census = app(DietReportService::class)->monthlyCensus(CarbonImmutable::parse('2026-10-01'), receivedOnly: true);
        $this->assertSame(1, $census['totals']['desayuno']);
        $this->assertSame(0, $census['totals']['almuerzo']);

        $this->serve($diet, 'almuerzo');

        $this->assertSame(1, $this->billed('desayuno'));
        $this->assertSame(1, $this->billed('almuerzo'));
        $this->assertSame(0, app(BillingService::class)->monthly(CarbonImmutable::parse('2026-10-01'))['notReceived']);
    }

    public function test_billing_explains_what_is_missing(): void
    {
        // SCRUM-14: desayuno recibido (se factura), almuerzo pendiente → se explica qué falta
        $diet = $this->diet('999', ['desayuno', 'almuerzo']);
        $this->serve($diet, 'desayuno');

        $pending = app(BillingService::class)->monthly(CarbonImmutable::parse('2026-10-01'))['pending'];
        $this->assertCount(1, $pending);
        $this->assertSame(['almuerzo'], $pending[0]['withheld']);
        $this->assertSame(['desayuno'], $pending[0]['received']);

        $this->activatePremium();
        $this->actingAs(User::role('jefe_nutricion')->firstOrFail())
            ->get(route('prices.index', ['mes' => '2026-10']))
            ->assertOk()
            ->assertSee('1 servicio(s) sin facturar', false)
            ->assertSee('Hab. 999')
            ->assertSee('sin recepción: Almuerzo.', false)
            ->assertSee('Se factura cuando nutrición confirme la recepción.');

        // Recibido el almuerzo ya no hay pendientes y se factura aunque no haya precio (cantidad con $ 0)
        $this->serve($diet, 'almuerzo');
        $monthly = app(BillingService::class)->monthly(CarbonImmutable::parse('2026-10-01'));
        $this->assertTrue($monthly['pending']->isEmpty());
        $row = collect($monthly['rows'])->first(fn ($r) => $r['type'] === 'normal' && $r['service'] === 'desayuno');
        $this->assertSame(1, $row['count']);
        $this->assertSame(1, $row['missingPriceTrays']);
    }

    public function test_received_meal_counts_even_if_the_latest_version_dropped_it(): void
    {
        $diet = $this->diet('106', ['desayuno', 'almuerzo']);
        $this->serve($diet, 'desayuno');

        // Más tarde se quita el desayuno: lo que nutrición ya recibió sigue contando
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00'));
        app(DietService::class)->createNewVersion($diet, [
            'timeFood' => ['almuerzo'], 'consistency' => ['normal'], 'specifications' => ['sin_gluten'],
            'billing_type' => 'normal', 'changes' => 'con_cambios',
        ], $this->nutritionist->id);

        $this->assertSame(1, $this->billed('desayuno'));
    }

    public function test_received_meal_cannot_be_removed_when_editing(): void
    {
        $diet = $this->diet('107', ['desayuno', 'almuerzo']);
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:10:00'));
        $this->serve($diet, 'almuerzo');
        $form = ['timeFood' => ['desayuno'], 'consistency' => ['normal'], 'specifications' => ['hiposodica'], 'changes' => 'con_cambios'];

        $this->actingAs($this->nutritionist)
            ->put(route('dietas.update', $diet), $form)
            ->assertSessionHasErrors(['timeFood' => 'No puedes quitar Almuerzo: nutrición ya confirmó su recepción hoy.']);
        $this->assertSame(1, $diet->history()->count());

        // Conservando el almuerzo sí se puede editar (aquí se quita el desayuno, no recibido)
        $this->actingAs($this->nutritionist)
            ->put(route('dietas.update', $diet), ['timeFood' => ['almuerzo', 'cena']] + $form)
            ->assertSessionHasNoErrors();
        $this->assertSame(['almuerzo', 'cena'], $diet->fresh()->currentVersion->timeFood);

        // Al día siguiente el almuerzo de ayer ya no bloquea
        $this->travelTo(CarbonImmutable::parse('2026-10-21 08:00:00'));
        $this->actingAs($this->nutritionist)
            ->put(route('dietas.update', $diet), ['timeFood' => ['cena']] + $form)
            ->assertSessionHasNoErrors();
    }

    public function test_dispatched_but_not_received_meal_can_still_be_removed(): void
    {
        $diet = $this->diet('108', ['desayuno', 'almuerzo']);
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:10:00'));
        app(DispatchService::class)->dispatch([$diet->id], 'almuerzo', $this->dispatcher->id);

        $this->actingAs($this->nutritionist)
            ->put(route('dietas.update', $diet), ['timeFood' => ['desayuno'], 'consistency' => ['normal'], 'specifications' => ['hiposodica'], 'changes' => 'con_cambios'])
            ->assertSessionHasNoErrors();
    }

    public function test_edit_button_sends_the_received_meals_as_locked(): void
    {
        $diet = $this->diet('109', ['desayuno', 'almuerzo']);
        $this->serve($diet, 'desayuno');

        Livewire::actingAs($this->nutritionist)
            ->test(NutritionDiets::class)
            ->assertSeeHtml('"locked_meals":["desayuno"]');
    }

    public function test_next_day_without_continue_is_authorized_automatically_when_dispatching(): void
    {
        $diet = $this->diet('105', ['desayuno']);
        $this->serve($diet, 'desayuno');
        $this->assertSame(Diet::COMPLETED, $diet->fresh()->status);

        // Al día siguiente sin «Continuar dieta» ni diets:start-day: al despachar se inicia el día y se autoriza sola
        $this->travelTo(CarbonImmutable::parse('2026-10-21 06:40:00'));
        $result = app(DispatchService::class)->dispatch([$diet->id], 'desayuno', $this->dispatcher->id);

        $this->assertSame(['105'], $result['dispatched']);
        $this->assertSame(DietDay::VIA_AUTOMATIC, DietDay::where('diet_id', $diet->id)->whereDate('service_date', '2026-10-21')->value('authorized_via'));
        $this->assertSame(Diet::ACTIVE, $diet->fresh()->status);
    }

    public function test_start_day_authorizes_open_diets_nobody_continued(): void
    {
        $completed = $this->diet('120', ['desayuno']);
        $this->serve($completed, 'desayuno');
        $active = $this->diet('121', ['desayuno', 'almuerzo']);
        $continued = $this->diet('122', ['desayuno']);
        app(DietDayService::class)->continueDiet($continued, $this->nutritionist->id);
        $cancelled = $this->diet('123', ['desayuno']);
        app(DietService::class)->cancelDiet($cancelled, $this->nutritionist->id);
        $versions = DietHistory::count();

        $this->travelTo(CarbonImmutable::parse('2026-10-21 00:01:00'));
        $this->artisan('diets:start-day')
            ->expectsOutput('2 dieta(s) autorizada(s) automáticamente; 1 volvieron a Activa.')
            ->assertSuccessful();

        $via = fn (Diet $d) => DietDay::where('diet_id', $d->id)->whereDate('service_date', '2026-10-21')->value('authorized_via');
        $this->assertSame(DietDay::VIA_AUTOMATIC, $via($completed));
        $this->assertSame(DietDay::VIA_AUTOMATIC, $via($active));
        $this->assertSame(DietDay::VIA_CONTINUATION, $via($continued)); // no se pisa «Continuar»
        $this->assertNull($via($cancelled));
        $this->assertSame(Diet::ACTIVE, $completed->fresh()->status);
        $this->assertSame($versions, DietHistory::count()); // sin versión: no hay usuario

        // Correrlo otra vez no duplica nada
        $this->artisan('diets:start-day')->expectsOutput('0 dieta(s) autorizada(s) automáticamente; 0 volvieron a Activa.');
        $this->assertSame(3, DietDay::whereDate('service_date', '2026-10-21')->count()); // la cancelada no tiene día
    }

    public function test_authorize_today_when_the_day_was_not_authorized(): void
    {
        $diet = $this->diet('124', ['desayuno']);
        $this->serve($diet, 'desayuno');
        $this->travelTo(CarbonImmutable::parse('2026-10-21 06:00:00')); // no corrió el inicio del día

        // Solo lectura (sin continueDiets): no ve el botón ni puede autorizar
        $reader = User::factory()->create();
        $reader->assignRole(Role::firstOrCreate(['name' => 'lectura', 'guard_name' => 'web'])->syncPermissions(['viewActiveDiets']));
        Livewire::actingAs($reader)->test(NutritionDiets::class)
            ->assertSee('Sin autorizar hoy')
            ->assertDontSeeHtml('wire:click="authorizeToday(')
            ->call('authorizeToday', $diet->id)
            ->assertForbidden();

        Livewire::actingAs($this->nutritionist)->test(NutritionDiets::class)
            ->assertSee('Sin autorizar hoy')
            ->assertSee('Autorizar hoy')
            ->call('authorizeToday', $diet->id)
            ->assertSet('noticeError', false)
            ->assertSet('notice', 'Dieta de la habitación 124 autorizada para hoy.')
            ->assertSee('En curso')
            ->call('authorizeToday', $diet->id)
            ->assertSet('noticeError', true)
            ->assertSet('notice', 'La dieta de la habitación 124 ya está autorizada para hoy.');

        $this->assertSame(Diet::ACTIVE, $diet->fresh()->status);
        $this->assertSame('continuada', $diet->fresh()->currentVersion->changes);
        $this->assertSame(['124'], app(DispatchService::class)->dispatch([$diet->id], 'desayuno', $this->dispatcher->id)['dispatched']);
    }

    public function test_closed_diet_cannot_be_authorized_today(): void
    {
        $diet = $this->diet('125', ['desayuno']);
        app(DietService::class)->cancelDiet($diet, $this->nutritionist->id);

        $this->expectException(ValidationException::class);
        app(DietDayService::class)->authorizeToday($diet->fresh(), $this->nutritionist->id);
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

        $census = app(DietReportService::class)->monthlyCensus(CarbonImmutable::parse('2026-10-01'), receivedOnly: true);
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

        $this->expectException(ValidationException::class);
        app(DietDayService::class)->continueDiet($diet->fresh(), $this->nutritionist->id);
    }
}
