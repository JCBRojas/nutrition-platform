<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\RolesManager;
use App\Livewire\Admin\UsersManager;
use App\Models\Diet;
use App\Models\DietHistory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsersManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::role('admin')->firstOrFail();
    }

    public function test_only_users_with_permission_can_open_the_module(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.roles.index'))->assertOk();

        // AuthenticateSession (Jetstream) invalida la sesión al cambiar de usuario
        $this->flushSession();

        $nutritionist = User::role('nutricionista')->first();
        $this->actingAs($nutritionist)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($nutritionist)->get(route('admin.roles.index'))->assertForbidden();
    }

    public function test_admin_can_create_a_user_with_roles(): void
    {
        Livewire::actingAs($this->admin)->test(UsersManager::class)
            ->call('create')
            ->set('name', 'Auxiliar Cocina')
            ->set('email', 'aux@example.com')
            ->set('phone', '3001234567')
            ->set('password', 'Secreta-2026')
            ->set('password_confirmation', 'Secreta-2026')
            ->set('roles', ['despachador'])
            ->set('companies', [(string) \App\Models\Company::default()->id]) // despachador: empresa específica
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $user = User::where('email', 'aux@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('despachador'));
        $this->assertTrue($user->is_active);
        $this->assertSame('3001234567', $user->phone);
    }

    public function test_validation_rejects_bad_phone_duplicate_email_and_unknown_role(): void
    {
        Livewire::actingAs($this->admin)->test(UsersManager::class)
            ->call('create')
            ->set('name', 'X')
            ->set('email', 'camilo@gmail.com')
            ->set('phone', '+573001234567') // con indicativo: no se acepta
            ->set('password', 'Secreta-2026')
            ->set('password_confirmation', 'Secreta-2026')
            ->set('roles', ['superusuario'])
            ->call('save')
            ->assertHasErrors(['email', 'phone', 'roles.0']);
    }

    public function test_edit_keeps_password_when_left_empty(): void
    {
        $user = User::role('despachador')->first();
        $hash = $user->password;

        Livewire::actingAs($this->admin)->test(UsersManager::class)
            ->call('edit', $user->id)
            ->set('name', 'Despachador Editado')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Despachador Editado', $user->name);
        $this->assertSame($hash, $user->password);
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::role('despachador')->first();

        Livewire::actingAs($this->admin)->test(UsersManager::class)->call('toggleActive', $user->id);
        $this->assertFalse($user->fresh()->is_active);

        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => env('SEED_USER_PASSWORD')])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_delete_is_soft_and_restorable(): void
    {
        $user = User::role('despachador')->first();
        $component = Livewire::actingAs($this->admin)->test(UsersManager::class);

        $component->call('delete', $user->id);
        $this->assertSoftDeleted($user);

        $component->call('restore', $user->id);
        $this->assertNotSoftDeleted($user);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_admin_cannot_delete_or_deactivate_themselves(): void
    {
        Livewire::actingAs($this->admin)->test(UsersManager::class)
            ->call('delete', $this->admin->id)
            ->assertForbidden();

        Livewire::actingAs($this->admin)->test(UsersManager::class)
            ->call('toggleActive', $this->admin->id);

        $this->assertTrue($this->admin->fresh()->is_active);
        $this->assertNotSoftDeleted($this->admin);
    }

    public function test_last_admin_role_cannot_be_removed(): void
    {
        $other = User::factory()->create();
        $other->assignRole('admin');
        $other->update(['is_active' => false]);

        Livewire::actingAs($other)->test(UsersManager::class)
            ->call('edit', $this->admin->id)
            ->set('roles', ['nutricionista'])
            ->set('all_companies', true) // con acceso válido, solo falla la regla del último admin
            ->call('save')
            ->assertHasErrors('user');

        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
    }

    public function test_roles_matrix_updates_permissions_and_keeps_admin_full(): void
    {
        Livewire::actingAs($this->admin)->test(RolesManager::class)
            ->set('matrix.despachador', ['viewDiets'])
            ->set('matrix.admin', [])
            ->call('save');

        $this->assertEqualsCanonicalizing(['viewDiets'], Role::findByName('despachador')->permissions->pluck('name')->all());
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('manageRoles'));
    }

    public function test_role_created_under_sanctum_guard_gets_permissions(): void
    {
        // En la app las rutas usan auth:sanctum: durante la petición el guard por defecto es 'sanctum'
        auth()->shouldUse('sanctum');

        Livewire::actingAs($this->admin)->test(RolesManager::class)
            ->set('newRole', 'enfermeria')
            ->call('createRole')
            ->set('matrix.enfermeria', ['viewDiets', 'confirmReception'])
            ->call('save')
            ->assertHasNoErrors();

        $role = Role::findByName('enfermeria', 'web');
        $this->assertEqualsCanonicalizing(['viewDiets', 'confirmReception'], $role->permissions->pluck('name')->all());
    }

    public function test_migration_moves_sanctum_roles_to_web(): void
    {
        $roleId = \DB::table('roles')->insertGetId(['name' => 'enfermeria', 'guard_name' => 'sanctum', 'created_at' => now(), 'updated_at' => now()]);
        $this->admin->roles()->attach($roleId);

        (require database_path('migrations/2026_10_20_000000_move_sanctum_roles_to_web_guard.php'))->up();

        $this->assertSame('web', \DB::table('roles')->where('id', $roleId)->value('guard_name'));
        $this->assertTrue($this->admin->fresh()->hasRole('enfermeria'));
        Role::findByName('enfermeria', 'web')->syncPermissions(['viewDiets']); // ya no falla
    }

    public function test_cannot_delete_role_with_users(): void
    {
        Livewire::actingAs($this->admin)->test(RolesManager::class)
            ->call('deleteRole', 'despachador');

        $this->assertNotNull(Role::where('name', 'despachador')->first());
    }

    public function test_nutritionist_dashboard_lists_cancelled_diets(): void
    {
        $nutritionist = User::role('nutricionista')->first();
        $diet = Diet::create(['habitation' => '305', 'name_patient' => 'Paciente Cancelado', 'status' => false]);
        DietHistory::create([
            'diet_id' => $diet->id, 'updated_by' => $nutritionist->id, 'version' => 1,
            'timeFood' => ['desayuno'], 'changes' => 'cancelada', 'modify' => 'si',
        ]);

        // Solo se dibuja la pestaña abierta (componente Livewire NutritionDiets)
        $this->actingAs($nutritionist)->get(route('dashboard.nutrition', ['tab' => 'cancelled']))
            ->assertOk()
            ->assertSee('Canceladas (1)')
            ->assertSee('Paciente Cancelado')
            ->assertSee('Nutricionista Principal');
    }
}
