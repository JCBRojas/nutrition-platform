<?php

namespace Tests\Feature;

use App\Livewire\Admin\DemoRequestsViewer;
use App\Models\DemoRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Login con vitrina, botón «Solicitar demo» y pie de página; formulario de demo y su listado.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    private array $valid = [
        'name' => 'Laura Pérez',
        'institution' => 'Clínica Central',
        'position' => 'Jefe de nutrición',
        'email' => 'laura@clinica.co',
        'phone' => '+57 300 123 4567',
        'beds' => 120,
        'message' => 'Queremos ver el despacho.',
        'consent' => '1',
    ];

    public function test_login_shows_demo_button_and_footer_without_showcase(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(route('demo.create'))
            ->assertSee('Solicitar demo')
            ->assertDontSee('Así se ve NutriTrack por dentro.')
            ->assertDontSee('images/demo/dietas.jpg')
            ->assertDontSee('Productos')
            ->assertDontSee('¿Olvidaste tu contraseña?')
            ->assertSee('Contacto')
            ->assertSee(config('landing.contact.email'));
    }

    public function test_demo_request_is_saved_and_notified(): void
    {
        Mail::fake();

        $this->get(route('demo.create'))->assertOk()->assertSee('Enviar solicitud');
        $this->post(route('demo.store'), $this->valid)
            ->assertRedirect(route('demo.create'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('demo_requests', ['institution' => 'Clínica Central', 'status' => 'new']);
    }

    public function test_demo_request_requires_contact_data_and_consent(): void
    {
        $this->post(route('demo.store'), ['name' => 'X'])
            ->assertSessionHasErrors(['institution', 'email', 'phone', 'consent']);
        $this->assertSame(0, DemoRequest::count());
    }

    public function test_bots_filling_the_honeypot_are_ignored(): void
    {
        $this->post(route('demo.store'), $this->valid + ['website' => 'http://spam.example'])->assertRedirect(route('demo.create'));
        $this->assertSame(0, DemoRequest::count());
    }

    public function test_only_admin_sees_demo_requests(): void
    {
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $demo = DemoRequest::create(array_diff_key($this->valid, ['consent' => 1]) + ['status' => 'new']);
        $admin = User::role('admin')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.demo-requests.index'))->assertOk()->assertSee('Clínica Central');
        Livewire::actingAs($admin)->test(DemoRequestsViewer::class)->call('setStatus', $demo->id, 'contacted');
        $this->assertSame('contacted', $demo->fresh()->status);

        $this->flushSession();
        $this->actingAs(User::role('nutricionista')->firstOrFail())->get(route('admin.demo-requests.index'))->assertForbidden();
    }
}
