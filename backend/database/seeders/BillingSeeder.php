<?php

namespace Database\Seeders;

use App\Models\Billing;
use App\Models\Customer;
use App\Models\Plan;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::where('name', 'Profissional')->first();
        $customer = Customer::where('email', 'joao@email.com')->first();

        Billing::create([
            'uuid' => (string) Str::uuid(),
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => $plan->price,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDay()->toDateString(),
        ]);

        $plan = Plan::where('name', 'Básico')->first();
        $customer = Customer::where('email', 'maria@email.com')->first();

        Billing::create([
            'uuid' => (string) Str::uuid(),
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => $plan->price,
            'status' => 'paid',
            'due_date' => Carbon::now()->subDays(5)->toDateString(),
        ]);
    }
}
