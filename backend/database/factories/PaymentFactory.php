<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'billing_id' => Billing::factory(),
            'credit_card_id' => CreditCard::factory(),
            'amount_paid' => fake()->randomFloat(2, 10, 500),
            'status' => PaymentStatus::APPROVED,
            'paid_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Payment $payment) {
            if ($payment->creditCard->customer_id !== $payment->billing->customer_id) {
                $payment->creditCard->update(['customer_id' => $payment->billing->customer_id]);
            }
        });
    }
}
