<?php

namespace Tests\Feature;

use App\Models\Diet;
use App\Models\User;
use App\Services\DietService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DietIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $nutritionist;

    private Diet $diet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->nutritionist = User::role('nutricionista')->first();
        $this->diet = app(DietService::class)->createDiet(
            ['habitation' => '101', 'name_patient' => 'Paciente', 'timeFood' => ['desayuno'], 'consistency' => ['normal'], 'specifications' => ['hiposodica']],
            $this->nutritionist->id,
        );
    }

    private function update(array $data)
    {
        return $this->actingAs($this->nutritionist)
            ->put(route('dietas.update', $this->diet), $data + ['timeFood' => ['desayuno'], 'consistency' => ['normal'], 'specifications' => ['hiposodica'], 'changes' => 'aislado', 'billing_type' => 'normal']);
    }

    /** @dataProvider accepted */
    public function test_isolation_values_from_forms_are_stored_as_enum(string $sent, string $stored): void
    {
        $this->update(['isolation' => $sent])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($stored, $this->diet->fresh()->currentVersion->isolation);
    }

    public static function accepted(): array
    {
        return [
            'Sí (modal crear)' => ['Sí', 'Sí'],
            'Si sin tilde (formularios antiguos)' => ['Si', 'Sí'],
            'minúsculas' => ['si', 'Sí'],
            'No' => ['No', 'No'],
            'no' => ['no', 'No'],
        ];
    }

    public function test_invalid_isolation_is_rejected(): void
    {
        $this->update(['isolation' => 'tal vez'])->assertSessionHasErrors('isolation');

        $this->assertSame(1, $this->diet->history()->count());
    }

    public function test_edit_modal_offers_the_stored_values(): void
    {
        $html = $this->actingAs($this->nutritionist)->get(route('dashboard.nutrition'))->assertOk()->getContent();

        $this->assertStringContainsString('<option value="Sí">Sí</option>', $html);
        $this->assertStringNotContainsString('<option value="Si"', $html);
    }
}
