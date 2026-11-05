<?php

namespace Database\Factories;

use App\Models\Billing;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Billing>
 */
class BillingFactory extends Factory
{
    protected $model = Billing::class;

    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'customer_id' => Customer::factory(),
            'amount' => fn (array $attributes) => Plan::find($attributes['plan_id'])->price,
            'status' => 'pending',
            'due_date' => now()->addDays(7)->toDateString(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
        ])->afterCreating(function (Billing $billing) {
            Payment::factory()->for($billing)->create();
        });
    }
}
