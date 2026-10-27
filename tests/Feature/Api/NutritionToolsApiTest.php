<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
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
 * «Historial de cambios» y «Reporte nutricional» en la API v1: solo para los roles de
 * nutrición (config desktop.nutrition_roles), el historial sin Premium.
 */
class NutritionToolsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $nutritionist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->nutritionist = User::role('nutricionista')->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:30:00'));
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user, ['*']);

        return $this;
    }

    private function diet(string $room, array $override = []): Diet
    {
        return app(DietService::class)->createDiet(['habitation' => $room, 'name_patient' => 'Paciente '.$room, 'timeFood' => ['almuerzo', 'cena'],
            'consistency' => ['normal'], 'specifications' => ['hiposodica'], 'billing_type' => 'normal', 'isolation' => 'No', ...$override], $this->nutritionist->id);
    }

    public function test_only_nutrition_roles_have_the_tools(): void
    {
        $this->as($this->nutritionist)->getJson(route('api.v1.me'))->assertJsonPath('nutrition_tools', true);
        $this->as(User::role('jefe_nutricion')->firstOrFail())->getJson(route('api.v1.me'))->assertJsonPath('nutrition_tools', true);

        // El despachador tiene viewDiets y el jefe de despacho viewReports, pero en la app no
        foreach (['despachador', 'jefe_despacho'] as $role) {
            $user = User::role($role)->firstOrFail();
            $this->as($user)->getJson(route('api.v1.me'))->assertJsonPath('nutrition_tools', false);
            $this->getJson(route('api.v1.history.diets'))->assertForbidden();
            $this->getJson(route('api.v1.reports.monthly'))->assertForbidden();
            $this->getJson(route('api.v1.reports.monthly.excel'))->assertForbidden();
        }
    }

    public function test_history_lists_diets_and_shows_the_timeline_without_premium(): void
    {
        $diet = $this->diet('301');
        $this->diet('302');
        app(DietService::class)->createNewVersion($diet, ['timeFood' => ['cena'], 'consistency' => ['blanda_mecanica'], 'specifications' => ['hiposodica'],
            'billing_type' => 'normal', 'isolation' => 'Sí', 'changes' => 'con_cambios'], $this->nutritionist->id);

        $this->as($this->nutritionist)->getJson(route('api.v1.history.diets', ['q' => '301']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.habitation', '301')
            ->assertJsonPath('data.0.versions', 2);

        $this->getJson(route('api.v1.diets.history', $diet->id))
            ->assertOk()
            ->assertJsonPath('diet.habitation', '301')
            ->assertJsonPath('versions.0.version', 2)
            ->assertJsonPath('versions.0.type', 'edicion')
            ->assertJsonPath('versions.0.updated_by', $this->nutritionist->name)
            ->assertJsonPath('versions.0.fields.timeFood.changed', true)
            ->assertJsonPath('versions.0.fields.isolation.old', 'No')
            ->assertJsonPath('versions.0.fields.isolation.value', 'Sí')
            ->assertJsonPath('versions.1.type', 'creacion')
            ->assertJsonMissingPath('versions.0.updated_by_email');

        // Ley 1581: queda quién consultó el historial, sin el nombre del paciente
        $log = AuditLog::where('event', 'history_viewed')->latest('id')->firstOrFail();
        $this->assertSame('desktop', $log->meta['client']);
        $this->assertStringNotContainsString('Paciente', $log->description);

        // Otra empresa: 403
        $other = Company::create(['name' => 'Clínica Norte', 'nit' => '900123457-1', 'contact_name' => 'X', 'contact_phone' => '3000000000', 'contact_email' => 'x@clinica.org', 'is_active' => true]);
        $foreign = Diet::withoutGlobalScopes()->create(['habitation' => '999', 'name_patient' => 'Ajeno', 'status' => Diet::ACTIVE, 'company_id' => $other->id]);
        $this->getJson(route('api.v1.diets.history', $foreign->id))->assertForbidden();
    }

    public function test_monthly_report_compares_scheduled_and_received_and_downloads_the_excel(): void
    {
        $diet = $this->diet('401');
        app(DispatchService::class)->dispatch([$diet->id], 'almuerzo', User::role('despachador')->firstOrFail()->id);
        app(DispatchService::class)->confirmReceipt(DietDispatch::where('diet_id', $diet->id)->pluck('id')->all(), $this->nutritionist->id);

        $response = $this->as($this->nutritionist)->getJson(route('api.v1.reports.monthly', ['mes' => '2026-10']))
            ->assertOk()
            ->assertJsonPath('month', '2026-10')
            ->assertJsonPath('label', 'Octubre 2026')
            ->assertJsonPath('kpis.active_now', 1)
            ->assertJsonPath('kpis.received_trays', 1);
        $lunch = collect($response->json('meals'))->firstWhere('key', 'almuerzo');
        $this->assertSame(1, $lunch['received']);
        $this->assertGreaterThanOrEqual(1, $lunch['scheduled']);

        $this->getJson(route('api.v1.reports.monthly', ['mes' => '2026-12']))->assertStatus(422);

        $excel = $this->get(route('api.v1.reports.monthly.excel', ['mes' => '2026-10']))->assertOk();
        $this->assertStringContainsString('reporte-nutricional_', $excel->headers->get('content-disposition'));
        $this->assertTrue(AuditLog::where('event', 'export')->exists());
    }
}
