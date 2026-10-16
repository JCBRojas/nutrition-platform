<?php

namespace Tests\Feature;

use App\Models\Diet;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DietService;
use App\Services\Reports\DietReportService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/bussiness-rules/facturacion-alta-paciente.md: los tiempos de comida
 * programados después de la hora del alta no se cuentan en ningún cálculo.
 */
class DischargeMealCutoffTest extends TestCase
{
    use RefreshDatabase;

    private User $nutritionist;

    private Diet $diet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->nutritionist = User::role('nutricionista')->firstOrFail();

        $this->travelTo(CarbonImmutable::parse('2026-10-01 05:00:00'));
        $this->diet = app(DietService::class)->createDiet(
            ['habitation' => '410', 'name_patient' => 'Luis', 'timeFood' => ['desayuno', 'almuerzo', 'cena', 'fraccionada'], 'billing_type' => 'normal'],
            $this->nutritionist->id,
        );
    }

    private function dischargeAt(string $moment): void
    {
        $this->travelTo(CarbonImmutable::parse($moment));
        app(DietService::class)->finishDiet($this->diet->fresh(), $this->nutritionist->id);
        $this->travelTo(CarbonImmutable::parse('2026-10-15 20:00:00'));
    }

    private function day(int $day): array
    {
        $census = app(DietReportService::class)->monthlyCensus(CarbonImmutable::parse('2026-10-01'));

        return $census['days']->first(fn ($row) => (int) $row['date']->format('j') === $day);
    }

    public function test_discharge_at_15_counts_breakfast_and_lunch_but_not_dinner(): void
    {
        $this->dischargeAt('2026-10-03 15:00:00');

        $day = $this->day(3);
        $this->assertSame(1, $day['meals']['desayuno']);
        $this->assertSame(1, $day['meals']['almuerzo']);
        $this->assertSame(0, $day['meals']['cena']);
        $this->assertSame(0, $day['meals']['fraccionada']); // sin horario: no cuenta el día del alta
        $this->assertSame(2, $day['total']);

        // Días completos antes del alta: los 4 tiempos; después del alta: nada
        $this->assertSame(4, $this->day(2)['total']);
        $this->assertSame(0, $this->day(4)['total']);

        $billing = collect(app(BillingService::class)->monthly(CarbonImmutable::parse('2026-10-01'))['rows'])
            ->keyBy(fn ($r) => "{$r['type']}.{$r['service']}");
        $this->assertSame(1, $billing['normal.almuerzo']['days'][3]);
        $this->assertArrayNotHasKey(3, $billing['normal.cena']['days']);
        $this->assertSame(2, $billing['normal.cena']['count']); // días 1 y 2
    }

    public function test_meal_at_the_exact_discharge_time_counts(): void
    {
        $this->dischargeAt('2026-10-03 12:00:00'); // almuerzo programado a las 12:00

        $this->assertSame(1, $this->day(3)['meals']['almuerzo']);
        $this->assertSame(0, $this->day(3)['meals']['cena']);
    }

    public function test_discharge_before_breakfast_does_not_count_the_day(): void
    {
        $this->dischargeAt('2026-10-03 05:30:00');

        $this->assertSame(0, $this->day(3)['total']);
        $this->assertSame(0, $this->day(3)['diets']);
        $this->assertSame(2, app(DietReportService::class)->monthlyCensus(CarbonImmutable::parse('2026-10-01'))['dietDays']);
    }

    public function test_cancellation_keeps_the_end_of_day_rule(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-03 15:00:00'));
        app(DietService::class)->cancelDiet($this->diet->fresh(), $this->nutritionist->id);
        $this->travelTo(CarbonImmutable::parse('2026-10-15 20:00:00'));

        $this->assertSame(0, $this->day(3)['total']);
        $this->assertSame(4, $this->day(2)['total']);
    }
}
