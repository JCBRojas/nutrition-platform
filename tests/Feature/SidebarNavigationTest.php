<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_LINKS = [
        'nav-nutricional', 'nav-despacho', 'nav-despachos', 'nav-historial', 'nav-reporte',
        'nav-precios', 'nav-suscripcion', 'nav-usuarios', 'nav-roles', 'nav-demos', 'nav-auditoria',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        Role::create(['name' => 'enfermeria', 'guard_name' => 'web'])
            ->givePermissionTo(['viewActiveDiets', 'continueDiets']);
    }

    /** @dataProvider roles */
    public function test_each_role_sees_only_its_links(string $role, array $expected): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('profile.show'))->assertOk();

        foreach (self::ALL_LINKS as $id) {
            in_array($id, $expected, true)
                ? $response->assertSee('id="'.$id.'"', false)
                : $response->assertDontSee('id="'.$id.'"', false);
        }
    }

    public static function roles(): array
    {
        return [
            'admin' => ['admin', ['nav-nutricional', 'nav-despachos', 'nav-historial', 'nav-reporte', 'nav-precios',
                'nav-suscripcion', 'nav-usuarios', 'nav-roles', 'nav-demos', 'nav-auditoria']],
            'nutricionista' => ['nutricionista', ['nav-nutricional', 'nav-despachos', 'nav-historial', 'nav-reporte']],
            'enfermeria' => ['enfermeria', ['nav-nutricional']],
            'despachador' => ['despachador', ['nav-despacho', 'nav-despachos', 'nav-historial', 'nav-reporte']],
            'jefe_nutricion' => ['jefe_nutricion', ['nav-historial', 'nav-reporte', 'nav-precios', 'nav-suscripcion', 'nav-usuarios']],
        ];
    }

    public function test_current_page_is_marked_and_logout_uses_modal(): void
    {
        $user = User::factory()->create();
        $user->assignRole('nutricionista');

        $html = $this->actingAs($user)->get(route('dashboard.nutrition'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="nav-nutricional"[^>]*aria-current="page"/', $html);
        $this->assertStringContainsString('id="logoutOverlay"', $html);
        $this->assertStringNotContainsString('confirm(', $html);
    }

    public function test_premium_links_are_locked_without_plan(): void
    {
        $user = User::factory()->create();
        $user->assignRole('nutricionista');

        $html = $this->actingAs($user)->get(route('profile.show'))->getContent();

        $this->assertMatchesRegularExpression('/<a[^>]*is-locked[^>]*href="'.preg_quote(route('subscription.index'), '/').'"[^>]*id="nav-despachos"/', $html);
        $this->assertStringContainsString('Premium', $html);
    }

    public function test_premium_links_open_with_plan(): void
    {
        $this->activatePremium();
        $user = User::factory()->create();
        $user->assignRole('nutricionista');

        $html = $this->actingAs($user)->get(route('profile.show'))->getContent();

        $this->assertMatchesRegularExpression('/<a[^>]*href="'.preg_quote(route('dispatches.index'), '/').'"[^>]*id="nav-despachos"/', $html);
        $this->assertStringNotContainsString('is-locked', $html);
    }
}
