<?php

namespace Tests\Feature;

use App\Models\Diet;
use App\Models\DietHistory;
use App\Services\Reports\DietReportService;
use Database\Seeders\DietDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DietDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_50_diets_with_5_consistent_versions_each(): void
    {
        $this->seed(DietDemoSeeder::class);

        $this->assertSame(50, Diet::count());
        $this->assertSame(250, DietHistory::count());

        foreach (Diet::with(['history' => fn ($q) => $q->orderBy('version')])->get() as $diet) {
            $versions = $diet->history;

            $this->assertSame([1, 2, 3, 4, 5], $versions->pluck('version')->all());
            $this->assertTrue($diet->created_at->equalTo($versions->first()->created_at));

            // Fechas estrictamente crecientes y nunca en el futuro
            $versions->sliding(2)->each(fn ($pair) => $this->assertTrue($pair->first()->created_at < $pair->last()->created_at));
            $this->assertTrue($versions->last()->created_at <= now());

            // status=false ⇔ v5 cancelada (y la cabecera guarda la fecha de cancelación)
            $cancelled = $versions->last()->changes === 'cancelada';
            $this->assertSame(! $cancelled, $diet->status);
            if ($cancelled) {
                $this->assertTrue($diet->updated_at->equalTo($versions->last()->created_at));
            }
            $this->assertSame(0, $versions->slice(0, 4)->where('changes', 'cancelada')->count());
            $this->assertSame('NA', $versions->first()->changes);

            foreach ($versions as $v) {
                $this->assertContains($v->isolation, ['Sí', 'No']);
                foreach (['timeFood', 'consistency', 'specifications'] as $catalog) {
                    foreach ((array) $v->{$catalog} as $key) {
                        $this->assertArrayHasKey($key, config("{$catalog}.labels"), "{$catalog}: {$key}");
                    }
                }
                $this->assertNotEmpty($v->timeFood);
            }
        }
    }

    public function test_seeded_data_feeds_the_monthly_report(): void
    {
        $this->seed(DietDemoSeeder::class);

        $census = app(DietReportService::class)->monthlyCensus(now());

        $this->assertGreaterThan(0, $census['totalTrays']);
        $this->assertGreaterThan(0, $census['perDiet']->count());
    }

    public function test_does_not_run_in_production(): void
    {
        $this->app['env'] = 'production';

        // Directo: db:seed en producción pide confirmación interactiva
        $this->app->make(DietDemoSeeder::class)->run();

        $this->assertSame(0, Diet::count());
    }
}
