<?php

namespace Tests\Feature;

use App\Events\DietsChanged;
use App\Exports\MonthlySummaryExport;
use App\Livewire\DietHistoryViewer;
use App\Livewire\DietsManager;
use App\Livewire\DispatchLog;
use App\Livewire\DispatchReceipts;
use App\Livewire\NutritionDiets;
use App\Models\Company;
use App\Models\Diet;
use App\Models\DietDispatch;
use App\Models\User;
use App\Services\DietService;
use App\Services\DispatchService;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use LogicException;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/** NUT-RN-003 §4 y §5 (SCRUM-8): selector de empresa y aislamiento de datos entre empresas */
class CompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $nutritionist;

    private User $dispatcher;

    private Company $a;

    private Company $b;

    private Diet $dietA;

    private Diet $dietB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activatePremium();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:30:00'));

        $this->nutritionist = User::role('nutricionista')->firstOrFail();
        $this->dispatcher = User::role('despachador')->firstOrFail();
        $this->a = Company::default();
        $this->a->update(['name' => 'Clínica Alfa']);
        $this->b = Company::create(['name' => 'Clínica Beta', 'nit' => '860002964-4', 'contact_name' => 'B',
            'contact_phone' => '3001234567', 'contact_email' => 'b@example.com']);

        // El despachador atiende ambas empresas (empresas específicas, NUT-RN-003-24)
        $this->dispatcher->companies()->sync([$this->a->id, $this->b->id]);

        // Datos de ambas empresas, creados sin usuario autenticado (sin filtro)
        $this->dietA = $this->diet('101', $this->a);
        $this->dietB = $this->diet('202', $this->b);
    }

    private function diet(string $room, Company $company): Diet
    {
        return app(DietService::class)->createDiet(
            ['company_id' => $company->id, 'habitation' => $room, 'name_patient' => "Paciente {$room}",
                'timeFood' => ['desayuno', 'almuerzo'], 'consistency' => ['normal'], 'specifications' => ['hiposodica'], 'billing_type' => 'normal'],
            $this->nutritionist->id,
        );
    }

    /** Cambia la empresa activa del usuario como lo haría el selector */
    private function useCompany(User $user, Company $company): void
    {
        $this->actingAs($user);
        app(CurrentCompany::class)->switch($company->id);
    }

    /** Versión nueva sin confirmar (cambio pendiente para el despachador) */
    private function pendingChange(Diet $diet): void
    {
        app(DietService::class)->createNewVersion($diet,
            ['habitation' => $diet->habitation, 'name_patient' => $diet->name_patient, 'timeFood' => ['almuerzo'], 'consistency' => ['normal'], 'specifications' => ['hiposodica'], 'billing_type' => 'normal'],
            $this->nutritionist->id);
    }

    public function test_default_company_is_used_and_selector_lists_assigned_companies(): void
    {
        $this->actingAs($this->nutritionist)->get(route('dashboard.nutrition'))
            ->assertOk()
            ->assertSee('Cambiar empresa')
            ->assertSee('Clínica Alfa')
            ->assertSee('Clínica Beta')
            ->assertSee('Paciente 101')
            ->assertDontSee('Paciente 202');
    }

    public function test_switching_company_persists_across_modules_and_resets_the_query(): void
    {
        $this->actingAs($this->nutritionist)
            ->from(route('dashboard.nutrition', ['q' => '101', 'page' => 2]))
            ->post(route('company.switch'), ['company_id' => $this->b->id])
            ->assertRedirect(route('dashboard.nutrition')); // sin ?q ni ?page

        $this->get(route('dashboard.nutrition'))->assertSee('Paciente 202')->assertDontSee('Paciente 101');
        $this->get(route('diets.history'))->assertSee('Paciente 202')->assertDontSee('Paciente 101');
        $this->assertSame($this->b->id, $this->nutritionist->fresh()->last_company_id);
    }

    public function test_last_used_company_is_restored_in_a_new_session(): void
    {
        $this->nutritionist->forceFill(['last_company_id' => $this->b->id])->save();

        $this->actingAs($this->nutritionist)->get(route('dashboard.nutrition'))
            ->assertSee('Paciente 202')
            ->assertDontSee('Paciente 101');
    }

    public function test_cannot_switch_to_an_unassigned_or_deactivated_company(): void
    {
        $limited = User::factory()->create(['all_companies' => false]);
        $limited->assignRole('nutricionista');
        $limited->companies()->sync([$this->a->id]);

        $this->actingAs($limited)->post(route('company.switch'), ['company_id' => $this->b->id])->assertForbidden();

        // Con una sola empresa no hay selector, solo su nombre
        $this->actingAs($limited)->get(route('dashboard.nutrition'))->assertDontSee('Cambiar empresa')->assertSee('Clínica Alfa');

        $this->flushSession();
        $this->b->update(['is_active' => false]);
        $this->actingAs($this->nutritionist)->post(route('company.switch'), ['company_id' => $this->b->id])->assertForbidden();
    }

    public function test_requests_on_a_diet_of_another_company_are_forbidden(): void
    {
        $data = ['habitation' => '202', 'name_patient' => 'X', 'timeFood' => ['cena'], 'consistency' => ['normal'], 'specifications' => ['hiposodica'], 'billing_type' => 'normal'];

        $this->actingAs($this->nutritionist)->put(route('dietas.update', $this->dietB), $data)->assertForbidden();
        $this->actingAs($this->nutritionist)->patch(route('diets.canceled', $this->dietB))->assertForbidden();
        $this->actingAs($this->nutritionist)->get(route('diets.history', ['dieta' => $this->dietB->id]))->assertForbidden();

        Livewire::actingAs($this->nutritionist)->test(NutritionDiets::class)
            ->call('startClose', $this->dietB->id, 'cancel')->assertForbidden();
        Livewire::actingAs($this->dispatcher)->test(DietsManager::class)
            ->call('confirmChanges', $this->dietB->id)->assertForbidden();
        Livewire::actingAs($this->nutritionist)->test(DietHistoryViewer::class)
            ->call('select', $this->dietB->id)->assertForbidden();

        $this->assertSame(1, $this->dietB->fresh()->history()->count());
    }

    public function test_new_diet_belongs_to_the_selected_company_and_cannot_move(): void
    {
        $this->useCompany($this->nutritionist, $this->b);

        $this->post(route('dietas.create'), ['habitation' => '303', 'name_patient' => 'Nuevo', 'timeFood' => ['desayuno'], 'consistency' => ['normal'], 'specifications' => ['hiposodica'], 'billing_type' => 'normal'])
            ->assertRedirect();

        $diet = Diet::where('habitation', '303')->firstOrFail();
        $this->assertSame($this->b->id, $diet->company_id);

        // Una versión nueva no cambia la empresa
        $this->put(route('dietas.update', $diet), ['habitation' => '303', 'name_patient' => 'Nuevo', 'timeFood' => ['cena'], 'consistency' => ['normal'], 'specifications' => ['hiposodica'], 'billing_type' => 'normal']);
        $this->assertSame($this->b->id, $diet->fresh()->company_id);
        $this->assertSame([$this->b->id], $diet->history()->with('diet')->get()->pluck('diet.company_id')->unique()->values()->all());

        $this->expectException(LogicException::class);
        $diet->update(['company_id' => $this->a->id]);
    }

    public function test_dispatcher_sees_only_pending_changes_of_the_selected_company(): void
    {
        $this->pendingChange($this->dietA);
        $this->pendingChange($this->dietB);

        $this->useCompany($this->dispatcher, $this->a);
        Livewire::test(DietsManager::class)
            ->assertSet('pendingCount', 1)
            ->assertSee('Paciente 101')
            ->assertDontSee('Paciente 202');

        $this->useCompany($this->dispatcher, $this->b);
        Livewire::test(DietsManager::class)
            ->assertSet('pendingCount', 1)
            ->assertSee('Paciente 202')
            ->assertDontSee('Paciente 101');
    }

    public function test_dispatch_only_applies_to_the_selected_company(): void
    {
        $this->useCompany($this->dispatcher, $this->a);

        $result = app(DispatchService::class)->dispatch([$this->dietA->id, $this->dietB->id], 'almuerzo', $this->dispatcher->id);

        $this->assertSame(['101'], array_values($result['dispatched']));
        $this->assertSame(1, DietDispatch::withoutGlobalScopes()->count());
    }

    public function test_dispatch_log_and_receipts_do_not_mix_companies(): void
    {
        // Despachos de ambas empresas (sin usuario: sin filtro)
        app(DispatchService::class)->dispatch([$this->dietA->id, $this->dietB->id], 'almuerzo', $this->dispatcher->id);
        $dispatchB = DietDispatch::where('diet_id', $this->dietB->id)->firstOrFail();

        $this->useCompany($this->nutritionist, $this->a);
        Livewire::test(DispatchLog::class)->assertSee('101')->assertDontSee('Paciente 202');
        Livewire::test(DispatchReceipts::class)
            ->assertSee('101')
            ->assertDontSee('Paciente 202')
            ->set('selected', [$dispatchB->id])
            ->call('confirmReceipt');

        $this->assertNull($dispatchB->fresh()->received_at); // no se puede recibir lo de otra empresa
    }

    public function test_monthly_excel_only_includes_the_selected_company(): void
    {
        Excel::fake();
        // Sin la regla de lo recibido: cuenta lo programado de ambas empresas
        config(['dispatch.required_from' => null, 'dispatch.completion_required_from' => null]);
        $this->useCompany($this->nutritionist, $this->b);

        $this->get(route('reports.monthly.excel', ['mes' => '2026-10']))->assertOk();

        Excel::assertDownloaded('reporte-nutricional_clinica-beta_2026-10_20261020_1230.xlsx', function (MonthlySummaryExport $export) {
            $perDiet = collect($export->sheets()[2]->array());

            return $perDiet->contains(fn ($row) => in_array('202', $row, true))
                && ! $perDiet->contains(fn ($row) => in_array('101', $row, true));
        });
    }

    public function test_live_updates_carry_only_the_company_of_the_change(): void
    {
        Event::fake([DietsChanged::class]);

        app(DietService::class)->confirmChanges($this->dietB);

        Event::assertDispatched(DietsChanged::class, fn (DietsChanged $e) => $e->companies === [$this->b->id]
            && $e->broadcastWith() === ['kinds' => ['diet'], 'companies' => [$this->b->id]]);
    }
}
