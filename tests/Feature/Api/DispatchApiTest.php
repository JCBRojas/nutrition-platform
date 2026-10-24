<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Diet;
use App\Models\DietDispatch;
use App\Models\DietDispatchVoid;
use App\Models\User;
use App\Services\DietService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * API v1 de operación (paso 2 del plan del cliente de escritorio): tablero del despachador,
 * despachar, confirmar cambios, anular / «No llegó», recepción y autorización del tiempo real.
 * Mismas reglas que la web (DispatchBoard / DispatchService).
 */
class DispatchApiTest extends TestCase
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
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:30:00')); // servicio actual: almuerzo
    }

    private function diet(string $room, array $data = []): Diet
    {
        return app(DietService::class)->createDiet(
            ['habitation' => $room, 'name_patient' => "Paciente {$room}", 'timeFood' => ['almuerzo', 'cena'], 'billing_type' => 'normal', ...$data],
            $this->nutritionist->id,
        );
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user, ['*']);

        return $this;
    }

    public function test_board_lists_diets_with_their_dispatch_state(): void
    {
        $diet = $this->diet('101');

        $response = $this->as($this->dispatcher)->getJson(route('api.v1.dispatch.board'))
            ->assertOk()
            ->assertJsonPath('meal', 'almuerzo')
            ->assertJsonPath('tab', 'active')
            ->assertJsonPath('data.0.id', $diet->id)
            ->assertJsonPath('data.0.habitation', '101')
            ->assertJsonPath('data.0.time_food', ['almuerzo', 'cena'])
            ->assertJsonPath('data.0.block_reason', null)
            ->assertJsonPath('data.0.dispatch', null)
            ->assertJsonPath('dispatchable', [$diet->id])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('counts.active', 1);

        // Nada de datos del usuario que editó
        $this->assertStringNotContainsString($this->nutritionist->email, $response->getContent());
    }

    public function test_dispatcher_dispatches_and_the_nutritionist_confirms_reception(): void
    {
        $diet = $this->diet('102');

        $this->as($this->dispatcher)->postJson(route('api.v1.dispatch.store'), ['diet_ids' => [$diet->id], 'meal' => 'almuerzo'])
            ->assertOk()
            ->assertJsonPath('dispatched', ['102']);
        // Ya despachada en ese tiempo: se omite
        $this->postJson(route('api.v1.dispatch.store'), ['diet_ids' => [$diet->id], 'meal' => 'almuerzo'])
            ->assertOk()->assertJsonPath('dispatched', []);

        $this->getJson(route('api.v1.dispatch.board', ['servicio' => 'almuerzo']))
            ->assertJsonPath('data.0.dispatch.dispatched_by', $this->dispatcher->name)
            ->assertJsonPath('data.0.delivery.almuerzo', 'dispatched')
            ->assertJsonPath('dispatchable', []);

        $pending = $this->as($this->nutritionist)->getJson(route('api.v1.receipts.pending'))
            ->assertOk()
            ->assertJsonPath('data.0.habitation', '102')
            ->assertJsonPath('data.0.meal_label', 'Almuerzo')
            ->json('data.0.id');

        $this->postJson(route('api.v1.receipts.confirm'), ['dispatch_ids' => [$pending]])
            ->assertOk()->assertJsonPath('received', 1);
        $this->getJson(route('api.v1.receipts.pending'))->assertJsonCount(0, 'data');
    }

    public function test_void_permission_depends_on_the_kind(): void
    {
        $diet = $this->diet('103');
        $this->as($this->dispatcher)->postJson(route('api.v1.dispatch.store'), ['diet_ids' => [$diet->id], 'meal' => 'almuerzo']);
        $dispatch = DietDispatch::where('diet_id', $diet->id)->firstOrFail();

        // La cocina no reporta «No llegó»; nutrición no anula despachos
        $this->postJson(route('api.v1.dispatches.void', $dispatch->id), ['kind' => DietDispatchVoid::NOT_ARRIVED])->assertForbidden();
        $this->as($this->nutritionist)->postJson(route('api.v1.dispatches.void', $dispatch->id), ['kind' => DietDispatchVoid::CANCELLED])->assertForbidden();

        $this->postJson(route('api.v1.dispatches.void', $dispatch->id), ['kind' => DietDispatchVoid::NOT_ARRIVED, 'reason' => 'No subió'])
            ->assertOk()->assertJsonPath('label', 'No llegó');
        $this->assertModelMissing($dispatch);

        // Ya no existe: error de validación en español
        $this->postJson(route('api.v1.dispatches.void', $dispatch->id), ['kind' => DietDispatchVoid::NOT_ARRIVED])->assertStatus(422);

        $this->as($this->dispatcher)->getJson(route('api.v1.dispatch.board'))
            ->assertJsonPath('data.0.missing.reason', 'No subió');
    }

    public function test_pending_changes_are_listed_and_confirmed(): void
    {
        $diet = $this->diet('104');
        app(DietService::class)->createNewVersion($diet, ['timeFood' => ['almuerzo'], 'billing_type' => 'normal'], $this->nutritionist->id);

        $this->as($this->dispatcher)->getJson(route('api.v1.dispatch.board'))
            ->assertJsonPath('pending.0.diet_id', $diet->id)
            ->assertJsonPath('pending.0.key', "{$diet->id}:2")
            ->assertJsonPath('data.0.has_pending', true)
            ->assertJsonPath('data.0.pending_changes.timeFood.removed', ['cena'])
            ->assertJsonPath('data.0.block_reason', fn ($reason) => is_string($reason)); // sin confirmar no se despacha

        $this->postJson(route('api.v1.diets.confirm-changes', $diet->id))->assertOk();
        $this->getJson(route('api.v1.dispatch.board'))->assertJsonCount(0, 'pending');
    }

    public function test_permissions_and_company_isolation(): void
    {
        $this->as($this->nutritionist)->getJson(route('api.v1.dispatch.board'))->assertForbidden();
        $this->as($this->dispatcher)->getJson(route('api.v1.receipts.pending'))->assertForbidden();

        // Dieta de otra empresa: no aparece y no se puede tocar
        $other = Company::create(['name' => 'Clínica Norte', 'nit' => '900123457-1', 'contact_name' => 'X', 'contact_phone' => '3000000000', 'contact_email' => 'x@clinica.org', 'is_active' => true]);
        $foreign = Diet::withoutGlobalScopes()->create(['habitation' => '999', 'name_patient' => 'Otra', 'status' => Diet::ACTIVE, 'company_id' => $other->id]);

        $this->as($this->dispatcher)->getJson(route('api.v1.dispatch.board'))->assertJsonPath('meta.total', 0);
        $this->postJson(route('api.v1.diets.confirm-changes', $foreign->id))->assertForbidden();
        $this->postJson(route('api.v1.dispatch.store'), ['diet_ids' => [$foreign->id], 'meal' => 'almuerzo'])->assertJsonPath('dispatched', []);
    }

    public function test_compliance_is_required_for_operation(): void
    {
        config(['security.privacy.required' => true]);

        $this->as($this->dispatcher)->getJson(route('api.v1.dispatch.board'))
            ->assertForbidden()
            ->assertJsonPath('code', 'privacy_required')
            ->assertJsonPath('url', route('privacy.accept'));
        // me sigue respondiendo para que la app muestre el aviso
        $this->getJson(route('api.v1.me'))->assertOk()->assertJsonPath('compliance.privacy_required', true);
    }

    public function test_realtime_channel_is_authorized_with_the_token(): void
    {
        config(['broadcasting.default' => 'pusher', 'broadcasting.connections.pusher.key' => 'app-key',
            'broadcasting.connections.pusher.secret' => 'app-secret', 'broadcasting.connections.pusher.app_id' => 'app-id']);
        app('Illuminate\Broadcasting\BroadcastManager')->purge('pusher');
        require base_path('routes/channels.php'); // los canales se registraron al arrancar con el driver null

        $this->as($this->dispatcher)->postJson(route('api.v1.broadcasting.auth'), ['socket_id' => '123.456', 'channel_name' => 'private-diets'])
            ->assertOk()->assertJsonStructure(['auth']);

        // Sin ningún permiso de dietas: no se suscribe
        $this->as(User::factory()->create())->postJson(route('api.v1.broadcasting.auth'), ['socket_id' => '123.456', 'channel_name' => 'private-diets'])
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->postJson(route('api.v1.broadcasting.auth'), ['socket_id' => '1.2', 'channel_name' => 'private-diets'])
            ->assertUnauthorized();
    }
}
