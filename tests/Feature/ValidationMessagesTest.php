<?php

namespace Tests\Feature;

use App\Livewire\PricesManager;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SCRUM-23 y SCRUM-24: mensajes de validación en español, con nombres de campo en español
 * y un solo estilo (segunda persona, qué hacer).
 */
class ValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_laravel_rule_has_a_spanish_message(): void
    {
        $en = Arr::dot(require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php'));
        $es = Arr::dot(require lang_path('es/validation.php'));

        $missing = array_diff(array_keys(array_filter($en, 'is_string')), array_keys($es));
        $this->assertSame([], array_values(array_filter($missing, fn ($k) => ! str_starts_with($k, 'custom.'))));
    }

    public function test_generic_messages_use_spanish_attribute_names(): void
    {
        $first = fn (array $data, array $rules) => validator($data, $rules)->errors()->first();

        $this->assertSame('Escribe en el campo precio un valor de 0 o más.', $first(['price' => -1], ['price' => 'numeric|min:0']));
        $this->assertSame('Completa el campo vigente desde.', $first([], ['validFrom' => 'required']));
        $this->assertSame('Completa el campo correo electrónico.', $first([], ['email' => 'required']));
        $this->assertSame('Escribe un correo electrónico válido.', $first(['email' => 'x'], ['email' => 'email']));
    }

    public function test_prices_form_errors_are_in_spanish(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->activatePremium();

        Livewire::actingAs(User::role('jefe_nutricion')->firstOrFail())->test(PricesManager::class)
            ->set('editType', 'normal')->set('editService', 'desayuno')
            ->set('price', '')->set('validFrom', '')
            ->call('save')
            ->assertHasErrors(['price', 'validFrom'])
            ->assertSee('Completa el campo vigente desde.')
            ->assertDontSee('valid from');
    }

    public function test_demo_form_beds_message_is_in_spanish(): void
    {
        $this->withoutVite();

        $this->from(route('demo.create'))->post(route('demo.store'), ['beds' => 0])
            ->assertSessionHasErrors(['beds' => 'Escribe un número de camas de 1 o más.']);
    }
}
