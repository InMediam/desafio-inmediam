<?php

namespace Tests\Feature;

use App\Enums\BillingStatus;
use App\Models\Billing;
use App\Models\Customer;
use App\Models\Plan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    public function test_show_returns_billing(): void
    {
        $plan = Plan::factory()->create();
        $customer = Customer::factory()->create();
        $billing = Billing::factory()->create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->getJson("/api/billing/{$billing->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $billing->id,
        ]);
    }

    public function test_show_returns_404_when_not_found(): void
    {
        $response = $this->getJson('/api/billing/999');

        $response->assertStatus(404);
        $response->assertJson([
            'error' => 'Cobrança não encontrada',
        ]);
    }

    public function test_pay_returns_validation_errors(): void
    {
        $response = $this->postJson('/api/billing/1/pay', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'card_holder_name',
            'card_number',
            'expiry_date',
            'cvv',
            'phone',
            'postal_code',
            'address_number',
        ]);
    }

    public function test_pay_success(): void
    {
        $plan = Plan::factory()->create(['price' => 100.00]);
        $customer = Customer::factory()->create();
        $billing = Billing::factory()->create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 100.00,
            'status' => BillingStatus::PENDING,
        ]);

        Http::fake([
            'sandbox.asaas.com/api/v3/customers' => Http::response([
                'id' => 'cus_123',
                'name' => $customer->name,
                'email' => $customer->email,
            ], 200),
            'sandbox.asaas.com/api/v3/payments' => Http::response([
                'id' => 'pay_123',
                'status' => 'PENDING',
            ], 200),
            'sandbox.asaas.com/api/v3/payments/*/payWithCreditCard' => Http::response([
                'id' => 'pay_123',
                'status' => 'CONFIRMED',
                'creditCard' => [
                    'creditCardNumber' => '1234',
                    'creditCardBrand' => 'Visa',
                    'creditCardToken' => 'tok_abc',
                ],
            ], 200),
        ]);

        $response = $this->postJson("/api/billing/{$billing->id}/pay", [
            'amount' => 100.00,
            'card_holder_name' => 'John Doe',
            'card_number' => '4111111111111111',
            'expiry_date' => '12/28',
            'cvv' => '123',
            'phone' => '11999999999',
            'postal_code' => '01001000',
            'address_number' => '100',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'confirmed',
        ]);
    }

    public function test_pay_returns_404_when_billing_not_found(): void
    {
        $response = $this->postJson('/api/billing/999/pay', [
            'amount' => 100.00,
            'card_holder_name' => 'John Doe',
            'card_number' => '4111111111111111',
            'expiry_date' => '12/28',
            'cvv' => '123',
            'phone' => '11999999999',
            'postal_code' => '01001000',
            'address_number' => '100',
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'error' => 'Billing not found',
        ]);
    }
}
