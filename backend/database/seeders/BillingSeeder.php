<?php

namespace Database\Seeders;

use App\Enums\BillingStatus;
use App\Models\Billing;
use App\Models\Customer;
use App\Models\Plan;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::where('name', 'Profissional')->firstOrFail();
        $customer = Customer::where('email', 'joao@email.com')->firstOrFail();

        Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => $plan->price,
            'status' => BillingStatus::PENDING->value,
            'due_date' => Carbon::now()->addDay()->toDateString(),
        ]);

        $plan = Plan::where('name', 'Básico')->firstOrFail();
        $customer = Customer::where('email', 'maria@email.com')->firstOrFail();

        Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => $plan->price,
            'status' => BillingStatus::PAID->value,
            'due_date' => Carbon::now()->subDays(5)->toDateString(),
        ]);
    }
}
