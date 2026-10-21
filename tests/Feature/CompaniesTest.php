<?php

namespace Tests\Feature;

use App\Livewire\Admin\UsersManager;
use App\Livewire\CompaniesManager;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Diet;
use App\Models\User;
use App\Rules\Nit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** NUT-RN-003 §2 y §3 (SCRUM-7): empresas, jefe de despacho y asignación de usuarios */
class CompaniesTest extends TestCase
{
    use RefreshDatabase;

    private User $jefeDespacho;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activatePremium(); // empresas es función Premium (NUT-RN-003-16)
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->jefeDespacho = User::role('jefe_despacho')->firstOrFail();
    }

    private function company(string $name, string $nit, bool $active = true, ?User $manager = null): Company
    {
        $company = new Company([
            'name' => $name, 'nit' => $nit, 'contact_name' => 'Contacto', 'contact_phone' => '+573001234567',
            'contact_email' => 'contacto@example.com', 'is_active' => $active,
        ]);
        $company->manager_id = ($manager ?? $this->jefeDespacho)->id;
        $company->save();

        return $company;
    }

    private function fillForm($component, string $name, string $nit)
    {
        return $component->call('create')
            ->set('name', $name)
            ->set('nit', $nit)
            ->set('contact_name', 'Ana Pérez')
            ->set('contact_phone', '+573001234567')
            ->set('contact_email', 'Ana@Clinica.com');
    }

    public function test_migration_creates_default_company_and_existing_diets_belong_to_it(): void
    {
        $default = Company::default();

        $this->assertNotNull($default);
        $this->assertTrue($default->is_active);
        $this->assertSame($default->id, Diet::factory()->create()->company_id);
    }

    public function test_nit_check_digit(): void
    {
        $this->assertSame(8, Nit::checkDigit('900123456'));
        $this->assertSame(4, Nit::checkDigit('860002964'));
        $this->assertTrue(validator(['nit' => '900.123.456-8'], ['nit' => [new Nit]])->passes());
        $this->assertFalse(validator(['nit' => '900123456-7'], ['nit' => [new Nit]])->passes());
        $this->assertFalse(validator(['nit' => '900123456'], ['nit' => [new Nit]])->passes());
    }

    public function test_jefe_de_despacho_creates_a_company_and_it_is_audited(): void
    {
        $this->actingAs($this->jefeDespacho)->get(route('companies.index'))->assertOk()->assertSee('Nueva empresa');

        $this->fillForm(Livewire::actingAs($this->jefeDespacho)->test(CompaniesManager::class), 'Clínica Norte', '900.123.456-8')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $company = Company::where('name', 'Clínica Norte')->firstOrFail();
        $this->assertSame('900123456-8', $company->nit);
        $this->assertSame('ana@clinica.com', $company->contact_email);
        $this->assertTrue(AuditLog::where('event', 'company_created')->where('subject_id', $company->id)->exists());
    }

    public function test_duplicate_name_and_nit_are_rejected_in_spanish(): void
    {
        $this->company('Clínica Norte', '900123456-8');

        $this->fillForm(Livewire::actingAs($this->jefeDespacho)->test(CompaniesManager::class), 'Clínica Norte', '900123456-8')
            ->call('save')
            ->assertHasErrors(['name' => 'unique', 'nit' => 'unique'])
            ->assertSee('Ya existe una empresa con este nombre.')
            ->assertSee('Ya existe una empresa con este NIT.');
    }

    public function test_required_fields_and_check_digit_are_validated(): void
    {
        Livewire::actingAs($this->jefeDespacho)->test(CompaniesManager::class)
            ->call('create')
            ->set('nit', '900123456-7')
            ->set('contact_phone', '3001234567')
            ->call('save')
            ->assertHasErrors(['name', 'nit', 'contact_name', 'contact_phone', 'contact_email'])
            ->assertSee('El dígito de verificación del NIT no es válido.');
    }

    public function test_edit_and_deactivate_keep_history_and_are_audited(): void
    {
        $company = $this->company('Clínica Norte', '900123456-8');
        $diet = Diet::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($this->jefeDespacho)->test(CompaniesManager::class)
            ->call('edit', $company->id)
            ->set('contact_name', 'Nuevo contacto')
            ->call('save')
            ->assertHasNoErrors()
            ->call('toggleActive', $company->id);

        $company->refresh();
        $this->assertSame('Nuevo contacto', $company->contact_name);
        $this->assertFalse($company->is_active);
        $this->assertSame($company->id, $diet->fresh()->company_id);
        $this->assertTrue(AuditLog::where('event', 'company_updated')->where('subject_id', $company->id)->exists());
        $this->assertTrue(AuditLog::where('event', 'company_deactivated')->where('subject_id', $company->id)->exists());
    }

    public function test_default_company_cannot_be_deactivated(): void
    {
        $default = Company::default();

        Livewire::actingAs($this->jefeDespacho)->test(CompaniesManager::class)
            ->call('toggleActive', $default->id);

        $this->assertTrue($default->fresh()->is_active);
    }

    public function test_dispatcher_cannot_see_the_administration(): void
    {
        $dispatcher = User::role('despachador')->firstOrFail();

        $this->actingAs($dispatcher)->get(route('companies.index'))->assertForbidden();
        Livewire::actingAs($dispatcher)->test(CompaniesManager::class)->assertForbidden();
    }

    public function test_viewer_only_sees_assigned_companies(): void
    {
        $viewer = User::factory()->create(['all_companies' => false]);
        $viewer->givePermissionTo('viewCompanies');
        $assigned = $this->company('Clínica Norte', '900123456-8');
        $this->company('Clínica Sur', '800197268-4');
        $viewer->companies()->sync([$assigned->id]);

        $this->actingAs($viewer)->get(route('companies.index'))
            ->assertOk()->assertSee('Clínica Norte')->assertDontSee('Clínica Sur');
    }

    public function test_view_only_permission_cannot_create_or_edit(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('viewCompanies');
        $company = $this->company('Clínica Norte', '900123456-8');

        $this->actingAs($viewer)->get(route('companies.index'))->assertOk()->assertDontSee('Nueva empresa');
        Livewire::actingAs($viewer)->test(CompaniesManager::class)->call('create')->assertForbidden();
        Livewire::actingAs($viewer)->test(CompaniesManager::class)->call('toggleActive', $company->id)->assertForbidden();
    }

    public function test_admin_assigns_specific_companies_or_all(): void
    {
        $admin = User::role('admin')->firstOrFail();
        $a = $this->company('Clínica A', '900123456-8');
        $b = $this->company('Clínica B', '860002964-4');

        Livewire::actingAs($admin)->test(UsersManager::class)
            ->call('create')
            ->set('name', 'Despachador A')
            ->set('email', 'desp.a@example.com')
            ->set('password', 'Secreta-2026')
            ->set('password_confirmation', 'Secreta-2026')
            ->set('roles', ['despachador'])
            ->set('companies', [(string) $a->id])
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'desp.a@example.com')->firstOrFail();
        $this->assertFalse($user->all_companies);
        $this->assertSame([$a->id], $user->selectableCompanies()->pluck('id')->all());
        $this->assertFalse($user->canAccessCompany($b));

        // «Todas» no aplica al despachador (NUT-RN-003-24): se prueba con un nutricionista
        Livewire::actingAs($admin)->test(UsersManager::class)
            ->call('edit', $user->id)
            ->set('roles', ['nutricionista'])
            ->set('all_companies', true)
            ->call('save')
            ->assertHasNoErrors();

        // «Todas» incluye las empresas creadas después
        $c = $this->company('Clínica C', '890900608-9');
        $this->assertTrue($user->fresh()->canAccessCompany($c));
        $this->assertSame(0, $user->fresh()->companies()->count());
    }

    public function test_user_needs_at_least_one_company_or_all(): void
    {
        $admin = User::role('admin')->firstOrFail();

        Livewire::actingAs($admin)->test(UsersManager::class)
            ->call('create')
            ->set('name', 'Sin empresa')
            ->set('email', 'sin.empresa@example.com')
            ->set('password', 'Secreta-2026')
            ->set('password_confirmation', 'Secreta-2026')
            ->set('roles', ['despachador'])
            ->call('save')
            ->assertHasErrors('user');

        $this->assertDatabaseMissing('users', ['email' => 'sin.empresa@example.com']);
    }

    public function test_deactivated_company_is_not_selectable_but_admin_sees_all(): void
    {
        $inactive = $this->company('Clínica Cerrada', '900123456-8', active: false);
        $user = User::factory()->create(['all_companies' => true]);
        $admin = User::role('admin')->firstOrFail();

        $this->assertFalse($user->selectableCompanies()->whereKey($inactive->id)->exists());
        $this->assertTrue($user->canAccessCompany($inactive)); // su historial sigue accesible
        $this->assertTrue($admin->hasAllCompanies());
    }

    public function test_user_created_without_company_permission_inherits_creator_access(): void
    {
        $a = $this->company('Clínica A', '900123456-8');
        $jefeNutricion = User::role('jefe_nutricion')->firstOrFail();
        $jefeNutricion->update(['all_companies' => false]);
        $jefeNutricion->companies()->sync([$a->id]);

        Livewire::actingAs($jefeNutricion)->test(UsersManager::class)
            ->call('create')
            ->set('name', 'Despachador nuevo')
            ->set('email', 'desp.nuevo@example.com')
            ->set('password', 'Secreta-2026')
            ->set('password_confirmation', 'Secreta-2026')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'desp.nuevo@example.com')->firstOrFail();
        $this->assertFalse($user->all_companies);
        $this->assertSame([$a->id], $user->companies()->pluck('companies.id')->all());
    }
}
