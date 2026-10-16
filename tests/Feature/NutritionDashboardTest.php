<?php

namespace Tests\Feature;

use App\Livewire\NutritionDiets;
use App\Models\User;
use App\Services\DietService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NutritionDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $nutritionist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->nutritionist = User::role('nutricionista')->firstOrFail();
    }

    private function createDiets(int $count): void
    {
        foreach (range(1, $count) as $i) {
            app(DietService::class)->createDiet(
                ['habitation' => (string) (100 + $i), 'name_patient' => "Paciente {$i}", 'timeFood' => ['desayuno'], 'billing_type' => 'normal'],
                $this->nutritionist->id,
            );
        }
    }

    private function dashboard(array $query = []): string
    {
        return $this->actingAs($this->nutritionist)
            ->get(route('dashboard.nutrition', $query))
            ->assertOk()
            ->getContent();
    }

    public function test_edit_modals_survive_the_auto_refresh(): void
    {
        $this->createDiets(2);
        $dom = new \DOMDocument;
        @$dom->loadHTML($this->dashboard());
        $xpath = new \DOMXPath($dom);

        // El refresco automático no debe tocar un modal abierto
        $this->assertSame(2, $xpath->query('//*[starts-with(@id, "modalEditOverlay")][@*[name()="wire:ignore"]]')->length);
        // El modal de registro queda fuera del componente que se refresca
        $this->assertSame(1, $xpath->query('//*[@id="modalOverlay"]')->length);
        $this->assertSame(0, $xpath->query('//*[@*[name()="wire:snapshot"]]//*[@id="modalOverlay"]')->length);
    }

    public function test_active_table_is_paginated_and_counts_use_totals(): void
    {
        $this->createDiets(17);

        $first = $this->dashboard();
        $this->assertStringContainsString('Activas (17)', $first);
        $this->assertStringContainsString('1–15 de 17 registros', $first);
        $this->assertSame(15, substr_count($first, 'id="modalEditOverlay'));

        $second = $this->dashboard(['page' => 2, 'tab' => 'active']);
        $this->assertStringContainsString('16–17 de 17 registros', $second);
        $this->assertSame(2, substr_count($second, 'id="modalEditOverlay'));
    }

    public function test_changing_tab_or_search_returns_to_page_one(): void
    {
        $this->createDiets(16);

        Livewire::actingAs($this->nutritionist)->withQueryParams(['page' => 2])
            ->test(NutritionDiets::class)
            ->assertSee('16–16 de 16 registros')
            ->call('setTab', 'cancelled')
            ->assertSet('tab', 'cancelled')
            ->assertSet('paginators.page', 1)
            ->call('setTab', 'active')
            ->call('gotoPage', 2)
            ->set('search', 'Paciente')
            ->assertSet('paginators.page', 1);
    }

    public function test_unknown_tab_falls_back_to_active(): void
    {
        Livewire::actingAs($this->nutritionist)->withQueryParams(['tab' => 'x'])
            ->test(NutritionDiets::class)
            ->assertSet('tab', 'active');
    }

    public function test_search_filters_in_real_time(): void
    {
        $this->createDiets(3);

        Livewire::actingAs($this->nutritionist)->test(NutritionDiets::class)
            ->assertSee('Paciente 1')
            ->assertSee('Paciente 2')
            ->set('search', '102')
            ->assertSee('Paciente 2')
            ->assertDontSee('Paciente 3')
            ->assertSee('1 resultado(s) para «102»')
            ->set('search', 'nadie')
            ->assertSee('No hay dietas activas que coincidan con «nadie».');
    }

    public function test_table_shows_changes_on_refresh_without_reloading(): void
    {
        $this->createDiets(1);
        $component = Livewire::actingAs($this->nutritionist)->test(NutritionDiets::class)
            ->assertSee('Activas (1)')
            ->assertDontSee('Paciente Nuevo');

        // Otro usuario registra y cancela dietas mientras la página está abierta
        $diet = app(DietService::class)->createDiet(
            ['habitation' => '900', 'name_patient' => 'Paciente Nuevo', 'timeFood' => ['almuerzo'], 'billing_type' => 'normal'],
            $this->nutritionist->id,
        );
        $component->call('$refresh')
            ->assertSee('Paciente Nuevo')
            ->assertSee('Activas (2)');

        app(DietService::class)->cancelDiet($diet, $this->nutritionist->id);
        $component->call('$refresh')
            ->assertDontSee('Paciente Nuevo')
            ->assertSee('Activas (1)')
            ->assertSee('Canceladas (1)');
    }

    public function test_page_polls_and_pauses_while_a_modal_is_open(): void
    {
        $html = $this->dashboard();

        $this->assertStringContainsString('$wire.$refresh()', html_entity_decode($html));
        $this->assertStringContainsString('.overlay.open, .chip-select.open', html_entity_decode($html));
    }
}
