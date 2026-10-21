<?php

namespace Tests\Feature;

use App\Livewire\Admin\UsersManager;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El jefe de nutrición crea usuarios solo con rol despachador y solo ve a los despachadores.
 */
class JefeCreatesDispatchersTest extends TestCase
{
    use RefreshDatabase;

    private User $jefe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->jefe = User::role('jefe_nutricion')->firstOrFail();
    }

    private function form(array $roles)
    {
        return Livewire::actingAs($this->jefe)->test(UsersManager::class)
            ->call('create')
            ->set('name', 'Cocina Turno Noche')
            ->set('email', 'noche@hospital.co')
            ->set('password', 'Secreta-2026')
            ->set('password_confirmation', 'Secreta-2026')
            ->set('roles', $roles);
    }

    public function test_jefe_creates_a_dispatcher(): void
    {
        Livewire::actingAs($this->jefe)->test(UsersManager::class)->call('create')->assertSet('roles', ['despachador']);

        $this->form(['despachador'])->call('save')->assertHasNoErrors();

        $this->assertTrue(User::where('email', 'noche@hospital.co')->firstOrFail()->hasRole('despachador'));
    }

    public function test_jefe_cannot_assign_other_roles(): void
    {
        $this->form(['admin'])->call('save')->assertHasErrors('roles.0');
        $this->form([])->call('save')->assertHasErrors('roles');

        $this->assertNull(User::where('email', 'noche@hospital.co')->first());
    }

    public function test_jefe_only_sees_dispatchers_and_cannot_edit(): void
    {
        $admin = User::role('admin')->firstOrFail();
        $dispatcher = User::role('despachador')->firstOrFail();

        Livewire::actingAs($this->jefe)->test(UsersManager::class)
            ->assertSee($dispatcher->email)
            ->assertDontSee($admin->email)
            ->call('edit', $dispatcher->id)->assertForbidden();
    }

    public function test_admin_still_assigns_any_role(): void
    {
        Livewire::actingAs(User::role('admin')->firstOrFail())->test(UsersManager::class)
            ->call('create')
            ->set('name', 'Otra Nutri')
            ->set('email', 'nutri2@hospital.co')
            ->set('password', 'Secreta-2026')
            ->set('password_confirmation', 'Secreta-2026')
            ->set('roles', ['nutricionista'])
            ->set('all_companies', true)
            ->call('save')
            ->assertHasNoErrors();
    }
}
