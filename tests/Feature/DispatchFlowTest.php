<?php

namespace Tests\Feature;

use App\Exports\MonthlySummaryExport;
use App\Livewire\DietsManager;
use App\Livewire\DispatchLog;
use App\Livewire\DispatchReceipts;
use App\Livewire\NutritionDiets;
use App\Models\Diet;
use App\Models\DietDispatch;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DietService;
use App\Services\DispatchService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class DispatchFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $nutritionist;

    private User $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activatePremium(); // usa pantallas del plan Premium (precios, despachos del día, historial)
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->nutritionist = User::role('nutricionista')->firstOrFail();
        $this->dispatcher = User::role('despachador')->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:30:00'));
        // Estos tests cubren la regla de lo recibido; la de días completados está en DietDayCompletionTest
        config(['dispatch.required_from' => '2026-10-20', 'dispatch.completion_required_from' => null]);
    }

    private function diet(string $room, array $timeFood = ['desayuno', 'almuerzo']): Diet
    {
        return app(DietService::class)->createDiet(
            ['habitation' => $room, 'name_patient' => "Paciente {$room}", 'timeFood' => $timeFood, 'billing_type' => 'normal'],
            $this->nutritionist->id,
        );
    }

    private function billed(string $service): int
    {
        $m = app(BillingService::class)->monthly(CarbonImmutable::parse('2026-10-01'));

        return collect($m['rows'])->first(fn ($r) => $r['type'] === 'normal' && $r['service'] === $service)['count'];
    }

    public function test_default_meal_follows_the_schedule(): void
    {
        $this->assertSame('desayuno', DispatchService::currentMeal(CarbonImmutable::parse('2026-10-20 05:00')));
        $this->assertSame('almuerzo', DispatchService::currentMeal(CarbonImmutable::parse('2026-10-20 12:30')));
        $this->assertSame('cena', DispatchService::currentMeal(CarbonImmutable::parse('2026-10-20 20:00')));
    }

    public function test_dispatcher_selects_and_dispatches_ready_diets(): void
    {
        $a = $this->diet('101');
        $b = $this->diet('102');
        $noLunch = $this->diet('103', ['desayuno']);

        Livewire::actingAs($this->dispatcher)->test(DietsManager::class)
            ->assertSet('meal', 'almuerzo')
            ->assertSet('showAll', true) // «Mostrar todas las activas» marcado por defecto
            ->assertSee('No incluye almuerzo.')
            ->set('showAll', false)
            ->assertDontSee('Paciente 103') // no incluye almuerzo: oculta con el filtro
            ->assertSee('2 de 3')
            ->call('togglePage', [$a->id, $b->id])
            ->assertSet('selected', [$a->id, $b->id])
            ->call('dispatchSelected')
            ->assertDispatched('diet-dispatched')
            ->assertSet('selected', [])
            ->assertSee('esperando recepción');

        $this->assertSame(2, DietDispatch::where('meal', 'almuerzo')->pendingReceipt()->count());
        $this->assertDatabaseMissing('diet_dispatches', ['diet_id' => $noLunch->id]);
    }

    public function test_diets_with_unconfirmed_changes_cannot_be_dispatched(): void
    {
        $diet = $this->diet('201');
        app(DietService::class)->createNewVersion($diet, ['timeFood' => ['almuerzo'], 'billing_type' => 'normal', 'changes' => 'con_cambios'], $this->nutritionist->id);

        $result = app(DispatchService::class)->dispatch([$diet->id], 'almuerzo', $this->dispatcher->id);

        $this->assertSame([], $result['dispatched']);
        $this->assertSame(['201' => 'Tiene cambios sin confirmar.'], $result['skipped']);
    }

    public function test_same_meal_is_not_dispatched_twice(): void
    {
        $diet = $this->diet('202');
        $service = app(DispatchService::class);

        $service->dispatch([$diet->id], 'almuerzo', $this->dispatcher->id);
        $again = $service->dispatch([$diet->id], 'almuerzo', $this->dispatcher->id);

        $this->assertSame(['202' => 'Ya se despachó.'], $again['skipped']);
        $this->assertSame(1, DietDispatch::count());
    }

    public function test_nutritionist_is_notified_and_confirms_reception(): void
    {
        $diet = $this->diet('301');
        $component = Livewire::actingAs($this->nutritionist)->test(DispatchReceipts::class)
            ->assertDontSee('por confirmar recepción');

        app(DispatchService::class)->dispatch([$diet->id], 'almuerzo', $this->dispatcher->id);
        $dispatch = DietDispatch::firstOrFail();

        $component->call('checkNew')
            ->assertDispatched('dispatch-arrived', count: 1, rooms: ['301'])
            ->assertSee('1 despacho por confirmar recepción')
            ->set('selected', [$dispatch->id])
            ->call('confirmReceipt')
            ->assertDispatched('dispatch-received')
            ->assertDontSee('por confirmar recepción');

        $this->assertNotNull($dispatch->fresh()->received_at);
        $this->assertSame($this->nutritionist->id, $dispatch->fresh()->received_by);
    }

    public function test_dashboard_shows_pending_receptions(): void
    {
        $diet = $this->diet('302');
        app(DispatchService::class)->dispatch([$diet->id], 'almuerzo', $this->dispatcher->id);

        $this->actingAs($this->nutritionist)->get(route('dashboard.nutrition'))
            ->assertOk()
            ->assertSee('1 despacho por confirmar recepción');
    }

    public function test_dispatcher_cannot_confirm_reception(): void
    {
        $diet = $this->diet('303');
        app(DispatchService::class)->dispatch([$diet->id], 'almuerzo', $this->dispatcher->id);

        Livewire::actingAs($this->dispatcher)->test(DispatchReceipts::class)
            ->set('selected', [DietDispatch::firstOrFail()->id])
            ->call('confirmReceipt')
            ->assertForbidden();
    }

    public function test_only_dispatched_and_received_meals_are_billed(): void
    {
        $diet = $this->diet('401'); // desayuno + almuerzo, creada hoy 20 oct
        $service = app(DispatchService::class);
        $service->dispatch([$diet->id], 'desayuno', $this->dispatcher->id);
        $service->dispatch([$diet->id], 'almuerzo', $this->dispatcher->id);

        // Despachado pero sin recibir: no se factura
        $this->assertSame(0, $this->billed('desayuno'));
        $this->assertSame(0, $this->billed('almuerzo'));

        $service->confirmReceipt([DietDispatch::where('meal', 'desayuno')->value('id')], $this->nutritionist->id);

        $this->assertSame(1, $this->billed('desayuno'));
        $this->assertSame(0, $this->billed('almuerzo'));
        $this->assertSame(1, app(BillingService::class)->monthly(CarbonImmutable::parse('2026-10-01'))['notReceived']);
    }

    public function test_days_before_the_cutoff_keep_the_previous_rule(): void
    {
        $diet = $this->diet('402', ['desayuno']);
        $diet->forceFill(['created_at' => '2026-10-18 05:00:00'])->saveQuietly();
        $diet->history()->update(['created_at' => '2026-10-18 05:00:00']);

        // 18 y 19 sin despacho (antes del corte) cuentan; el 20 no (sin recepción)
        $this->assertSame(2, $this->billed('desayuno'));
    }

    private function dispatchAndReceive(Diet $diet, string $meal): void
    {
        app(DispatchService::class)->dispatch([$diet->id], $meal, $this->dispatcher->id);
        app(DispatchService::class)->confirmReceipt(DietDispatch::where('diet_id', $diet->id)->where('meal', $meal)->pluck('id')->all(), $this->nutritionist->id);
    }

    public function test_monthly_excel_counts_only_received_meals(): void
    {
        Excel::fake();
        $diet = $this->diet('501'); // desayuno + almuerzo, creada hoy (día del corte)
        $this->dispatchAndReceive($diet, 'desayuno');
        app(DispatchService::class)->dispatch([$diet->id], 'almuerzo', $this->dispatcher->id); // sin recibir

        $this->actingAs($this->nutritionist)->get(route('reports.monthly.excel', ['mes' => '2026-10']))->assertOk();

        Excel::assertDownloaded('reporte-nutricional_2026-10_20261020_1230.xlsx', function (MonthlySummaryExport $export) {
            $summary = collect($export->sheets()[0]->array());

            return $summary->contains(fn ($row) => $row[0] === 'Total bandejas' && $row[1] === 1)
                && $summary->contains(fn ($row) => $row[0] === 'Tiempos no contados (sin recepción o día sin completar)' && $row[1] === 1);
        });
    }

    public function test_dispatcher_last_change_column_shows_only_the_title(): void
    {
        $this->diet('601');

        Livewire::actingAs($this->dispatcher)->test(DietsManager::class)
            ->assertDontSee('por '.$this->nutritionist->name)
            ->assertDontSeeHtml('<div class="dm-meta">v1');
    }

    public function test_time_column_marks_dispatched_and_received_meals(): void
    {
        $diet = $this->diet('602');
        app(DispatchService::class)->dispatch([$diet->id], 'desayuno', $this->dispatcher->id);

        Livewire::actingAs($this->dispatcher)->test(DietsManager::class)
            ->assertSeeHtml('title="Desayuno: despachado hoy, falta confirmar recepción"')
            ->assertDontSeeHtml('title="Desayuno: entregado y recibido hoy"');

        app(DispatchService::class)->confirmReceipt(DietDispatch::pluck('id')->all(), $this->nutritionist->id);

        $this->actingAs($this->nutritionist)->get(route('dashboard.nutrition'))
            ->assertOk()
            ->assertSee('title="Desayuno: entregado y recibido hoy"', false)
            ->assertDontSee('title="Almuerzo: entregado y recibido hoy"', false);
    }

    public function test_nutritionist_searches_active_diets(): void
    {
        $this->diet('701');
        $this->diet('702');

        $this->actingAs($this->nutritionist)->get(route('dashboard.nutrition', ['q' => '701']))
            ->assertOk()
            ->assertSee('Paciente 701')
            ->assertDontSee('Paciente 702')
            ->assertSee('1 resultado(s) para «701»');
    }

    public function test_daily_dispatch_log_for_both_roles(): void
    {
        $a = $this->diet('801');
        $b = $this->diet('802');
        $this->dispatchAndReceive($a, 'almuerzo');
        app(DispatchService::class)->dispatch([$b->id], 'almuerzo', $this->dispatcher->id);

        foreach ([$this->nutritionist, $this->dispatcher] as $user) {
            $this->actingAs($user)->get(route('dispatches.index'))->assertOk()->assertSee('Despachos del día');
            $this->flushSession();
        }

        Livewire::actingAs($this->nutritionist)->test(DispatchLog::class)
            ->assertSet('date', '2026-10-20')
            ->assertSeeInOrder(['Paciente 801', 'Paciente 802']) // por habitación
            ->assertSee('Esperando recepción')
            ->set('status', 'received')
            ->assertSee('Paciente 801')
            ->assertDontSee('Paciente 802')
            ->set('date', '2026-10-19')
            ->assertSee('No hay despachos que coincidan');

        $this->assertSame(['dispatched' => 2, 'received' => 1, 'planned' => 2], array_diff_key(
            app(DispatchService::class)->daySummary(now())['almuerzo'], ['label' => 0]
        ));
    }

    public function test_users_without_permission_cannot_see_the_dispatch_log(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dispatches.index'))->assertForbidden();
    }

    public function test_dispatcher_sees_only_diets_for_the_selected_service(): void
    {
        $this->diet('901', ['desayuno', 'cena']);
        $this->diet('902', ['media_manana']); // clave antigua del formulario
        $changed = $this->diet('903', ['cena']);
        app(DietService::class)->createNewVersion($changed, ['timeFood' => ['desayuno'], 'billing_type' => 'normal', 'changes' => 'con_cambios'], $this->nutritionist->id);

        Livewire::actingAs($this->dispatcher)->test(DietsManager::class)
            ->set('showAll', false) // por defecto se muestran todas; el filtro se activa al desmarcar
            ->set('meal', 'cena')
            ->assertSee('Paciente 901')
            ->assertDontSee('Paciente 902')
            ->assertSee('Paciente 903') // quitó la cena, pero tiene un cambio sin confirmar
            ->set('meal', 'mediamanana')
            ->assertSee('Paciente 902')
            ->assertDontSee('Paciente 901')
            ->set('meal', 'merienda')
            ->assertDontSee('Paciente 901')
            ->assertDontSee('Paciente 902');
    }

    public function test_tables_are_sorted_by_room_in_natural_order(): void
    {
        foreach (['1001', '205', '12', '101'] as $room) {
            $this->diet($room);
        }

        Livewire::actingAs($this->nutritionist)->test(NutritionDiets::class)
            ->assertSeeInOrder(['Paciente 12', 'Paciente 101', 'Paciente 205', 'Paciente 1001']);

        Livewire::actingAs($this->dispatcher)->test(DietsManager::class)
            ->assertSeeInOrder(['Paciente 12', 'Paciente 101', 'Paciente 205', 'Paciente 1001']);

        app(DispatchService::class)->dispatch(Diet::pluck('id')->all(), 'almuerzo', $this->dispatcher->id);

        Livewire::actingAs($this->nutritionist)->test(DispatchLog::class)
            ->assertSeeInOrder(['Paciente 12', 'Paciente 101', 'Paciente 205', 'Paciente 1001']);
        Livewire::actingAs($this->nutritionist)->test(DispatchReceipts::class)
            ->assertSeeInOrder(['Paciente 12', 'Paciente 101', 'Paciente 205', 'Paciente 1001']);
    }
}
