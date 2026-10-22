<?php

namespace Tests\Feature;

use App\Livewire\Admin\EmailDomainsManager;
use App\Models\AuditLog;
use App\Models\EmailDomain;
use App\Models\User;
use App\Services\EmailDomainService;
use App\Services\UserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NUT-RN-002 (SCRUM-6): solo inician sesión los correos de un dominio autorizado por el admin.
 */
class EmailDomainsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::role('admin')->firstOrFail(); // admin@nutritrack.com
    }

    private function user(string $email, string $role = 'nutricionista'): User
    {
        $user = User::factory()->create(['email' => $email, 'password' => bcrypt('Secreta-2026')]);
        $user->assignRole($role);

        return $user;
    }

    private function authorize(string ...$domains): void
    {
        foreach ($domains as $domain) {
            EmailDomain::create(['domain' => $domain, 'is_active' => true]);
        }
    }

    public function test_empty_list_does_not_restrict_login(): void
    {
        $user = $this->user('maria@gmail.com');

        $this->post('/login', ['email' => $user->email, 'password' => 'Secreta-2026']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_only_authorized_domains_log_in_with_the_generic_message(): void
    {
        $this->authorize('nutritrack.com', 'hospitalsanjose.org');
        $allowed = $this->user('maria@hospitalsanjose.org');
        $blocked = $this->user('maria@gmail.com');
        $sub = $this->user('ana@sub.hospitalsanjose.org');

        // Mismo mensaje que una contraseña errónea: no revela el dominio ni que el usuario existe
        $this->post('/login', ['email' => $blocked->email, 'password' => 'Secreta-2026'])
            ->assertSessionHasErrors(['email' => 'Credenciales incorrectas.']);
        $this->post('/login', ['email' => $sub->email, 'password' => 'Secreta-2026'])
            ->assertSessionHasErrors(['email' => 'Credenciales incorrectas.']);
        $this->assertGuest();

        $log = AuditLog::where('event', 'login_blocked_domain')->latest('id')->firstOrFail();
        $this->assertSame(['domain' => 'sub.hospitalsanjose.org'], $log->meta);

        $this->post('/login', ['email' => strtoupper($allowed->email), 'password' => 'Secreta-2026']);
        $this->assertAuthenticatedAs($allowed);
    }

    public function test_inactive_domain_blocks_login(): void
    {
        $this->authorize('nutritrack.com');
        EmailDomain::create(['domain' => 'hospital.org', 'is_active' => false]);
        $user = $this->user('pedro@hospital.org');

        $this->post('/login', ['email' => $user->email, 'password' => 'Secreta-2026'])
            ->assertSessionHasErrors(['email' => 'Credenciales incorrectas.']);
        $this->assertGuest();
    }

    public function test_password_reset_link_is_not_sent_to_unauthorized_domain(): void
    {
        Notification::fake();
        $this->authorize('nutritrack.com');
        $blocked = $this->user('maria@gmail.com');

        // La respuesta es la misma que con un correo autorizado
        $this->post('/forgot-password', ['email' => $blocked->email])->assertSessionHasNoErrors();
        $this->post('/forgot-password', ['email' => $this->admin->email])->assertSessionHasNoErrors();

        Notification::assertNotSentTo($blocked, ResetPassword::class);
        Notification::assertSentTo($this->admin, ResetPassword::class);
    }

    public function test_users_cannot_be_created_or_moved_to_an_unauthorized_domain(): void
    {
        $this->authorize('nutritrack.com');
        $service = app(UserService::class);
        $data = ['name' => 'Nueva', 'phone' => null, 'password' => 'Secreta-2026x', 'roles' => ['nutricionista'], 'all_companies' => true];

        try {
            $service->create([...$data, 'email' => 'nueva@gmail.com']);
            $this->fail('Debió rechazar el dominio');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('gmail.com no está autorizado', $e->errors()['email'][0]);
        }

        $user = $service->create([...$data, 'email' => 'nueva@nutritrack.com']);

        $this->expectException(ValidationException::class);
        $service->update($user, [...$data, 'email' => 'nueva@gmail.com']);
    }

    public function test_existing_user_outside_the_list_can_still_be_edited_without_changing_email(): void
    {
        $user = $this->user('antiguo@gmail.com');
        $this->authorize('nutritrack.com');

        app(UserService::class)->update($user, ['name' => 'Renombrado', 'email' => 'antiguo@gmail.com', 'phone' => null, 'roles' => ['nutricionista'], 'all_companies' => true]);

        $this->assertSame('Renombrado', $user->fresh()->name);
    }

    public function test_the_last_admin_never_loses_access(): void
    {
        $service = app(EmailDomainService::class);

        // Primer dominio que no es el del admin: lo dejaría sin acceso
        try {
            $service->create('hospital.org');
            $this->fail('Debió rechazarlo');
        } catch (ValidationException) {
            $this->assertSame(0, EmailDomain::count());
        }

        $own = $service->create('nutritrack.com');
        $service->create('hospital.org');

        // Cada intento con el registro recién leído (el rechazo revierte la base, no el objeto en memoria)
        foreach (['toggleActive', 'delete'] as $change) {
            try {
                $service->{$change}(EmailDomain::findOrFail($own->id));
                $this->fail('Debió rechazarlo');
            } catch (ValidationException) {
                $this->assertTrue(EmailDomain::findOrFail($own->id)->is_active);
            }
        }
    }

    public function test_deactivating_a_domain_closes_its_users_sessions(): void
    {
        config(['session.driver' => 'database']);
        $this->authorize('nutritrack.com');
        $domain = EmailDomain::create(['domain' => 'hospital.org', 'is_active' => true]);
        $user = $this->user('pedro@hospital.org');
        foreach ([$user->id, $this->admin->id] as $i => $id) {
            DB::table('sessions')->insert(['id' => "s{$i}", 'user_id' => $id, 'ip_address' => '127.0.0.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        }

        app(EmailDomainService::class)->toggleActive($domain);

        $this->assertSame([$this->admin->id], DB::table('sessions')->pluck('user_id')->all());
        $this->assertTrue(AuditLog::where('event', 'email_domain_updated')->exists());
    }

    public function test_admin_manages_the_list_from_the_screen(): void
    {
        $this->actingAs($this->admin)->get(route('admin.email-domains.index'))
            ->assertOk()->assertSee('La lista está vacía');

        Livewire::actingAs($this->admin)->test(EmailDomainsManager::class)
            ->set('domain', 'maria@hospital.org')
            ->call('add')
            ->assertHasErrors('domain')
            ->set('domain', ' @NutriTrack.com ')
            ->call('add')
            ->assertHasNoErrors()
            ->assertSee('Dominio nutritrack.com autorizado.')
            ->set('domain', 'nutritrack.com')
            ->call('add')
            ->assertHasErrors(['domain' => 'unique'])
            ->set('domain', 'hospital.org')
            ->call('add')
            ->assertHasNoErrors();

        $this->assertSame(['hospital.org', 'nutritrack.com'], EmailDomain::orderBy('domain')->pluck('domain')->all());
        $hospital = EmailDomain::where('domain', 'hospital.org')->firstOrFail();
        $own = EmailDomain::where('domain', 'nutritrack.com')->firstOrFail();

        Livewire::actingAs($this->admin)->test(EmailDomainsManager::class)
            ->call('startDelete', $own->id)
            ->assertSee('Eliminar nutritrack.com')
            ->call('confirmDelete')
            ->assertSee('ningún administrador activo podría iniciar sesión')
            ->call('startDelete', $hospital->id)
            ->call('confirmDelete')
            ->assertSee('Dominio hospital.org eliminado.');

        $this->assertModelMissing($hospital);
        $this->assertTrue(AuditLog::where('event', 'email_domain_created')->exists());
        $this->assertTrue(AuditLog::where('event', 'email_domain_deleted')->exists());
    }

    public function test_only_users_with_the_permission_see_the_screen(): void
    {
        $jefe = User::role('jefe_nutricion')->firstOrFail();

        $this->actingAs($jefe)->get(route('admin.email-domains.index'))->assertForbidden();
        $this->flushSession();
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertSee('Dominios de correo');
    }

    public function test_seeder_authorizes_the_admin_domain_on_a_fresh_install(): void
    {
        config(['security.seed_admin_email_domain' => true]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->assertSame(['nutritrack.com'], EmailDomain::pluck('domain')->all());

        // Con dominios ya registrados, el seeder no toca la lista
        EmailDomain::query()->delete();
        EmailDomain::create(['domain' => 'hospital.org', 'is_active' => true]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->assertSame(['hospital.org'], EmailDomain::pluck('domain')->all());
    }
}
