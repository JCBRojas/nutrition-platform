<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_profile_information_is_available(): void
    {
        $this->actingAs($user = User::factory()->create());

        $component = Livewire::test(UpdateProfileInformationForm::class);

        $this->assertEquals($user->name, $component->state['name']);
        $this->assertEquals($user->email, $component->state['email']);
    }

    public function test_profile_name_can_be_updated_but_not_the_email(): void
    {
        $this->actingAs($user = User::factory()->create());
        $email = $user->email;

        // Aunque se manipule el formulario, el correo (usuario de acceso) no cambia
        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', ['name' => 'Test Name', 'email' => 'test@example.com'])
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertEquals('Test Name', $user->fresh()->name);
        $this->assertEquals($email, $user->fresh()->email);
    }

    public function test_profile_page_is_in_spanish_without_account_deletion(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create());

        $this->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Verificación en dos pasos')
            ->assertSee('solo un administrador puede cambiarlo')
            ->assertDontSee('Two Factor Authentication')
            ->assertDontSee('Eliminar cuenta');
    }
}
