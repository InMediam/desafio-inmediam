<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_billing_details(): void
    {
        $billing = Billing::factory()->create();

        $response = $this->getJson("/api/billing/{$billing->id}");

        $response->assertOk();
        $response->assertJsonFragment(['id' => $billing->id]);
    }

    public function test_it_returns_the_customer_name(): void
    {
        $customer = Customer::factory()->create(['name' => 'Empresa Exemplo Ltda']);
        $billing = Billing::factory()->for($customer)->create();

        $response = $this->getJson("/api/billing/{$billing->id}");

        $response->assertOk();
        $response->assertJsonFragment(['customer' => ['name' => 'Empresa Exemplo Ltda']]);
    }

    public function test_it_returns_404_when_billing_does_not_exist(): void
    {
        $response = $this->getJson('/api/billing/999999');

        $response->assertStatus(404);
    }

    public function test_it_returns_404_for_a_non_numeric_billing_id(): void
    {
        $response = $this->getJson('/api/billing/not-a-valid-id');

        $response->assertStatus(404);
    }

    public function test_it_does_not_leak_the_credit_card_token_for_a_paid_billing(): void
    {
        $billing = Billing::factory()->paid()->create();

        $response = $this->getJson("/api/billing/{$billing->id}");

        $response->assertOk();
        $this->assertStringNotContainsString('card_token', $response->getContent());
    }

    public function test_a_paid_billing_response_does_not_expose_internal_or_sensitive_fields(): void
    {
        $billing = Billing::factory()->paid()->create();

        $response = $this->getJson("/api/billing/{$billing->id}");

        $response->assertOk();

        $forbiddenFields = [
            'asaas_charge_id',
            'idempotency_key',
            'customer_id',
            'plan_id',
            'credit_card_id',
            'billing_id',
            'created_at',
            'updated_at',
            'card_token',
            'document',
            'email',
        ];

        foreach ($forbiddenFields as $field) {
            $this->assertStringNotContainsString('"'.$field.'"', $response->getContent());
        }
    }
}
