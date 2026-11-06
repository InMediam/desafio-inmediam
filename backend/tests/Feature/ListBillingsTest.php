<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Customer;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListBillingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_billings_with_customer_name_and_plan_name(): void
    {
        $customer = Customer::factory()->create(['name' => 'Empresa Exemplo Ltda']);
        $plan = Plan::factory()->create(['name' => 'Empresarial']);
        $billing = Billing::factory()->for($customer)->for($plan)->pending()->create();

        $response = $this->getJson('/api/billings');

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $billing->id,
            'status' => 'pending',
        ]);
        $this->assertStringContainsString('Empresa Exemplo Ltda', $response->getContent());
        $this->assertStringContainsString('Empresarial', $response->getContent());
    }

    public function test_it_orders_billings_by_id(): void
    {
        $third = Billing::factory()->create();
        $first = Billing::factory()->create();
        $second = Billing::factory()->create();

        $response = $this->getJson('/api/billings');

        $response->assertOk();
        $ids = array_column($response->json(), 'id');
        $expectedOrder = collect([$third, $first, $second])->pluck('id')->sort()->values()->all();

        $this->assertSame($expectedOrder, $ids);
    }

    public function test_it_does_not_expose_internal_or_sensitive_fields(): void
    {
        Billing::factory()->create();

        $response = $this->getJson('/api/billings');

        $response->assertOk();

        $forbiddenFields = [
            'amount',
            'due_date',
            'plan_id',
            'customer_id',
            'document',
            'email',
            'phone',
            'postal_code',
            'address_number',
            'created_at',
            'updated_at',
        ];

        foreach ($forbiddenFields as $field) {
            $this->assertStringNotContainsString('"'.$field.'"', $response->getContent());
        }
    }
}
