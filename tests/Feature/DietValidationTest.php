<?php

namespace Tests\Feature;

use App\Models\Diet;
use App\Models\User;
use App\Services\DietService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validación del registro de dietas en el servidor (informe de calidad 21/10/2026):
 * sin habitación o nombre (SCRUM-11) y textos demasiado largos (SCRUM-12) dan
 * un mensaje de validación, no un error 500.
 */
class DietValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $nutritionist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->nutritionist = User::role('nutricionista')->firstOrFail();
    }

    private function register(array $data)
    {
        return $this->actingAs($this->nutritionist)->post(route('dietas.create'), [
            'habitation' => '101', 'name_patient' => 'Paciente', 'timeFood' => ['desayuno'], 'billing_type' => 'normal', ...$data,
        ]);
    }

    public function test_room_and_patient_name_are_required(): void
    {
        $this->register(['habitation' => null, 'name_patient' => null])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'habitation' => 'Escribe la habitación.',
                'name_patient' => 'Escribe el nombre del paciente.',
            ]);

        // Solo espacios cuenta como vacío
        $this->register(['habitation' => '   ', 'name_patient' => '   '])
            ->assertSessionHasErrors(['habitation', 'name_patient']);

        // Petición JSON: 422
        $this->actingAs($this->nutritionist)
            ->postJson(route('dietas.create'), ['billing_type' => 'normal'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['habitation', 'name_patient']);

        $this->assertSame(0, Diet::count());
    }

    public function test_too_long_values_are_rejected(): void
    {
        $this->register(['habitation' => str_repeat('1', 300), 'name_patient' => str_repeat('a', 300)])
            ->assertSessionHasErrors([
                'habitation' => 'La habitación no puede tener más de 4 caracteres.',
                'name_patient' => 'El nombre del paciente no puede tener más de 150 caracteres.',
            ]);

        // Justo en el límite (4) se acepta; con 5 no
        $this->register(['habitation' => '12345'])->assertSessionHasErrors('habitation');
        $this->assertSame(0, Diet::count());
        $this->register(['habitation' => '1205'])->assertSessionHasNoErrors();
        $this->assertSame(1, Diet::count());
    }

    public function test_valid_diet_is_registered_trimmed(): void
    {
        $this->register(['habitation' => ' 205 ', 'name_patient' => ' Ana Gómez '])->assertSessionHasNoErrors();

        $this->assertSame('205', Diet::firstOrFail()->habitation);
        $this->assertSame('Ana Gómez', Diet::firstOrFail()->name_patient);
    }

    public function test_new_version_does_not_require_room_or_name(): void
    {
        $diet = app(DietService::class)->createDiet(
            ['habitation' => '102', 'name_patient' => 'Paciente', 'timeFood' => ['desayuno'], 'billing_type' => 'normal'],
            $this->nutritionist->id,
        );

        $this->actingAs($this->nutritionist)
            ->put(route('dietas.update', $diet->id), ['timeFood' => ['almuerzo'], 'billing_type' => 'normal'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $diet->history()->count());
    }

    public function test_form_marks_the_fields_as_required_with_a_limit(): void
    {
        $this->activatePremium();
        $this->actingAs($this->nutritionist)->get(route('dashboard.nutrition'))
            ->assertOk()
            ->assertSee('name="habitation" placeholder="Ej. 205"', false)
            ->assertSee('maxlength="4"', false)
            ->assertSee('maxlength="150"', false);
    }
}
