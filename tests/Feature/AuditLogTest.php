<?php

namespace Tests\Feature;

use App\Livewire\Admin\AuditLogViewer;
use App\Livewire\Admin\UsersManager;
use App\Livewire\DietHistoryViewer;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\DietService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Registro de auditoría: accesos, administración, exportaciones y consultas del historial.
 */
class AuditLogTest extends TestCase
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

    public function test_logins_and_failed_attempts_are_logged_without_passwords(): void
    {
        $user = User::role('despachador')->firstOrFail();

        $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta']);
        $this->post('/login', ['email' => $user->email, 'password' => env('SEED_USER_PASSWORD')]);

        $this->assertSame(1, AuditLog::where('event', 'login_failed')->count());
        $this->assertSame(1, AuditLog::where('event', 'login')->where('user_id', $user->id)->count());
        $this->assertStringNotContainsString('incorrecta', AuditLog::pluck('meta')->toJson());
    }

    public function test_user_administration_is_logged(): void
    {
        $user = User::role('despachador')->firstOrFail();

        Livewire::actingAs($this->admin)->test(UsersManager::class)->call('toggleActive', $user->id);

        $log = AuditLog::where('event', 'user_deactivated')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertTrue($log->subject->is($user));
    }

    public function test_exports_and_history_views_are_logged(): void
    {
        $nutritionist = User::role('nutricionista')->firstOrFail();
        $diet = app(DietService::class)->createDiet(['habitation' => '401', 'name_patient' => 'Paciente 401', 'timeFood' => ['desayuno'], 'billing_type' => 'normal'], $nutritionist->id);

        $this->actingAs($nutritionist)->get(route('reports.monthly.excel'))->assertOk();
        Livewire::actingAs($nutritionist)->test(DietHistoryViewer::class)->call('select', $diet->id);

        $this->assertSame(1, AuditLog::where('event', 'export')->count());
        $history = AuditLog::where('event', 'history_viewed')->firstOrFail();
        $this->assertStringNotContainsString('Paciente 401', $history->description); // sin nombre del paciente
    }

    public function test_audit_log_is_immutable(): void
    {
        $log = AuditLog::create(['event' => 'login', 'description' => 'x']);

        $this->expectException(\LogicException::class);
        $log->update(['description' => 'y']);
    }

    public function test_only_admin_can_view_the_audit_log(): void
    {
        AuditLog::create(['event' => 'login', 'description' => 'Ingresó alguien@ejemplo.com', 'user_id' => $this->admin->id]);

        $this->actingAs($this->admin)->get(route('admin.audit.index'))->assertOk()->assertSee('Ingresó alguien@ejemplo.com');
        Livewire::actingAs($this->admin)->test(AuditLogViewer::class)->set('event', 'export')->assertDontSee('Ingresó alguien@ejemplo.com');

        $this->flushSession();
        $this->actingAs(User::role('nutricionista')->firstOrFail())->get(route('admin.audit.index'))->assertForbidden();
    }
}
