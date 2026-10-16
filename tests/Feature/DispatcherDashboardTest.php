<?php

namespace Tests\Feature;

use App\Livewire\DietsManager;
use App\Models\Diet;
use App\Models\User;
use App\Services\DietService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DispatcherDashboardTest extends TestCase
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
        // Hora fija: el servicio por defecto depende de la hora (a las 12:00 sería almuerzo y las dietas de solo desayuno no se listan)
        $this->travelTo(CarbonImmutable::parse('2026-10-20 07:00:00'));
    }

    private function diet(string $room, array $timeFood = ['desayuno']): Diet
    {
        return app(DietService::class)->createDiet(
            ['habitation' => $room, 'name_patient' => "Paciente {$room}", 'timeFood' => $timeFood, 'billing_type' => 'normal'],
            $this->nutritionist->id,
        );
    }

    private function edit(Diet $diet, array $data): void
    {
        app(DietService::class)->createNewVersion($diet, array_merge(['billing_type' => 'normal', 'changes' => 'con_cambios'], $data), $this->nutritionist->id);
    }

    public function test_page_uses_the_content_wrapper_below_the_fixed_menu(): void
    {
        $this->actingAs($this->dispatcher)->get(route('dashboard.dispatch'))
            ->assertOk()
            ->assertSee('class="content" id="page-despacho"', false)
            ->assertSee('Dietas activas (0)');
    }

    public function test_changed_diet_is_highlighted_first_and_shows_what_changed(): void
    {
        $this->diet('101');
        $changed = $this->diet('900', ['desayuno', 'almuerzo']);
        $this->edit($changed, ['timeFood' => ['desayuno', 'cena'], 'observations' => 'Sin sal']);

        Livewire::actingAs($this->dispatcher)->test(DietsManager::class)
            ->assertSet('pendingCount', 1)
            ->assertSee('1 dieta cambió')
            ->assertSee('Cambio sin confirmar')
            ->assertSeeInOrder(['Paciente 900', 'Paciente 101']) // pendiente arriba aunque 101 < 900
            ->assertSeeHtml('dm-added')
            ->assertSee('+ Cena')
            ->assertSee('Antes: —');
    }

    public function test_dispatcher_confirms_all_pending_versions(): void
    {
        $diet = $this->diet('305');
        $this->edit($diet, ['timeFood' => ['almuerzo']]);
        $this->edit($diet, ['timeFood' => ['cena']]);

        Livewire::actingAs($this->dispatcher)->test(DietsManager::class)
            ->assertSet('pendingCount', 1)
            ->call('confirmChanges', $diet->id)
            ->assertDispatched('diet-change-confirmed', room: '305')
            ->assertSet('pendingCount', 0)
            ->assertDontSee('Cambio sin confirmar');

        $this->assertSame(0, $diet->history()->where('modify', 'si')->count());
        $this->assertSame(3, $diet->history()->count()); // no se crean ni borran versiones
    }

    public function test_compares_against_the_last_confirmed_version(): void
    {
        $diet = $this->diet('305', ['desayuno']);
        $this->edit($diet, ['timeFood' => ['desayuno', 'almuerzo']]);
        $this->edit($diet, ['timeFood' => ['desayuno', 'almuerzo', 'cena']]);

        // v1 confirmada → se ven almuerzo y cena como agregados
        Livewire::actingAs($this->dispatcher)->test(DietsManager::class)
            ->assertSee('+ Almuerzo')
            ->assertSee('+ Cena');
    }

    public function test_new_change_during_polling_raises_an_alert(): void
    {
        $diet = $this->diet('212');

        $component = Livewire::actingAs($this->dispatcher)->test(DietsManager::class)
            ->call('checkPending')
            ->assertNotDispatched('diet-changes-arrived');

        $this->edit($diet, ['timeFood' => ['cena']]);

        $component->call('checkPending')
            ->assertDispatched('diet-changes-arrived', count: 1, rooms: ['212'])
            ->call('checkPending')
            ->assertNotDispatched('diet-changes-arrived'); // no repite la alerta
    }

    public function test_cancellation_must_be_acknowledged(): void
    {
        $diet = $this->diet('118');
        app(DietService::class)->cancelDiet($diet, $this->nutritionist->id);

        Livewire::actingAs($this->dispatcher)->test(DietsManager::class)
            ->assertSee('Cancelada: no despachar')
            ->call('setTab', 'closed')
            ->assertSee('Cierre sin confirmar')
            ->call('confirmChanges', $diet->id)
            ->assertSet('pendingCount', 0);
    }

    public function test_only_users_with_confirm_delivery_can_confirm(): void
    {
        $diet = $this->diet('305');
        $this->edit($diet, ['timeFood' => ['cena']]);

        Livewire::actingAs($this->nutritionist)->test(DietsManager::class)
            ->call('confirmChanges', $diet->id)
            ->assertForbidden();

        $this->assertSame(1, $diet->history()->where('modify', 'si')->count());
    }

    public function test_table_is_paginated_by_15_and_keeps_the_search(): void
    {
        foreach (range(1, 20) as $i) {
            $this->diet(sprintf('%03d', $i));
        }

        $component = Livewire::actingAs($this->dispatcher)->test(DietsManager::class)
            ->assertSee('1–15 de 20 registros')
            ->assertSee('Paciente 015')
            ->assertDontSee('Paciente 016')
            ->call('gotoPage', 2)
            ->assertSee('16–20 de 20 registros')
            ->assertSee('Paciente 020');

        $component->set('search', '00')->assertSet('paginators.page', 1)->assertSee('1–9 de 9 registros');
    }

    public function test_invalid_per_page_falls_back_to_15(): void
    {
        Livewire::withQueryParams(['per_page' => 1000])->actingAs($this->dispatcher)
            ->test(DietsManager::class)
            ->assertSet('perPage', 15);
    }
}
