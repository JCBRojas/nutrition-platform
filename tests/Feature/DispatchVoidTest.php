<?php

namespace Tests\Feature;

use App\Livewire\DietsManager;
use App\Livewire\DispatchLog;
use App\Livewire\DispatchReceipts;
use App\Models\AuditLog;
use App\Models\DietDispatch;
use App\Models\DietDispatchVoid;
use App\Models\User;
use App\Services\DietService;
use App\Services\DispatchService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Despacho que no llegó (lo reporta el nutricionista) o que cocina anula por error
 * (despachador): sale de los pendientes por recibir y se puede volver a despachar.
 */
class DispatchVoidTest extends TestCase
{
    use RefreshDatabase;

    private User $nutritionist;

    private User $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activatePremium(); // «Despachos del día» es Premium
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->nutritionist = User::role('nutricionista')->firstOrFail();
        $this->dispatcher = User::role('despachador')->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:30:00'));
    }

    private function dispatched(string $room): DietDispatch
    {
        $diet = app(DietService::class)->createDiet(
            ['habitation' => $room, 'name_patient' => "Paciente {$room}", 'timeFood' => ['almuerzo'], 'billing_type' => 'normal'],
            $this->nutritionist->id,
        );
        app(DispatchService::class)->dispatch([$diet->id], 'almuerzo', $this->dispatcher->id);

        return DietDispatch::where('diet_id', $diet->id)->firstOrFail();
    }

    public function test_nutritionist_reports_that_a_dispatch_did_not_arrive(): void
    {
        $dispatch = $this->dispatched('101');

        Livewire::actingAs($this->nutritionist)->test(DispatchReceipts::class)
            ->assertSee('No llegó')
            ->call('startNotArrived', $dispatch->id)
            ->assertSee('El despacho no llegó · Hab. 101')
            ->set('voidReason', 'No subió a piso')
            ->call('confirmNotArrived')
            ->assertDispatched('dispatch-received')
            ->assertSet('voidingId', null)
            ->assertDontSee('por confirmar recepción');

        $this->assertModelMissing($dispatch);
        $void = DietDispatchVoid::firstOrFail();
        $this->assertSame(DietDispatchVoid::NOT_ARRIVED, $void->kind);
        $this->assertSame('No subió a piso', $void->reason);
        $this->assertSame($this->nutritionist->id, $void->voided_by);
        $this->assertSame($this->dispatcher->id, $void->dispatched_by);
        $this->assertTrue(AuditLog::where('event', 'dispatch_not_arrived')->exists());
    }

    public function test_dispatcher_sees_it_did_not_arrive_and_dispatches_it_again(): void
    {
        $dispatch = $this->dispatched('102');
        app(DispatchService::class)->voidDispatch($dispatch->id, DietDispatchVoid::NOT_ARRIVED, null, $this->nutritionist->id);

        Livewire::actingAs($this->dispatcher)->test(DietsManager::class)
            ->assertSee('No llegó: vuelve a despacharla')
            ->call('togglePage', [$dispatch->diet_id])
            ->call('dispatchSelected')
            ->assertSee('esperando recepción')
            ->assertDontSee('No llegó: vuelve a despacharla');

        $this->assertSame(1, DietDispatch::pendingReceipt()->where('diet_id', $dispatch->diet_id)->count());
    }

    public function test_dispatcher_cancels_a_dispatch_made_by_mistake(): void
    {
        $dispatch = $this->dispatched('103');

        Livewire::actingAs($this->dispatcher)->test(DietsManager::class)
            ->assertSee('Anular despacho')
            ->call('startCancelDispatch', $dispatch->id)
            ->assertSee('Anular despacho · Hab. 103')
            ->call('confirmCancelDispatch')
            ->assertDispatched('diet-dispatched')
            ->assertDontSee('esperando recepción');

        $this->assertModelMissing($dispatch);
        $this->assertSame(DietDispatchVoid::CANCELLED, DietDispatchVoid::firstOrFail()->kind);
        $this->assertTrue(AuditLog::where('event', 'dispatch_cancelled')->exists());

        // Nutrición ya no lo ve por recibir y el tiempo se puede despachar de nuevo
        $this->assertSame([], app(DispatchService::class)->dispatch([$dispatch->diet_id], 'almuerzo', $this->dispatcher->id)['skipped']);
    }

    public function test_a_received_dispatch_cannot_be_voided(): void
    {
        $dispatch = $this->dispatched('104');
        app(DispatchService::class)->confirmReceipt([$dispatch->id], $this->nutritionist->id);

        $this->expectException(ValidationException::class);
        app(DispatchService::class)->voidDispatch($dispatch->id, DietDispatchVoid::CANCELLED, null, $this->dispatcher->id);
    }

    public function test_dispatcher_does_not_see_cancel_once_received(): void
    {
        $dispatch = $this->dispatched('105');
        app(DispatchService::class)->confirmReceipt([$dispatch->id], $this->nutritionist->id);

        Livewire::actingAs($this->dispatcher)->test(DietsManager::class)
            ->assertSee('Recibida')
            ->assertDontSee('Anular despacho');
    }

    public function test_each_role_only_has_its_own_option(): void
    {
        $dispatch = $this->dispatched('106');

        Livewire::actingAs($this->dispatcher)->test(DispatchReceipts::class)
            ->call('startNotArrived', $dispatch->id)
            ->assertForbidden();

        Livewire::actingAs($this->nutritionist)->test(DietsManager::class)
            ->call('startCancelDispatch', $dispatch->id)
            ->assertForbidden();

        $this->assertModelExists($dispatch);
    }

    public function test_dispatch_log_lists_voided_dispatches(): void
    {
        $dispatch = $this->dispatched('107');
        app(DispatchService::class)->voidDispatch($dispatch->id, DietDispatchVoid::NOT_ARRIVED, 'Bandeja fría', $this->nutritionist->id);

        Livewire::actingAs($this->nutritionist)->test(DispatchLog::class)
            ->assertSee('Anulados o no llegaron (1)')
            ->assertSee('Bandeja fría')
            ->set('status', 'pending')
            ->assertDontSee('Anulados o no llegaron');
    }
}
