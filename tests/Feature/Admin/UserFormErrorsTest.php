<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\UsersManager;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SCRUM-25: el formulario de usuario muestra todas las reglas y todos los errores en un solo
 * envío (contraseña, confirmación y empresa), no uno por envío.
 */
class UserFormErrorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_all_errors_come_back_in_one_submission(): void
    {
        $component = Livewire::actingAs(User::role('admin')->firstOrFail())->test(UsersManager::class)
            ->call('create')
            ->assertSee('Mínimo 10 caracteres, con mayúsculas, minúsculas y números.')
            ->set('name', 'Nuevo')
            ->set('email', 'nuevo@nutritrack.com')
            ->set('password', 'abc')
            ->set('password_confirmation', 'xyz')
            ->set('roles', ['despachador'])
            ->set('companies', [])
            ->call('save')
            ->assertHasErrors(['password', 'companies']);

        $errors = $component->errors();
        // Largo, mayúsculas/minúsculas, números y confirmación juntos
        $this->assertGreaterThanOrEqual(4, count($errors->get('password')));
        $this->assertSame(['Asigna al menos una empresa específica al despachador.'], $errors->get('companies'));
        foreach ($errors->get('password') as $message) {
            $component->assertSee($message);
        }
        $this->assertSame(0, User::where('email', 'nuevo@nutritrack.com')->count());
    }

    public function test_dispatcher_cannot_get_all_companies_reported_with_the_rest(): void
    {
        Livewire::actingAs(User::role('admin')->firstOrFail())->test(UsersManager::class)
            ->call('create')
            ->set('name', '')
            ->set('email', 'otro@nutritrack.com')
            ->set('password', 'Secreta-2026')
            ->set('password_confirmation', 'Secreta-2026')
            ->set('roles', ['despachador'])
            ->set('all_companies', true)
            ->call('save')
            ->assertHasErrors(['name', 'companies'])
            ->assertSee('A los despachadores y jefes de despacho se les asignan empresas específicas, no «Todas».');
    }
}
