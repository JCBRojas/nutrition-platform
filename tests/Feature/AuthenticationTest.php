<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect($user->homeUrl());
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_login_lockout_redirects_to_login_with_custom_message(): void
    {
        $user = User::factory()->create();
        $throttleKey = Str::transliterate(Str::lower($user->email) . '|' . request()->ip());

        RateLimiter::clear($throttleKey);

        for ($i = 0; $i < 6; $i++) {
            $this->from('/login')->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');

        $errors = session('errors');
        $this->assertStringContainsString('Demasiados intentos de inicio de sesión', $errors->first('email'));
    }

    public function test_successful_login_resets_failed_attempts(): void
    {
        $user = User::factory()->create();
        $throttleKey = Str::transliterate(Str::lower($user->email) . '|' . request()->ip());

        RateLimiter::clear($throttleKey);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect($user->homeUrl());
        $this->assertAuthenticated();
        $this->assertFalse(RateLimiter::tooManyAttempts($throttleKey, 3));
    }
}
