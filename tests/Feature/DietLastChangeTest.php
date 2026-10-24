<?php

namespace Tests\Feature;

use App\Livewire\DietsManager;
use App\Models\User;
use App\Services\DietDayService;
use App\Services\DietHistoryService;
use App\Services\DietService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SCRUM-28: la columna «Último cambio» del despachador muestra el cambio real del historial
 * (antes, «Sin tipo» en todas las filas).
 */
class DietLastChangeTest extends TestCase
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

    private function data(array $override = []): array
    {
        return ['habitation' => '301', 'name_patient' => 'Paciente', 'timeFood' => ['desayuno'], 'consistency' => ['normal'],
            'specifications' => ['sin_gluten'], 'isolation' => 'No', ...$override];
    }

    public function test_label_describes_the_real_last_change(): void
    {
        $service = app(DietService::class);
        $history = app(DietHistoryService::class);
        $diet = $service->createDiet($this->data(), $this->nutritionist->id);
        $this->assertSame('Creación', $history->lastChangeLabel($diet->fresh()));

        $service->createNewVersion($diet, $this->data(['specifications' => ['hiposodica'], 'changes' => 'NA']), $this->nutritionist->id);
        $this->assertSame('Cambió especificaciones y tipo de dieta', $history->lastChangeLabel($diet->fresh()));

        $service->createNewVersion($diet, $this->data(['specifications' => ['hiposodica'], 'timeFood' => ['desayuno', 'cena'], 'isolation' => 'Sí']), $this->nutritionist->id);
        $this->assertSame('Cambió tiempos de comida y aislamiento', $history->lastChangeLabel($diet->fresh()));

        // Continuar dieta crea una copia del día: no es un cambio
        app(DietDayService::class)->continueDiet($diet->fresh(), $this->nutritionist->id);
        $this->assertSame('Cambió tiempos de comida y aislamiento', $history->lastChangeLabel($diet->fresh()));

        $service->cancelDiet($diet->fresh(), $this->nutritionist->id);
        $this->assertSame('Cancelada', $history->lastChangeLabel($diet->fresh()));
    }

    public function test_dispatcher_column_shows_the_change_instead_of_sin_tipo(): void
    {
        $service = app(DietService::class);
        $diet = $service->createDiet($this->data(), $this->nutritionist->id);
        $service->createNewVersion($diet, $this->data(['specifications' => ['hiposodica']]), $this->nutritionist->id);

        Livewire::actingAs(User::role('despachador')->firstOrFail())->test(DietsManager::class)
            ->assertSee('Cambió especificaciones y tipo de dieta')
            ->assertDontSee('Sin tipo');
    }
}
