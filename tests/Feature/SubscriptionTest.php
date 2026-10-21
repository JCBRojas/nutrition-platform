<?php

namespace Tests\Feature;

use App\Livewire\SubscriptionManager;
use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\SubscriptionService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Plan básico / premium de la institución y pago manual con comprobante.
 */
class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $jefe;

    private User $nutritionist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00'));
        $this->admin = User::role('admin')->firstOrFail();
        $this->jefe = User::role('jefe_nutricion')->firstOrFail();
        $this->nutritionist = User::role('nutricionista')->firstOrFail();
    }

    private function subscriptions(): SubscriptionService
    {
        $service = app(SubscriptionService::class);
        $service->forget();

        return $service;
    }

    private function reportPayment(string $period = 'monthly'): SubscriptionPayment
    {
        Livewire::actingAs($this->jefe)->test(SubscriptionManager::class)
            ->set('period', $period)
            ->set('method', 'nequi')
            ->set('reference', 'NEQ-123')
            ->set('receipt', UploadedFile::fake()->image('comprobante.jpg'))
            ->call('submitPayment')
            ->assertSet('noticeError', false);

        return SubscriptionPayment::latest('id')->firstOrFail();
    }

    public function test_basic_plan_locks_premium_screens_for_non_admins(): void
    {
        $this->actingAs($this->nutritionist);

        $this->get(route('diets.history'))->assertRedirect(route('subscription.index'));
        $this->get(route('dispatches.index'))->assertRedirect(route('subscription.index'));
        $this->get(route('subscription.index'))->assertOk()->assertSee('Plan Básico')->assertSee('Historial de cambios')
            ->assertSee('Así se ve el plan Premium')->assertSee('images/premium/facturacion.png');

        $this->flushSession();
        $this->actingAs($this->jefe)->get(route('prices.index'))->assertRedirect(route('subscription.index'));
    }

    public function test_admin_keeps_access_to_everything(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('prices.index'))->assertOk();
        $this->get(route('dispatches.index'))->assertOk();
        $this->get(route('diets.history'))->assertOk();
    }

    public function test_menu_shows_locked_premium_items(): void
    {
        $this->actingAs($this->nutritionist)->get(route('dashboard.nutrition'))
            ->assertOk()
            ->assertSee('nav-lock', false)
            ->assertDontSee('title="Ver historial"', false);
    }

    public function test_menu_names_the_prices_item_billing_and_prices(): void
    {
        $this->actingAs($this->jefe)->get(route('diet.reports'))->assertOk()->assertSee('Facturación y precios');
    }

    public function test_payment_is_reported_with_receipt_and_only_one_can_be_pending(): void
    {
        $payment = $this->reportPayment();

        $this->assertSame(SubscriptionPayment::PENDING, $payment->status);
        $this->assertSame(250000.0, (float) $payment->amount);
        Storage::disk('local')->assertExists($payment->receipt_path);
        $this->assertFalse($this->subscriptions()->isPremium()); // aún no se aprueba

        Livewire::actingAs($this->jefe)->test(SubscriptionManager::class)->assertSee('pago pendiente de revisión');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->subscriptions()->submitPayment($this->jefe, 'monthly', 'nequi', 'X', UploadedFile::fake()->image('otro.jpg'));
    }

    public function test_receipt_is_required_and_must_be_an_image_or_pdf(): void
    {
        Livewire::actingAs($this->jefe)->test(SubscriptionManager::class)
            ->set('reference', 'R1')
            ->set('receipt', UploadedFile::fake()->create('virus.exe', 10))
            ->call('submitPayment')
            ->assertHasErrors(['receipt' => 'mimes']);
    }

    public function test_approving_activates_premium_and_renewals_stack(): void
    {
        $payment = $this->reportPayment('monthly');

        Livewire::actingAs($this->admin)->test(SubscriptionManager::class)->call('approve', $payment->id)->assertSet('noticeError', false);

        $this->assertTrue($this->subscriptions()->isPremium());
        $this->assertSame('2026-11-20', $this->subscriptions()->paidUntil()->toDateString());
        $this->assertSame(1, AuditLog::where('event', 'payment_approved')->count());

        // Renovar antes de vencer: el anual empieza cuando termina el mensual
        $annual = $this->reportPayment('annual');
        $this->subscriptions()->approve($annual, $this->admin);
        $this->assertSame('2027-11-20', $this->subscriptions()->paidUntil()->toDateString());

        $this->flushSession();
        $this->actingAs($this->nutritionist)->get(route('diets.history'))->assertOk();
    }

    public function test_premium_expires_back_to_basic(): void
    {
        $this->subscriptions()->approve($this->reportPayment(), $this->admin);

        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00:01'));
        $this->assertFalse($this->subscriptions()->isPremium());
        $this->actingAs($this->nutritionist)->get(route('diets.history'))->assertRedirect(route('subscription.index'));
    }

    public function test_rejecting_requires_a_reason_and_keeps_basic(): void
    {
        $payment = $this->reportPayment();

        Livewire::actingAs($this->admin)->test(SubscriptionManager::class)
            ->call('startReject', $payment->id)
            ->call('reject')->assertHasErrors('rejectReason')
            ->set('rejectReason', 'El comprobante no corresponde al valor.')
            ->call('reject')->assertHasNoErrors();

        $this->assertSame(SubscriptionPayment::REJECTED, $payment->fresh()->status);
        $this->assertFalse($this->subscriptions()->isPremium());
    }

    public function test_only_admin_approves_payments_and_edits_prices(): void
    {
        $payment = $this->reportPayment();

        Livewire::actingAs($this->jefe)->test(SubscriptionManager::class)->call('approve', $payment->id)->assertForbidden();
        Livewire::actingAs($this->nutritionist)->test(SubscriptionManager::class)->call('submitPayment')->assertForbidden();

        Livewire::actingAs($this->admin)->test(SubscriptionManager::class)
            ->set('prices.monthly', '300.000')
            ->call('savePrices')
            ->assertHasNoErrors();
        $this->assertSame(300000.0, $this->subscriptions()->prices()['monthly']);
        $this->assertSame(1, AuditLog::where('event', 'plan_price_updated')->count());
    }

    public function test_admin_can_grant_premium_without_payment(): void
    {
        Livewire::actingAs($this->admin)->test(SubscriptionManager::class)
            ->set('grantPeriod', 'monthly')
            ->set('grantNotes', 'Periodo de prueba')
            ->call('grant');

        $this->assertTrue($this->subscriptions()->isPremium());
        $this->assertNull(Subscription::first()->payment_id);
    }

    public function test_receipt_download_is_restricted(): void
    {
        $payment = $this->reportPayment();

        $this->actingAs($this->admin)->get(route('subscription.receipt', $payment))->assertOk();
        $this->flushSession();
        $this->actingAs($this->nutritionist)->get(route('subscription.receipt', $payment))->assertForbidden();
    }

    public function test_jefe_lands_on_active_diets_with_or_without_premium(): void
    {
        // El jefe de nutrición trabaja como nutricionista: siempre inicia en las dietas activas
        $this->assertSame(route('dashboard.nutrition'), $this->jefe->homeUrl());

        $this->subscriptions()->grant('monthly', $this->admin, 'Prueba');
        $this->subscriptions()->forget();
        $this->assertSame(route('dashboard.nutrition'), $this->jefe->homeUrl());
    }
}
