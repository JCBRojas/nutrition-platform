<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Diet;
use App\Models\DietDispatch;
use App\Models\User;
use App\Services\DietService;
use App\Services\DispatchService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * API v1 del panel de dietas (paso 3 del plan del cliente de escritorio): listar, registrar,
 * editar, continuar, cancelar, dar de alta con envío y «Despachos del día». Mismas reglas
 * que la web (StoreDietRequest, DietService, DietDayService, NutritionBoard).
 */
class DietApiTest extends TestCase
{
    use RefreshDatabase;

    private User $nutritionist;

    private User $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->nutritionist = User::role('nutricionista')->firstOrFail();
        $this->dispatcher = User::role('despachador')->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:30:00'));
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user, ['*']);

        return $this;
    }

    private function payload(array $override = []): array
    {
        return ['habitation' => '201', 'name_patient' => 'Ana Gómez', 'timeFood' => ['almuerzo', 'cena'],
            'consistency' => ['normal'], 'specifications' => ['hiposodica'], 'billing_type' => 'normal', 'isolation' => 'No', ...$override];
    }

    private function diet(string $room): Diet
    {
        return app(DietService::class)->createDiet($this->payload(['habitation' => $room]), $this->nutritionist->id);
    }

    public function test_nutritionist_registers_and_lists_diets(): void
    {
        $id = $this->as($this->nutritionist)->postJson(route('api.v1.diets.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('habitation', '201')
            ->assertJsonPath('timeFood', ['almuerzo', 'cena'])
            ->assertJsonPath('updated_by', $this->nutritionist->name)
            ->json('id');
        $this->assertSame(Company::default()->id, Diet::findOrFail($id)->company_id);

        $this->getJson(route('api.v1.diets.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.day.authorizedToday', true)
            ->assertJsonPath('counts.active', 1)
            ->assertJsonPath('active_rooms', ['201'])
            ->assertJsonMissingPath('data.0.updated_by_email');
    }

    public function test_store_uses_the_same_validation_as_the_web(): void
    {
        $this->as($this->nutritionist)->postJson(route('api.v1.diets.store'), $this->payload(['habitation' => null, 'timeFood' => []]))
            ->assertStatus(422)
            ->assertJsonPath('errors.habitation.0', 'Escribe la habitación.')
            ->assertJsonPath('errors.timeFood.0', 'Elige al menos un tiempo de comida.');

        // Habitación con dieta activa: solo con confirmación (SCRUM-17)
        $this->diet('202');
        $this->postJson(route('api.v1.diets.store'), $this->payload(['habitation' => '202']))->assertStatus(422)->assertJsonValidationErrors('habitation');
        $this->postJson(route('api.v1.diets.store'), $this->payload(['habitation' => '202', 'allow_duplicate_room' => true]))->assertCreated();

        // El despachador no registra dietas
        $this->as($this->dispatcher)->postJson(route('api.v1.diets.store'), $this->payload(['habitation' => '203']))->assertForbidden();
    }

    public function test_update_keeps_the_meals_received_today(): void
    {
        $diet = $this->diet('205');
        $dispatch = app(DispatchService::class)->dispatch([$diet->id], 'almuerzo', $this->dispatcher->id);
        $this->assertSame(['205'], $dispatch['dispatched']);
        app(DispatchService::class)->confirmReceipt(DietDispatch::where('diet_id', $diet->id)->pluck('id')->all(), $this->nutritionist->id);

        $this->as($this->nutritionist)->putJson(route('api.v1.diets.update', $diet->id), $this->payload(['timeFood' => ['cena']]))
            ->assertStatus(422)
            ->assertJsonPath('errors.timeFood.0', 'No puedes quitar Almuerzo: nutrición ya confirmó su recepción hoy.');
        $this->putJson(route('api.v1.diets.update', $diet->id), $this->payload(['timeFood' => ['almuerzo']]))->assertOk();
    }

    public function test_update_creates_a_new_version_only_for_active_diets_of_the_company(): void
    {
        $diet = $this->diet('204');

        $this->as($this->nutritionist)->putJson(route('api.v1.diets.update', $diet->id), $this->payload(['timeFood' => ['cena']]))
            ->assertOk()
            ->assertJsonPath('version', 2)
            ->assertJsonPath('timeFood', ['cena']);

        $other = Company::create(['name' => 'Clínica Norte', 'nit' => '900123457-1', 'contact_name' => 'X', 'contact_phone' => '3000000000', 'contact_email' => 'x@clinica.org', 'is_active' => true]);
        $foreign = Diet::withoutGlobalScopes()->create(['habitation' => '999', 'name_patient' => 'Otra', 'status' => Diet::ACTIVE, 'company_id' => $other->id]);
        $this->putJson(route('api.v1.diets.update', $foreign->id), $this->payload())->assertForbidden();

        app(DietService::class)->cancelDiet($diet, $this->nutritionist->id);
        $this->putJson(route('api.v1.diets.update', $diet->id), $this->payload())->assertNotFound();

        $this->as($this->dispatcher)->putJson(route('api.v1.diets.update', $diet->id), $this->payload())->assertForbidden();
    }

    public function test_continue_cancel_and_finish_with_transfer(): void
    {
        $continued = $this->diet('205');
        $this->as($this->nutritionist)->postJson(route('api.v1.diets.continue', $continued->id))->assertOk();
        $this->as($this->dispatcher)->postJson(route('api.v1.diets.continue', $continued->id))->assertForbidden();

        $cancelled = $this->diet('206');
        $this->as($this->nutritionist)->getJson(route('api.v1.diets.closing-summary', $cancelled->id))
            ->assertOk()->assertJsonStructure(['pending', 'receivedToday', 'candidates']);
        $this->postJson(route('api.v1.diets.cancel', $cancelled->id))->assertOk();
        $this->assertSame(Diet::CANCELLED, $cancelled->fresh()->status);
        $this->postJson(route('api.v1.diets.cancel', $cancelled->id))->assertNotFound(); // ya no está activa

        // Alta con envío a otro lugar: destino y tiempos obligatorios si se indica el envío
        $finished = $this->diet('207');
        $this->postJson(route('api.v1.diets.finish', $finished->id), ['transfer' => ['destination' => '']])
            ->assertStatus(422)
            ->assertJsonPath('errors', fn ($errors) => isset($errors['transfer.destination'], $errors['transfer.meals']));
        $this->postJson(route('api.v1.diets.finish', $finished->id), ['transfer' => ['destination' => 'IPS San José', 'meals' => ['cena']]])
            ->assertOk();
        $this->assertSame(Diet::FINISHED, $finished->fresh()->status);
        $this->assertSame('IPS San José', $finished->fresh()->transfer_destination);

        $this->getJson(route('api.v1.diets.index', ['tab' => 'finished']))
            ->assertJsonPath('data.0.transfer.destination', 'IPS San José')
            ->assertJsonPath('data.0.transfer.meals', ['cena']);
    }

    public function test_dispatch_log_is_premium(): void
    {
        $diet = $this->diet('208');
        app(DispatchService::class)->dispatch([$diet->id], 'almuerzo', $this->dispatcher->id);

        $this->as($this->nutritionist)->getJson(route('api.v1.dispatches.day'))->assertStatus(402);

        $this->activatePremium();
        $this->getJson(route('api.v1.dispatches.day', ['servicio' => 'almuerzo']))
            ->assertOk()
            ->assertJsonPath('date', '2026-10-20')
            ->assertJsonPath('data.0.habitation', '208')
            ->assertJsonPath('data.0.dispatched_by', $this->dispatcher->name)
            ->assertJsonPath('summary.almuerzo.dispatched', 1);
        $this->assertSame(1, DietDispatch::count());

        $this->getJson(route('api.v1.dispatches.day', ['fecha' => '2030-01-01']))->assertStatus(422);
    }
}
