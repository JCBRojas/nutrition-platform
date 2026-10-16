<?php

namespace Tests\Feature;

use App\Events\DietsChanged;
use App\Models\DietDispatch;
use App\Models\User;
use App\Services\DietService;
use App\Services\DispatchService;
use App\Support\LiveUpdates;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Tiempo real (reemplazo del polling): un solo aviso DietsChanged por operación, sin datos
 * clínicos, en un canal privado solo para quien ve dietas o despachos.
 */
class LiveUpdatesTest extends TestCase
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
        $this->travelTo(CarbonImmutable::parse('2026-10-20 06:00:00'));
    }

    private function diet(): \App\Models\Diet
    {
        return app(DietService::class)->createDiet(
            ['habitation' => '301', 'name_patient' => 'Paciente 301', 'timeFood' => ['desayuno'], 'billing_type' => 'normal'],
            $this->nutritionist->id,
        );
    }

    public function test_creating_a_diet_sends_one_notice_without_clinical_data(): void
    {
        Event::fake([DietsChanged::class]);

        $this->diet();

        Event::assertDispatchedTimes(DietsChanged::class, 1);
        Event::assertDispatched(DietsChanged::class, function (DietsChanged $e) {
            $payload = json_encode($e->broadcastWith());

            return in_array(LiveUpdates::DIET, $e->kinds, true)
                && in_array(LiveUpdates::DAY, $e->kinds, true)
                && ! str_contains($payload, 'Paciente');
        });
    }

    public function test_dispatch_and_receipt_notify_dispatch_changes(): void
    {
        $diet = $this->diet();
        Event::fake([DietsChanged::class]);

        $this->travelTo(CarbonImmutable::parse('2026-10-20 06:40:00'));
        app(DispatchService::class)->dispatch([$diet->id], 'desayuno', $this->dispatcher->id);
        Event::assertDispatched(DietsChanged::class, fn ($e) => in_array(LiveUpdates::DISPATCH, $e->kinds, true));

        app(DispatchService::class)->confirmReceipt(DietDispatch::pluck('id')->all(), $this->nutritionist->id);
        Event::assertDispatchedTimes(DietsChanged::class, 2);
    }

    public function test_confirming_changes_notifies(): void
    {
        $diet = $this->diet();
        Event::fake([DietsChanged::class]);

        app(DietService::class)->confirmChanges($diet);

        Event::assertDispatched(DietsChanged::class);
    }

    public function test_muted_block_sends_nothing(): void
    {
        Event::fake([DietsChanged::class]);

        LiveUpdates::muted(fn () => $this->diet());

        Event::assertNotDispatched(DietsChanged::class);
    }

    public function test_a_broadcast_failure_does_not_break_the_operation(): void
    {
        Event::listen(DietsChanged::class, fn () => throw new \RuntimeException('WebSocket caído'));

        $diet = $this->diet();

        $this->assertDatabaseHas('diets', ['id' => $diet->id]);
    }

    public function test_channel_is_only_for_users_who_see_diets_or_dispatches(): void
    {
        $channels = app(\Illuminate\Broadcasting\BroadcastManager::class)->driver();
        $callback = fn (User $user) => (new \ReflectionClass($channels))->getMethod('getChannels')->invoke($channels)
            ->get(DietsChanged::CHANNEL)($user);

        $this->assertTrue($callback($this->nutritionist));
        $this->assertTrue($callback($this->dispatcher));
        $this->assertFalse($callback(User::factory()->create()));
    }
}
