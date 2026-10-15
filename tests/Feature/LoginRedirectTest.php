<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** @dataProvider roles */
    public function test_login_redirects_to_role_home(string $role, string $route): void
    {
        $user = User::factory()->create(['password' => bcrypt('Secreta-2026')]);
        $user->assignRole($role);

        $this->post('/login', ['email' => $user->email, 'password' => 'Secreta-2026'])
            ->assertRedirect(route($route));
        $this->assertAuthenticatedAs($user);

        // Volver a /login autenticado no debe cerrar la sesión
        $this->get('/login')->assertRedirect(route($route));
        $this->assertAuthenticatedAs($user);
    }

    public static function roles(): array
    {
        return [
            'admin' => ['admin', 'admin.users.index'],
            'nutricionista' => ['nutricionista', 'dashboard.nutrition'],
            'despachador' => ['despachador', 'dashboard.dispatch'],
        ];
    }
}
