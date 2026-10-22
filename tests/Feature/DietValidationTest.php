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
 * un mensaje de validación, no un error 500. Dietas imposibles (SCRUM-17): sin tiempo de
 * comida, con valores fuera del catálogo o duplicadas en la habitación sin confirmarlo.
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
                'habitation' => 'Escribe la habitación con 4 caracteres como máximo.',
                'name_patient' => 'Escribe el nombre del paciente con 150 caracteres como máximo.',
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

    public function test_at_least_one_catalog_meal_is_required(): void
    {
        $this->register(['timeFood' => null])
            ->assertSessionHasErrors(['timeFood' => 'Elige al menos un tiempo de comida.']);
        $this->register(['timeFood' => []])->assertSessionHasErrors('timeFood');

        // Valores inventados enviados directamente al servidor
        $this->register(['timeFood' => ['banquete']])
            ->assertSessionHasErrors(['timeFood.0' => 'Elige los tiempos de comida de la lista.']);
        $this->register(['consistency' => ['gelatinosa']])->assertSessionHasErrors('consistency.0');
        $this->register(['specifications' => ['sin_sal_magica']])->assertSessionHasErrors('specifications.0');

        $this->assertSame(0, Diet::count());
    }

    public function test_legacy_meal_keys_are_normalized(): void
    {
        $this->register(['timeFood' => ['media_manana', 'Desayuno', 'mediamanana']])->assertSessionHasNoErrors();

        $this->assertSame(['mediamanana', 'desayuno'], Diet::firstOrFail()->currentVersion->timeFood);
    }

    public function test_room_with_an_active_diet_needs_confirmation(): void
    {
        $this->register(['habitation' => '999'])->assertSessionHasNoErrors();

        $this->register(['habitation' => '999', 'name_patient' => 'Otro'])
            ->assertSessionHasErrors(['habitation' => 'La habitación 999 ya tiene una dieta activa. Edítala o, si es una habitación compartida, marca «Registrar de todas formas».']);
        $this->assertSame(1, Diet::count());

        // Habitación compartida, confirmada
        $this->register(['habitation' => '999', 'name_patient' => 'Otro', 'allow_duplicate_room' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(2, Diet::where('habitation', '999')->count());
    }

    public function test_room_of_a_closed_diet_can_be_reused(): void
    {
        $diet = app(DietService::class)->createDiet(
            ['habitation' => '998', 'name_patient' => 'Anterior', 'timeFood' => ['desayuno'], 'billing_type' => 'normal'],
            $this->nutritionist->id,
        );
        app(DietService::class)->cancelDiet($diet, $this->nutritionist->id);

        $this->register(['habitation' => '998'])->assertSessionHasNoErrors();
    }

    public function test_new_version_also_requires_a_catalog_meal(): void
    {
        $diet = app(DietService::class)->createDiet(
            ['habitation' => '103', 'name_patient' => 'Paciente', 'timeFood' => ['desayuno'], 'billing_type' => 'normal'],
            $this->nutritionist->id,
        );

        $this->actingAs($this->nutritionist)
            ->put(route('dietas.update', $diet->id), ['timeFood' => [], 'billing_type' => 'normal'])
            ->assertSessionHasErrors('timeFood');
        $this->actingAs($this->nutritionist)
            ->put(route('dietas.update', $diet->id), ['timeFood' => ['banquete'], 'billing_type' => 'normal'])
            ->assertSessionHasErrors('timeFood.0');

        $this->assertSame(1, $diet->history()->count());
    }

    public function test_form_warns_about_rooms_with_an_active_diet(): void
    {
        $this->register(['habitation' => '997'])->assertSessionHasNoErrors();

        $this->activatePremium();
        $this->actingAs($this->nutritionist)->get(route('dashboard.nutrition'))
            ->assertOk()
            ->assertSee('window.ntActiveRooms = ["997"]', false)
            ->assertSee('Registrar de todas formas');
    }
}
