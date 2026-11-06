<?php

namespace Database\Seeders;

use App\Models\Billing;
use App\Models\Customer;
use App\Models\Plan;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::where('name', 'Profissional')->first();
        $customer = Customer::where('email', 'joao@email.com')->first();

        Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => $plan->price,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDay()->toDateString(),
        ]);

        $plan = Plan::where('name', 'Básico')->first();
        $customer = Customer::where('email', 'maria@email.com')->first();

        Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => $plan->price,
            'status' => 'paid',
            'due_date' => Carbon::now()->subDays(5)->toDateString(),
        ]);

        $plan = Plan::where('name', 'Empresarial')->first();
        $customer = Customer::where('email', 'empresa@email.com')->first();

        Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => $plan->price,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDay()->toDateString(),
        ]);
    }
}
