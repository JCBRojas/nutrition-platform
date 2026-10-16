<?php

namespace Database\Seeders;

use App\Models\Diet;
use App\Models\DietHistory;
use App\Models\User;
use Database\Factories\DietHistoryFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Datos de prueba: 50 dietas con 5 versiones cada una.
 * Solo agrega registros (no borra). Uso: php artisan db:seed --class=DietDemoSeeder
 */
class DietDemoSeeder extends Seeder
{
    private const DIETS = 50;
    private const VERSIONS = 5;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DietDemoSeeder no se ejecuta en producción.');
            return;
        }

        // User::role() lanza excepción si el rol no existe todavía (BD recién migrada)
        $nutritionist = Role::where('name', 'nutricionista')->exists()
            ? User::role('nutricionista')->first()
            : null;

        if (! $nutritionist) {
            $this->call(RolesAndPermissionsSeeder::class);
            $nutritionist = User::role('nutricionista')->firstOrFail();
        }

        $factory = DietHistory::factory();

        $cancelled = DB::transaction(function () use ($nutritionist, $factory) {
            $cancelled = 0;
            for ($i = 0; $i < self::DIETS; $i++) {
                $cancelled += $this->seedDiet($nutritionist->id, $factory) ? 1 : 0;
            }

            return $cancelled;
        });

        $this->command?->info(sprintf(
            'Creadas %d dietas (%d activas, %d canceladas) con %d versiones.',
            self::DIETS,
            self::DIETS - $cancelled,
            $cancelled,
            self::DIETS * self::VERSIONS,
        ));
    }

    /**
     * @return bool true si la dieta quedó cancelada
     */
    private function seedDiet(int $userId, DietHistoryFactory $factory): bool
    {
        // 4 saltos de máx. 5 días = 20 días: empezar hace ≥ 21 días evita versiones en el futuro
        $createdAt = Carbon::now()->subDays(fake()->numberBetween(21, 90))->setTime(fake()->numberBetween(6, 20), fake()->numberBetween(0, 59));
        $cancelled = fake()->boolean(20);

        $diet = Diet::factory()->create();
        $this->stamp($diet, $createdAt, $createdAt);

        // v1: estado inicial
        $state = $factory->raw();
        $at = $createdAt;
        $this->createVersion($diet, $userId, 1, $at, $state);

        // v2–v5: cada versión parte de la anterior y cambia 1–2 campos
        for ($version = 2; $version <= self::VERSIONS; $version++) {
            $at = $at->copy()->addDays(fake()->numberBetween(1, 5))->setTime(fake()->numberBetween(6, 20), fake()->numberBetween(0, 59));

            if ($version === self::VERSIONS && $cancelled) {
                $state = array_merge($state, ['changes' => 'cancelada']);
            } else {
                $state = $this->mutate($state, $factory);
            }

            $isLast = $version === self::VERSIONS;
            $state['modify'] = $isLast && ! $cancelled && fake()->boolean(30) ? 'si' : 'no';

            $this->createVersion($diet, $userId, $version, $at, $state);
        }

        // Igual que DietService::cancelDiet: status=false y updated_at = fecha de cancelación
        $diet->forceFill(['status' => $cancelled ? Diet::CANCELLED : Diet::ACTIVE])->save();
        $this->stamp($diet, $createdAt, $at);

        return $cancelled;
    }

    private function mutate(array $state, DietHistoryFactory $factory): array
    {
        $fields = fake()->randomElements(['timeFood', 'consistency', 'specifications', 'observations', 'isolation'], fake()->numberBetween(1, 2));
        $changes = 'con_cambios';

        foreach ($fields as $field) {
            switch ($field) {
                case 'timeFood':
                    $state['timeFood'] = $factory->randomTimeFood();
                    break;
                case 'consistency':
                    $state['consistency'] = [fake()->randomElement(array_keys(config('consistency.labels')))];
                    break;
                case 'specifications':
                    $state['specifications'] = $factory->randomSpecifications();
                    break;
                case 'observations':
                    $state['observations'] = fake()->boolean(70) ? fake()->randomElement(DietHistoryFactory::OBSERVATIONS) : null;
                    break;
                case 'isolation':
                    $state['isolation'] = $state['isolation'] === 'Sí' ? 'No' : 'Sí';
                    $changes = $state['isolation'] === 'Sí' ? 'aislado' : 'ya_no_aislado';
                    break;
            }
        }

        $state['changes'] = $changes;
        $state['billing_type'] = $factory->billingTypeFor((array) $state['consistency'], (array) $state['specifications']);

        return $state;
    }

    private function createVersion(Diet $diet, int $userId, int $version, Carbon $at, array $state): void
    {
        $history = DietHistory::create(array_merge($state, [
            'diet_id' => $diet->id,
            'updated_by' => $userId,
            'version' => $version,
        ]));

        $this->stamp($history, $at, $at);
    }

    private function stamp($model, Carbon $createdAt, Carbon $updatedAt): void
    {
        $model->forceFill(['created_at' => $createdAt, 'updated_at' => $updatedAt])->saveQuietly();
    }
}
