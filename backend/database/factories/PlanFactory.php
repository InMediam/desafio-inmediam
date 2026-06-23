<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Básico', 'Profissional', 'Empresarial']),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 29, 199),
        ];
    }
}
