<?php

namespace Tests\Feature;

use App\Livewire\DietsManager;
use App\Livewire\NutritionDiets;
use App\Models\Diet;
use App\Models\DietDay;
use App\Models\DietDispatch;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DietService;
use App\Services\DispatchService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Confirmación de cancelación / alta: aviso de despachos sin recibir (se cobran) y
 * alta con envío de comida a otro lugar (p. ej. paciente remitido a otra IPS).
 */
class DischargeTransferTest extends TestCase
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

    private function diet(string $room, array $timeFood = ['desayuno', 'almuerzo', 'cena']): Diet
    {
        return app(DietService::class)->createDiet(
            ['habitation' => $room, 'name_patient' => "Paciente {$room}", 'timeFood' => $timeFood, 'billing_type' => 'normal'],
            $this->nutritionist->id,
        );
    }

    private function receiveAll(Diet $diet): void
    {
        app(DispatchService::class)->confirmReceipt(
            DietDispatch::where('diet_id', $diet->id)->pendingReceipt()->pluck('id')->all(),
            $this->nutritionist->id,
        );
    }

    private function billed(string $service): int
    {
        $m = app(BillingService::class)->monthly(CarbonImmutable::parse('2026-10-01'));

        return collect($m['rows'])->first(fn ($r) => $r['type'] === 'normal' && $r['service'] === $service)['count'];
    }

    public function test_close_modal_warns_about_dispatches_without_receipt(): void
    {
        $diet = $this->diet('201');
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:05:00'));
        app(DispatchService::class)->dispatch([$diet->id], 'almuerzo', $this->dispatcher->id);

        foreach (['cancel', 'finish'] as $type) {
            Livewire::actingAs($this->nutritionist)->test(NutritionDiets::class)
                ->call('startClose', $diet->id, $type)
                ->assertSee('Tiene 1 despacho sin confirmar la recepción')
                ->assertSee('Almuerzo — hoy')
                ->assertSee('se cobrará');
        }
    }

    public function test_cancel_from_the_modal(): void
    {
        $diet = $this->diet('202');

        Livewire::actingAs($this->nutritionist)->test(NutritionDiets::class)
            ->call('startClose', $diet->id, 'cancel')
            ->assertSee('No tiene despachos de hoy')
            ->assertDontSee('¿Se debe despachar la comida a otro lugar?')
            ->call('confirmClose')
            ->assertSet('closingId', null)
            ->assertSee('cancelada');

        $this->assertSame(Diet::CANCELLED, $diet->fresh()->status);
    }

    public function test_discharge_with_transfer_requires_destination_and_meals(): void
    {
        $diet = $this->diet('203');

        Livewire::actingAs($this->nutritionist)->test(NutritionDiets::class)
            ->call('startClose', $diet->id, 'finish')
            ->set('transferEnabled', true)
            ->call('confirmClose')
            ->assertHasErrors(['transferDestination', 'transferMeals']);

        $this->assertTrue($diet->fresh()->isActive());
    }

    public function test_transfer_meals_must_not_be_dispatched_already(): void
    {
        $diet = $this->diet('204');
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:05:00'));
        app(DispatchService::class)->dispatch([$diet->id], 'almuerzo', $this->dispatcher->id);

        $this->assertSame(['desayuno' => 'Desayuno', 'cena' => 'Cena'], app(DispatchService::class)->transferCandidates($diet->fresh()));

        $this->expectException(ValidationException::class);
        app(DietService::class)->finishDiet($diet->fresh(), $this->nutritionist->id, ['destination' => 'IPS Norte', 'meals' => ['almuerzo']]);
    }

    public function test_dispatcher_sends_the_transfer_meal_and_it_is_billed(): void
    {
        $diet = $this->diet('205');
        app(DispatchService::class)->dispatch([$diet->id], 'desayuno', $this->dispatcher->id);
        $this->receiveAll($diet);

        // Alta a las 10:00: el paciente va a otra IPS y el almuerzo se envía allá
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00'));
        Livewire::actingAs($this->nutritionist)->test(NutritionDiets::class)
            ->call('startClose', $diet->id, 'finish')
            ->assertSee('¿Se debe despachar la comida a otro lugar?')
            ->set('transferEnabled', true)
            ->set('transferDestination', 'IPS San José')
            ->set('transferMeals', ['almuerzo'])
            ->call('confirmClose')
            ->assertHasNoErrors()
            ->assertSee('Cocina verá el envío a IPS San José');

        $diet->refresh();
        $this->assertSame(Diet::FINISHED, $diet->status);
        $this->assertSame(['almuerzo'], $diet->transferMealsOn());
        $this->assertNull(DietDay::where('diet_id', $diet->id)->value('completed_at')); // falta el envío

        // El despachador la ve en activas, confirma el alta y despacha solo el almuerzo
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00'));
        Livewire::actingAs($this->dispatcher)->test(DietsManager::class, ['meal' => 'almuerzo'])
            ->assertSee('Alta: enviar almuerzo a IPS San José')
            ->call('confirmChanges', $diet->id);

        $this->assertSame('Alta: no incluye cena.', app(DispatchService::class)->blockReason($diet->fresh(), 'cena'));
        $result = app(DispatchService::class)->dispatch([$diet->id], 'almuerzo', $this->dispatcher->id);
        $this->assertSame(['205'], $result['dispatched']);

        $this->assertSame(0, $this->billed('almuerzo')); // sin recibir aún
        $this->receiveAll($diet);
        $this->assertSame(1, $this->billed('desayuno'));
        $this->assertSame(1, $this->billed('almuerzo'));
        $this->assertSame(0, $this->billed('cena'));
        $this->assertNotNull(DietDay::where('diet_id', $diet->id)->value('completed_at'));

        // Al día siguiente ya no aparece para despachar
        $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00:00'));
        $this->assertSame([], $diet->fresh()->transferMealsOn());
        $this->assertSame('La dieta no está activa.', app(DispatchService::class)->blockReason($diet->fresh(), 'almuerzo'));
    }

    public function test_discharge_without_transfer_is_not_dispatchable(): void
    {
        $diet = $this->diet('206');
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00'));
        app(DietService::class)->finishDiet($diet, $this->nutritionist->id);

        $this->assertNull($diet->fresh()->transfer_destination);
        $this->assertSame('La dieta no está activa.', app(DispatchService::class)->blockReason($diet->fresh(), 'almuerzo'));
    }
}
