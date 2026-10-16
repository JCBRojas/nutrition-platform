<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::resetPasswords())) {
            $this->markTestSkipped('Password updates are not enabled.');
        }

        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        if (! Features::enabled(Features::resetPasswords())) {
            $this->markTestSkipped('Password updates are not enabled.');
        }

        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::resetPasswords())) {
            $this->markTestSkipped('Password updates are not enabled.');
        }

        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function (object $notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        if (! Features::enabled(Features::resetPasswords())) {
            $this->markTestSkipped('Password updates are not enabled.');
        }

        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function (object $notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Nueva-Clave-2026',
                'password_confirmation' => 'Nueva-Clave-2026',
            ]);

            $response->assertSessionHasNoErrors();

            return true;
        });
    }

    public function test_reset_password_email_is_in_spanish(): void
    {
        $user = User::factory()->create(['name' => 'Ana Pérez']);

        $mail = (new ResetPassword('token-123'))->toMail($user);

        $this->assertSame('Restablecer contraseña | NutriTrack', $mail->subject);
        $this->assertSame('Crear contraseña nueva', $mail->actionText);
        $this->assertStringContainsString('/reset-password/token-123', $mail->actionUrl);
    }

    public function test_mail_failure_shows_error_instead_of_server_error(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'host-inexistente.invalid', 'mail.mailers.smtp.port' => 1025]);

        $user = User::factory()->create();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors(['email' => 'No pudimos enviar el correo de recuperación. Inténtalo más tarde o contacta al administrador.']);
    }

    public function test_access_pages_show_platform_description(): void
    {
        $this->withoutVite();

        foreach (['/login', '/forgot-password'] as $page) {
            $this->get($page)
                ->assertOk()
                ->assertSee('Dietas hospitalarias, de la prescripción a la bandeja.')
                ->assertDontSee('Próximo servicio');
        }
    }
}
