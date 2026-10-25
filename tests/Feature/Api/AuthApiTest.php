<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\EmailDomain;
use App\Models\User;
use App\Services\EmailDomainService;
use App\Services\UserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * API v1 del cliente de escritorio (NUT-RN-004): meta, login con token, 2FA, me, empresa por
 * encabezado y revocación de tokens. Mismas reglas que el login web (AuthService).
 */
class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secreta-2026';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function dispatcher(string $email = 'cocina@nutritrack.com'): User
    {
        $user = User::factory()->create(['email' => $email, 'password' => bcrypt(self::PASSWORD), 'all_companies' => false, 'phone' => '3001112233']);
        $user->assignRole('despachador');
        $user->companies()->sync([Company::default()->id]);

        return $user;
    }

    private function login(string $email, string $password = self::PASSWORD)
    {
        return $this->postJson(route('api.v1.auth.login'), ['email' => $email, 'password' => $password, 'device_name' => 'Mac de cocina']);
    }

    private function bearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    public function test_meta_is_public_and_carries_catalogs_and_realtime(): void
    {
        $this->getJson(route('api.v1.meta'))
            ->assertOk()
            ->assertJsonPath('min_desktop_version', config('desktop.min_version'))
            ->assertJsonPath('catalogs.meals.0', ['key' => 'desayuno', 'label' => 'Desayuno', 'time' => '06:30'])
            ->assertJsonPath('catalogs.consistency.normal', config('consistency.labels.normal'))
            ->assertJsonPath('catalogs.void_kinds.not_arrived', 'No llegó')
            ->assertJsonPath('diet_form.billing_type_required', true)
            ->assertJsonPath('diet_form.habitation_max', 4)
            ->assertJsonPath('realtime.channel', 'private-diets')
            ->assertJsonPath('realtime.event', '.diets.changed')
            ->assertJsonMissingPath('realtime.secret');
    }

    public function test_login_issues_a_token_and_me_hides_personal_data(): void
    {
        $user = $this->dispatcher();

        $token = $this->login($user->email)->assertOk()->assertJsonStructure(['token', 'expires_at'])->json('token');
        $this->assertTrue(AuditLog::where('event', 'login')->where('meta->client', 'desktop')->exists());

        $this->bearer($token)->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('name', $user->name)
            ->assertJsonPath('home', 'dispatch')
            ->assertJsonPath('current_company_id', Company::default()->id)
            ->assertJsonPath('companies.0.id', Company::default()->id)
            ->assertJsonPath('compliance.privacy_required', false)
            ->assertJsonMissingPath('phone')
            ->assertJsonMissingPath('two_factor_secret');
        $this->assertTrue(collect($this->bearer($token)->getJson(route('api.v1.me'))->json('permissions'))->contains('confirmDelivery'));
    }

    public function test_login_rules_are_the_same_as_the_web(): void
    {
        $user = $this->dispatcher();

        $this->login($user->email, 'mala')->assertStatus(422)->assertJsonPath('errors.email.0', 'Credenciales incorrectas.');

        // Dominio no autorizado: mismo mensaje
        EmailDomain::create(['domain' => 'hospital.org', 'is_active' => true]);
        $this->login($user->email)->assertStatus(422)->assertJsonPath('errors.email.0', 'Credenciales incorrectas.');
        EmailDomain::query()->delete();

        $user->update(['is_active' => false]);
        $this->login($user->email)->assertStatus(422)->assertJsonPath('errors.email.0', 'Tu usuario está inactivo. Contacta al administrador.');

        // Bloqueo tras 3 intentos fallidos seguidos (el inicio con contraseña correcta reinicia el conteo)
        foreach ([1, 2, 3] as $i) {
            $this->login($user->email, 'mala')->assertStatus(422);
        }
        $this->login($user->email, 'mala')->assertStatus(429)->assertJsonPath('errors.email.0', fn ($m) => str_starts_with($m, 'Demasiados intentos'));
    }

    public function test_two_factor_is_required_when_the_user_has_it(): void
    {
        $user = $this->dispatcher();
        $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
        $user->forceFill(['two_factor_secret' => encrypt($secret), 'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode(['codigo-uno-1234']))])->save();

        $ticket = $this->login($user->email)->assertOk()
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonMissingPath('token')
            ->json('ticket');

        $twoFactor = fn (array $data) => $this->postJson(route('api.v1.auth.two-factor'), ['ticket' => $ticket, 'device_name' => 'PC', ...$data]);

        $twoFactor(['code' => '000000'])->assertStatus(422)->assertJsonPath('errors.code.0', 'El código no es válido.');
        $twoFactor(['code' => (new Google2FA)->getCurrentOtp($secret)])->assertOk()->assertJsonStructure(['token']);
        $twoFactor(['recovery_code' => 'codigo-uno-1234'])->assertOk(); // código de recuperación (se consume)
        $twoFactor(['recovery_code' => 'codigo-uno-1234'])->assertStatus(422);

        $this->postJson(route('api.v1.auth.two-factor'), ['ticket' => 'manipulado', 'code' => '123456', 'device_name' => 'PC'])->assertStatus(422);
    }

    public function test_logout_revokes_the_token_and_requests_without_token_get_401(): void
    {
        $token = $this->login($this->dispatcher()->email)->json('token');

        $this->bearer($token)->postJson(route('api.v1.auth.logout'))->assertOk();
        $this->bearer($token)->getJson(route('api.v1.me'))->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->get(route('api.v1.me'))->assertUnauthorized()->assertHeader('content-type', 'application/json');
    }

    public function test_company_header_selects_an_eligible_company_only(): void
    {
        $other = Company::create(['name' => 'Clínica Norte', 'nit' => '900123457-1', 'contact_name' => 'X', 'contact_phone' => '3000000000', 'contact_email' => 'x@clinica.org', 'is_active' => true]);
        $token = $this->login($this->dispatcher()->email)->json('token');

        $this->bearer($token)->withHeader('X-Company-Id', (string) $other->id)->getJson(route('api.v1.me'))
            ->assertForbidden()->assertJsonPath('code', 'company_forbidden');
        $this->bearer($token)->withHeader('X-Company-Id', 'abc')->getJson(route('api.v1.me'))->assertForbidden();
        $this->bearer($token)->withHeader('X-Company-Id', (string) Company::default()->id)->getJson(route('api.v1.me'))
            ->assertOk()->assertJsonPath('current_company_id', Company::default()->id);
    }

    public function test_tokens_are_revoked_when_access_is_removed(): void
    {
        $admin = User::role('admin')->firstOrFail();
        $this->actingAs($admin);

        $user = $this->dispatcher();
        $token = $this->login($user->email)->json('token');
        app(UserService::class)->toggleActive($user->fresh());
        $this->assertSame(0, $user->tokens()->count());
        $this->bearer($token)->getJson(route('api.v1.me'))->assertUnauthorized();

        // Dominio que deja de estar autorizado
        $other = $this->dispatcher('cocina@hospital.org');
        $this->login($other->email)->assertOk();
        app(EmailDomainService::class)->create('nutritrack.com');
        $this->assertSame(0, $other->tokens()->count());
    }
}
