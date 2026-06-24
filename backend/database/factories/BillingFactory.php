<?php

namespace Database\Factories;

use App\Enums\BillingStatus;
use App\Models\Billing;
use App\Models\Customer;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingFactory extends Factory
{
    protected $model = Billing::class;

    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'customer_id' => Customer::factory(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'status' => BillingStatus::PENDING,
            'due_date' => now()->addDay()->toDateString(),
        ];
    }
}
