<?php

namespace Tests\Feature;

use App\Models\EmailDomain;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * SCRUM-22: «Recuperar contraseña» responde lo mismo exista o no el correo, para no revelar
 * qué correos tienen cuenta.
 */
class PasswordResetEnumerationTest extends TestCase
{
    use RefreshDatabase;

    private const NEUTRAL = 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    public function test_unknown_and_registered_emails_get_the_same_response(): void
    {
        $user = User::factory()->create(['email' => 'ana@hospital.org']);

        foreach (['ana@hospital.org', 'nadie@hospital.org'] as $email) {
            $this->from('/forgot-password')->post('/forgot-password', ['email' => $email])
                ->assertRedirect('/forgot-password')
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', self::NEUTRAL);
        }

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_repeated_request_is_not_revealed_by_the_throttle(): void
    {
        User::factory()->create(['email' => 'ana@hospital.org']);

        // El segundo pedido seguido queda limitado (throttled), pero la respuesta es la misma
        foreach ([1, 2] as $i) {
            $this->from('/forgot-password')->post('/forgot-password', ['email' => 'ana@hospital.org'])
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', self::NEUTRAL);
        }
    }

    public function test_unauthorized_domain_gets_the_same_response(): void
    {
        EmailDomain::create(['domain' => 'nutritrack.com', 'is_active' => true]);
        $blocked = User::factory()->create(['email' => 'maria@gmail.com']);

        $this->from('/forgot-password')->post('/forgot-password', ['email' => $blocked->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', self::NEUTRAL);

        Notification::assertNotSentTo($blocked, ResetPassword::class);
    }

    public function test_json_requests_also_get_a_neutral_response(): void
    {
        $this->postJson('/forgot-password', ['email' => 'nadie@hospital.org'])
            ->assertOk()
            ->assertJson(['message' => self::NEUTRAL]);
    }

    public function test_invalid_email_format_is_still_validated(): void
    {
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'no-es-correo'])
            ->assertSessionHasErrors('email');
    }
}
