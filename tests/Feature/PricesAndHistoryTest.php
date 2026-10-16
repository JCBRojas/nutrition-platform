<?php

namespace Tests\Feature;

use App\Livewire\DietHistoryViewer;
use App\Livewire\PricesManager;
use App\Models\Diet;
use App\Models\DietHistory;
use App\Models\ServicePrice;
use App\Models\User;
use App\Services\DietHistoryService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PricesAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $jefe;

    private User $nutritionist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->jefe = User::role('jefe_nutricion')->firstOrFail();
        $this->nutritionist = User::role('nutricionista')->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));
    }

    private function diet(string $at, array $timeFood): Diet
    {
        $diet = Diet::create(['habitation' => '101', 'name_patient' => 'Paciente', 'status' => true]);
        $diet->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        $this->version($diet, 1, $at, $timeFood);

        return $diet;
    }

    private function version(Diet $diet, int $n, string $at, array $timeFood, array $extra = []): void
    {
        $h = DietHistory::create(array_merge([
            'diet_id' => $diet->id, 'updated_by' => $this->nutritionist->id, 'version' => $n,
            'timeFood' => $timeFood, 'modify' => 'no',
        ], $extra));
        $h->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
    }

    /* ===================== PRECIOS ===================== */

    public function test_only_jefe_can_manage_prices_and_lands_there_after_login(): void
    {
        $this->actingAs($this->jefe)->get(route('prices.index'))->assertOk()->assertSee('Precios vigentes hoy');
        $this->assertSame(route('prices.index'), $this->jefe->homeUrl());

        $this->flushSession();
        $this->actingAs($this->nutritionist)->get(route('prices.index'))->assertForbidden();
    }

    public function test_jefe_sets_price_for_type_and_service(): void
    {
        Livewire::actingAs($this->jefe)->test(PricesManager::class)
            ->call('edit', 'hipoglucida', 'almuerzo')
            ->set('price', '17.496')
            ->set('validFrom', '2026-10-01')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editType', '');

        $price = ServicePrice::firstOrFail();
        $this->assertSame(['hipoglucida', 'almuerzo', '17496.00'], [$price->billing_type, $price->service, $price->price]);
        $this->assertSame($this->jefe->id, $price->created_by);
    }

    public function test_price_for_a_service_the_type_does_not_bill_is_rejected(): void
    {
        Livewire::actingAs($this->jefe)->test(PricesManager::class)
            ->call('edit', 'normal', 'merienda') // Normal no factura merienda (va a Entrecomidas)
            ->set('price', '9000')
            ->call('save')
            ->assertHasErrors('editService');

        Livewire::actingAs($this->jefe)->test(PricesManager::class)
            ->call('edit', 'normal', 'desayuno')
            ->set('price', 'diez mil')
            ->call('save')
            ->assertHasErrors('price');

        $this->assertSame(0, ServicePrice::count());
    }

    /* ===================== HISTORIAL ===================== */

    public function test_history_timeline_marks_added_removed_and_changed_fields(): void
    {
        $diet = $this->diet('2026-10-01 08:00:00', ['desayuno', 'almuerzo']);
        $this->version($diet, 2, '2026-10-03 08:00:00', ['almuerzo', 'media_manana'], ['isolation' => 'Sí', 'changes' => 'aislado']);
        $this->version($diet, 3, '2026-10-05 08:00:00', ['almuerzo', 'media_manana'], ['isolation' => 'Sí', 'changes' => 'cancelada']);

        $timeline = app(DietHistoryService::class)->timeline($diet);

        $this->assertSame([3, 2, 1], $timeline->pluck('version.version')->all());
        $this->assertSame(['cancelacion', 'edicion', 'creacion'], $timeline->pluck('type')->all());

        $v2 = $timeline[1]['fields'];
        $states = collect($v2['timeFood']['items'])->pluck('state', 'label')->all();
        $this->assertSame(['Almuerzo' => 'kept', 'Media mañana' => 'added', 'Desayuno' => 'removed'], $states);
        $this->assertTrue($v2['timeFood']['changed']);
        $this->assertTrue($v2['isolation']['changed']);
        $this->assertSame('No', $v2['isolation']['old']);
        $this->assertFalse($timeline[0]['fields']['timeFood']['changed']);
    }

    public function test_history_page_shows_selected_diet_for_both_roles(): void
    {
        $diet = $this->diet('2026-10-01 08:00:00', ['desayuno']);

        $this->actingAs($this->nutritionist)->get(route('diets.history', ['dieta' => $diet->id]))
            ->assertOk()->assertSee('v1')->assertSee('Creación');

        $this->flushSession();
        $this->actingAs(User::role('despachador')->first())->get(route('diets.history'))
            ->assertOk()->assertSee('Selecciona una dieta');

        Livewire::actingAs($this->nutritionist)->test(DietHistoryViewer::class)
            ->set('search', '101')
            ->call('select', $diet->id)
            ->assertSet('dietId', $diet->id)
            ->assertSee('Paciente');
    }

    public function test_nutritionist_table_links_to_history(): void
    {
        $diet = $this->diet('2026-10-01 08:00:00', ['desayuno']);

        $this->actingAs($this->nutritionist)->get(route('dashboard.nutrition'))
            ->assertOk()
            ->assertSee(route('diets.history', ['dieta' => $diet->id]), false)
            ->assertSee('row-actions', false);
    }
}
