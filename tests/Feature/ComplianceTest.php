<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seguridad y cumplimiento: política de datos, 2FA obligatorio por rol, HTTPS, cabeceras y contraseñas.
 */
class ComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['security.privacy.required' => true, 'security.two_factor_roles' => ['admin', 'jefe_nutricion']]);
    }

    private function accepted(User $user): User
    {
        $user->forceFill(['privacy_accepted_at' => now(), 'privacy_version' => config('security.privacy.version')])->save();

        return $user;
    }

    public function test_user_must_accept_the_privacy_policy_before_using_the_app(): void
    {
        $user = User::role('nutricionista')->firstOrFail();

        $this->actingAs($user)->get(route('dashboard.nutrition'))->assertRedirect(route('privacy.accept'));
        $this->get(route('privacy.accept'))->assertOk()->assertSee('Política de tratamiento de datos');

        $this->post(route('privacy.accept.store'))->assertSessionHasErrors('accept');
        $this->post(route('privacy.accept.store'), ['accept' => '1'])->assertRedirect(route('dashboard.nutrition'));

        $this->get(route('dashboard.nutrition'))->assertOk();
        $this->assertSame(1, AuditLog::where('event', 'privacy_accepted')->where('user_id', $user->id)->count());
    }

    public function test_a_new_policy_version_must_be_accepted_again(): void
    {
        $user = $this->accepted(User::role('nutricionista')->firstOrFail());
        config(['security.privacy.version' => '2027-01-01']);

        $this->actingAs($user)->get(route('dashboard.nutrition'))->assertRedirect(route('privacy.accept'));
    }

    public function test_policy_page_is_public_and_linked_from_login(): void
    {
        $this->get(route('privacy.policy'))->assertOk()->assertSee('Ley 1581 de 2012')->assertSee('dato sensible', false);
        $this->get('/login')->assertSee(route('privacy.policy'));
    }

    public function test_admin_without_two_factor_is_sent_to_the_profile(): void
    {
        $admin = $this->accepted(User::role('admin')->firstOrFail());

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertRedirect(route('profile.show').'#two-factor');
        $this->get(route('profile.show'))->assertOk()->assertSee('verificación en dos pasos');

        $admin->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
        $this->get(route('admin.users.index'))->assertOk();
    }

    public function test_roles_without_the_requirement_do_not_need_two_factor(): void
    {
        $user = $this->accepted(User::role('despachador')->firstOrFail());

        $this->actingAs($user)->get(route('dashboard.dispatch'))->assertOk();
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_https_is_forced_when_enabled(): void
    {
        config(['security.force_https' => true]);

        $this->get('http://localhost/login')->assertRedirect('https://localhost/login');
        $this->get('https://localhost/login')->assertOk()->assertHeader('Strict-Transport-Security');
    }

    public function test_authenticated_pages_are_not_cached(): void
    {
        $user = $this->accepted(User::role('despachador')->firstOrFail());

        $cache = $this->actingAs($user)->get(route('dashboard.dispatch'))->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cache);
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $rule = \Illuminate\Validation\Rules\Password::default();

        $this->assertTrue(validator(['p' => 'despachador'], ['p' => $rule])->fails());
        $this->assertTrue(validator(['p' => 'abcdefghij1'], ['p' => $rule])->fails());
        $this->assertFalse(validator(['p' => 'Secreta-2026'], ['p' => $rule])->fails());
    }
}
