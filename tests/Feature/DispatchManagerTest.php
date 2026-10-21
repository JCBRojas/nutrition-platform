<?php

namespace Tests\Feature;

use App\Livewire\Admin\UsersManager;
use App\Livewire\CompaniesManager;
use App\Livewire\DietsManager;
use App\Models\Company;
use App\Models\Diet;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** NUT-RN-003-20 a -25 (ampliación de SCRUM-7): el jefe de despacho administra sus empresas y despachadores */
class DispatchManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->manager = User::role('jefe_despacho')->firstOrFail();
        $this->admin = User::role('admin')->firstOrFail();
    }

    private function companyOf(User $manager, string $name, string $nit): Company
    {
        $company = new Company(['name' => $name, 'nit' => $nit, 'contact_name' => 'C', 'contact_phone' => '+573001234567', 'contact_email' => 'c@example.com']);
        $company->manager_id = $manager->id;
        $company->save();

        return $company;
    }

    private function otherManager(): User
    {
        $other = User::factory()->create(['all_companies' => false]);
        $other->assignRole('jefe_despacho');

        return $other;
    }

    private function companyForm($component, string $name, string $nit)
    {
        return $component->call('create')
            ->set('name', $name)
            ->set('nit', $nit)
            ->set('contact_name', 'Ana')
            ->set('contact_phone', '+573001234567')
            ->set('contact_email', 'ana@example.com');
    }

    private function newDispatcherForm($component, string $email, array $companies)
    {
        return $component->call('create')
            ->set('name', 'Despachador nuevo')
            ->set('email', $email)
            ->set('password', 'Secreta-2026')
            ->set('password_confirmation', 'Secreta-2026')
            ->set('roles', ['despachador'])
            ->set('companies', array_map('strval', $companies));
    }

    public function test_admin_must_choose_the_dispatch_manager_of_a_new_company(): void
    {
        $this->companyForm(Livewire::actingAs($this->admin)->test(CompaniesManager::class), 'Clínica Norte', '900123456-8')
            ->call('save')
            ->assertHasErrors(['manager_id' => 'required']);

        $this->companyForm(Livewire::actingAs($this->admin)->test(CompaniesManager::class), 'Clínica Norte', '900123456-8')
            ->set('manager_id', $this->admin->id) // no es jefe de despacho
            ->call('save')
            ->assertHasErrors(['manager_id']);

        $this->companyForm(Livewire::actingAs($this->admin)->test(CompaniesManager::class), 'Clínica Norte', '900123456-8')
            ->set('manager_id', $this->manager->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($this->manager->id, Company::where('name', 'Clínica Norte')->value('manager_id'));
    }

    public function test_company_created_by_a_manager_is_his_and_he_only_sees_his_companies(): void
    {
        $this->activatePremium(); // varias empresas: función Premium (NUT-RN-003-16)
        $foreign = $this->companyOf($this->otherManager(), 'Clínica Ajena', '860002964-4');

        $this->companyForm(Livewire::actingAs($this->manager)->test(CompaniesManager::class), 'Clínica Propia', '900123456-8')
            ->call('save')
            ->assertHasNoErrors();

        $own = Company::where('name', 'Clínica Propia')->firstOrFail();
        $this->assertSame($this->manager->id, $own->manager_id);
        $this->assertTrue($this->manager->fresh()->canAccessCompany($own));
        $this->assertFalse($this->manager->fresh()->canAccessCompany($foreign));

        Livewire::actingAs($this->manager)->test(CompaniesManager::class)
            ->assertSee('Clínica Propia')
            ->assertDontSee('Clínica Ajena');
        Livewire::actingAs($this->manager)->test(CompaniesManager::class)->call('edit', $foreign->id)->assertForbidden();
        Livewire::actingAs($this->manager)->test(CompaniesManager::class)->call('toggleActive', $foreign->id)->assertForbidden();
        $this->assertTrue($foreign->fresh()->is_active);
    }

    public function test_manager_creates_dispatchers_only_with_his_companies(): void
    {
        $this->activatePremium(); // varias empresas: función Premium (NUT-RN-003-16)
        $own = $this->companyOf($this->manager, 'Clínica Propia', '900123456-8');
        $foreign = $this->companyOf($this->otherManager(), 'Clínica Ajena', '860002964-4');

        // Formulario: solo sus empresas y sin «Todas»
        Livewire::actingAs($this->manager)->test(UsersManager::class)
            ->call('create')
            ->assertSee('Clínica Propia')
            ->assertDontSee('Clínica Ajena')
            ->assertDontSee('Todas las empresas');

        $this->newDispatcherForm(Livewire::actingAs($this->manager)->test(UsersManager::class), 'ajeno@example.com', [$foreign->id])
            ->call('save')
            ->assertHasErrors('companies');
        $this->assertDatabaseMissing('users', ['email' => 'ajeno@example.com']);

        $this->newDispatcherForm(Livewire::actingAs($this->manager)->test(UsersManager::class), 'nuevo@example.com', [$own->id])
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'nuevo@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('despachador'));
        $this->assertSame([$own->id], $user->companies()->pluck('companies.id')->all());
    }

    public function test_companies_list_counts_the_diets_of_each_company(): void
    {
        $this->activatePremium();
        $own = $this->companyOf($this->manager, 'Clínica Propia', '900123456-8');
        Diet::create(['company_id' => $own->id, 'habitation' => '301', 'name_patient' => 'Paciente', 'status' => Diet::ACTIVE]);
        $active = $this->companyOf($this->manager, 'Clínica Activa', '860002964-4');
        $this->manager->forceFill(['last_company_id' => $active->id])->save();

        // La empresa activa del jefe es otra: el conteo no debe depender de ella
        Livewire::actingAs($this->manager)->test(CompaniesManager::class)
            ->assertViewHas('companies', fn ($companies) => $companies->firstWhere('id', $own->id)->diets_count === 1);
    }

    public function test_without_premium_manager_runs_the_default_company(): void
    {
        // Sin Premium solo existe para él la predeterminada (NUT-RN-003-18), aunque tenga otras a su nombre
        $default = Company::default();
        $own = $this->companyOf($this->manager, 'Clínica Propia', '900123456-8');

        Livewire::actingAs($this->manager)->test(UsersManager::class)
            ->call('create')
            ->assertSee($default->name)
            ->assertDontSee('Clínica Propia');

        $this->newDispatcherForm(Livewire::actingAs($this->manager)->test(UsersManager::class), 'propia@example.com', [$own->id])
            ->call('save')
            ->assertHasErrors('companies');

        $this->newDispatcherForm(Livewire::actingAs($this->manager)->test(UsersManager::class), 'basico@example.com', [$default->id])
            ->call('save')
            ->assertHasNoErrors();

        $dispatcher = User::where('email', 'basico@example.com')->firstOrFail();
        $this->assertSame([$default->id], $dispatcher->companies()->pluck('companies.id')->all());
        $this->assertTrue($this->manager->can('update', $dispatcher));
        Livewire::actingAs($this->manager)->test(UsersManager::class)->assertSee('basico@example.com');
    }

    public function test_manager_can_assign_all_his_companies_at_once(): void
    {
        $this->activatePremium(); // varias empresas: función Premium (NUT-RN-003-16)
        $one = $this->companyOf($this->manager, 'Clínica Uno', '900123456-8');
        $two = $this->companyOf($this->manager, 'Clínica Dos', '860002964-4');
        $own = Company::managedBy($this->manager)->active()->pluck('id')->all(); // incluye la predeterminada

        Livewire::actingAs($this->manager)->test(UsersManager::class)
            ->call('create')
            ->assertSee('Todas mis empresas')
            ->set('name', 'Despachador de todas')
            ->set('email', 'todas.mias@example.com')
            ->set('password', 'Secreta-2026')
            ->set('password_confirmation', 'Secreta-2026')
            ->set('roles', ['despachador'])
            ->call('toggleOwnCompanies')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'todas.mias@example.com')->firstOrFail();
        $this->assertFalse($user->all_companies);
        $this->assertEqualsCanonicalizing($own, $user->companies()->pluck('companies.id')->all());
        $this->assertContains($one->id, $own);
        $this->assertContains($two->id, $own);
    }

    public function test_manager_home_is_the_dispatch_log_with_premium(): void
    {
        // Sin Premium despachos y empresas están bloqueados: inicia en la suscripción (NUT-RN-003-17)
        $this->assertSame(route('subscription.index'), $this->manager->homeUrl());

        $this->activatePremium();
        $this->assertSame(route('dispatches.index'), $this->manager->fresh()->homeUrl());
    }

    public function test_manager_cannot_assign_other_roles(): void
    {
        $own = $this->companyOf($this->manager, 'Clínica Propia', '900123456-8');

        $this->newDispatcherForm(Livewire::actingAs($this->manager)->test(UsersManager::class), 'nutri@example.com', [$own->id])
            ->set('roles', ['nutricionista'])
            ->call('save')
            ->assertHasErrors('roles.0');
    }

    public function test_manager_only_sees_and_edits_users_of_his_companies(): void
    {
        $this->activatePremium(); // varias empresas: función Premium (NUT-RN-003-16)
        $own = $this->companyOf($this->manager, 'Clínica Propia', '900123456-8');
        $foreign = $this->companyOf($this->otherManager(), 'Clínica Ajena', '860002964-4');
        $mine = User::factory()->create(['name' => 'Despachador Propio', 'all_companies' => false]);
        $mine->assignRole('despachador');
        $mine->companies()->sync([$own->id]);
        $theirs = User::factory()->create(['name' => 'Despachador Ajeno', 'all_companies' => false]);
        $theirs->assignRole('despachador');
        $theirs->companies()->sync([$foreign->id]);

        Livewire::actingAs($this->manager)->test(UsersManager::class)
            ->assertSee('Despachador Propio')
            ->assertDontSee('Despachador Ajeno')
            ->call('edit', $theirs->id)
            ->assertForbidden();

        $this->assertTrue($this->manager->can('update', $mine));
        $this->assertFalse($this->manager->can('update', $theirs));
    }

    public function test_dispatcher_without_specific_company_cannot_see_any_screen(): void
    {
        $this->activatePremium();
        $dispatcher = User::factory()->create(['all_companies' => false]);
        $dispatcher->assignRole('despachador');

        $this->actingAs($dispatcher)->get(route('dashboard.dispatch'))
            ->assertForbidden()
            ->assertSee('Sin empresa asignada');
        $this->actingAs($dispatcher)->get(route('dispatches.index'))->assertForbidden();
        $this->actingAs($dispatcher)->get(route('diet.reports'))->assertForbidden();
        $this->actingAs($dispatcher)->get(route('profile.show'))->assertOk(); // puede ver su perfil y salir

        // Con una empresa específica ya entra
        $dispatcher->companies()->sync([Company::default()->id]);
        $this->actingAs($dispatcher)->get(route('dashboard.dispatch'))->assertOk();
        Livewire::actingAs($dispatcher)->test(DietsManager::class)->assertOk();
    }

    public function test_dispatcher_cannot_receive_all_companies(): void
    {
        $this->newDispatcherForm(Livewire::actingAs($this->admin)->test(UsersManager::class), 'todas@example.com', [])
            ->set('all_companies', true)
            ->call('save')
            ->assertHasErrors('user')
            ->assertSee('se les asignan empresas específicas, no «Todas»');

        $this->assertDatabaseMissing('users', ['email' => 'todas@example.com']);
    }

    public function test_manager_has_access_to_the_dispatch_log(): void
    {
        $this->activatePremium();

        $this->actingAs($this->manager)->get(route('dispatches.index'))->assertOk();
        $this->actingAs($this->manager)->get(route('profile.show'))->assertSee('id="nav-despachos"', false);
    }
}
