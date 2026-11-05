<?php

namespace Database\Factories;

use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'billing_id' => Billing::factory(),
            'credit_card_id' => CreditCard::factory(),
            'amount_paid' => fn (array $attributes) => Billing::find($attributes['billing_id'])->amount,
            'status' => 'CONFIRMED',
            'paid_at' => now(),
        ];
    }

    /**
     * Charge created but not yet confirmed — real Asaas status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'PENDING',
            'paid_at' => null,
        ]);
    }

    /**
     * Failed attempt. Not an Asaas payment status (a failed charge never
     * becomes a Payment with a status there) — this marks our own record
     * of an unsuccessful attempt once the controller is fixed to store one.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'FAILED',
            'paid_at' => null,
        ]);
    }
}
