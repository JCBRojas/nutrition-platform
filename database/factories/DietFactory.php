<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Diet>
 */
class DietFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Piso 1–4, cuarto 01–50
            'habitation' => fake()->numberBetween(1, 4).str_pad((string) fake()->numberBetween(1, 50), 2, '0', STR_PAD_LEFT),
            'name_patient' => fake()->name(),
            'status' => true,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => false]);
    }
}
