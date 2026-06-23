<?php

namespace Database\Factories;

use App\Models\Billing;
use App\Models\Customer;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Billing>
 */
class BillingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(), // Cria um cliente mockado automaticamente
            'plan_id' => Plan::factory(),         // Cria um plano mockado automaticamente
            'amount' => $this->faker->randomFloat(2, 29, 199),
            'due_date' => now()->addDays(5),
            'status' => 'pending',
        ];
    }
}
