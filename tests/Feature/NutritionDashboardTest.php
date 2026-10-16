<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DietService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_tab_tables_are_inside_the_alpine_scope(): void
    {
        $this->createDiets(2);
        $dom = new \DOMDocument;
        @$dom->loadHTML($this->dashboard());
        $xpath = new \DOMXPath($dom);

        // Un </div> sobrante en un modal cerraba el x-data antes de las tablas y los filtros no hacían nada
        foreach (['active', 'cancelled', 'finished'] as $tab) {
            $panels = $xpath->query("//*[@x-show=\"tab === '{$tab}'\"]");
            $this->assertSame(1, $panels->length, $tab);
            $this->assertSame(1, $xpath->query('ancestor::*[@x-data]', $panels->item(0))->length, $tab);
        }
    }

    public function test_active_table_is_paginated_and_counts_use_totals(): void
    {
        $this->createDiets(17);

        $first = $this->dashboard();
        $this->assertStringContainsString('Activas (17)', $first);
        $this->assertStringContainsString('Mostrando 1–15 de 17', $first);
        $this->assertSame(15, substr_count($first, 'id="modalEditOverlay'));

        $second = $this->dashboard(['activas' => 2, 'tab' => 'active']);
        $this->assertStringContainsString('Mostrando 16–17 de 17', $second);
        $this->assertSame(2, substr_count($second, 'id="modalEditOverlay'));
    }

    public function test_page_links_keep_the_selected_tab(): void
    {
        $this->createDiets(16);

        $html = html_entity_decode($this->dashboard(['tab' => 'cancelled']));
        $this->assertStringContainsString('tab: \'cancelled\'', $html);
        $this->assertMatchesRegularExpression('/activas=2[^"]*tab=active|tab=active[^"]*activas=2/', $html);
    }

    public function test_unknown_tab_falls_back_to_active(): void
    {
        $this->assertStringContainsString("tab: 'active'", html_entity_decode($this->dashboard(['tab' => 'x'])));
    }
}
