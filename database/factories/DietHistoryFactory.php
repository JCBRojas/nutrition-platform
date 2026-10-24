<?php

namespace Database\Factories;

use App\Models\DietHistory;
use App\Services\BillingService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Las claves de timeFood, consistency y specifications salen de config/*.php
 * para que todas las vistas encuentren su etiqueta.
 *
 * @extends Factory<DietHistory>
 */
class DietHistoryFactory extends Factory
{
    public const OBSERVATIONS = [
        'Paciente diabético, controlar porciones',
        'Alergia a mariscos',
        'Ofrecer líquidos cada 2 horas',
        'Dificultad para deglutir',
        'Postoperatorio, iniciar tolerancia',
        'Preferencia vegetariana',
        'Sin azúcar añadida',
        'Fraccionar en porciones pequeñas',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $consistency = [fake()->randomElement(array_keys(config('consistency.labels')))];
        $specifications = $this->randomSpecifications();

        return [
            'version' => 1,
            'timeFood' => $this->randomTimeFood(),
            'consistency' => $consistency,
            'specifications' => $specifications,
            'billing_type' => $this->billingTypeFor($consistency, $specifications),
            'observations' => fake()->boolean(40) ? fake()->randomElement(self::OBSERVATIONS) : null,
            'isolation' => fake()->boolean(15) ? 'Sí' : 'No',
            'changes' => 'NA',
            'modify' => 'no',
        ];
    }

    /**
     * 2–4 tiempos de comida; casi siempre incluye desayuno, almuerzo y cena
     */
    public function randomTimeFood(): array
    {
        if (fake()->boolean(10)) {
            return ['fraccionada', fake()->randomElement(['desayuno', 'almuerzo', 'cena'])];
        }

        $meals = fake()->boolean(85) ? ['desayuno', 'almuerzo', 'cena'] : fake()->randomElements(['desayuno', 'almuerzo', 'cena'], 2);
        $snacks = fake()->randomElements(['mediamanana', 'algo', 'merienda'], fake()->numberBetween(0, 4 - count($meals)));

        return array_values(array_merge($meals, $snacks));
    }

    /** Tipo de facturación: la misma regla de la aplicación (BillingService::typeFor) */
    public function billingTypeFor(array $consistency, array $specifications): string
    {
        return BillingService::typeFor($consistency, $specifications) ?? config('billing.type_from.default');
    }

    public function randomSpecifications(): array
    {
        return fake()->randomElements(array_keys(config('specifications.labels')), fake()->numberBetween(0, 3));
    }
}
