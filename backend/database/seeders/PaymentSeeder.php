<?php

namespace Database\Seeders;

use App\Enums\BillingStatus;
use App\Enums\PaymentStatus;
use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Customer;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $customer = Customer::where('email', 'maria@email.com')->firstOrFail();
        $billing = Billing::where('customer_id', $customer->id)
            ->where('status', BillingStatus::PAID->value)
            ->firstOrFail();

        $creditCard = CreditCard::create([
            'customer_id' => $customer->id,
            'card_holder_name' => 'MARIA OLIVEIRA',
            'card_last_four' => '1234',
            'card_brand' => 'VISA',
            'card_token' => 'sandbox_token_abc123',
        ]);

        Payment::create([
            'billing_id' => $billing->id,
            'credit_card_id' => $creditCard->id,
            'amount_paid' => $billing->amount,
            'status' => PaymentStatus::CONFIRMED->value,
            'paid_at' => Carbon::now()->subDays(3),
        ]);
    }
}
