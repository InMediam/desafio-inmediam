<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Customer;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_404_when_billing_does_not_exist(): void
    {
        $response = $this->getJson('/api/billing/999999');

        $response->assertStatus(404);
    }

    public function test_it_returns_409_when_billing_is_already_paid(): void
    {
        $customer = Customer::create([
            'name' => 'Cliente Teste',
            'email' => 'teste@example.com',
            'document' => '12345678901',
        ]);

        $plan = Plan::create([
            'name' => 'Plano Teste',
            'description' => 'Plano utilizado nos testes',
            'price' => 79.90,
            'active' => true,
        ]);

        $billing = Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 79.90,
            'status' => 'paid',
            'due_date' => now()->toDateString(),
        ]);

        $response = $this->postJson("/api/billing/{$billing->id}/pay", [
            'card_holder_name' => 'Cliente Teste',
            'card_number' => '4111111111111111',
            'expiry_date' => '12/30',
            'cvv' => '123',
        ]);

        $response->assertStatus(409);
    }

    public function test_it_rejects_an_expired_card(): void
    {
        $customer = Customer::create([
            'name' => 'Cliente Teste',
            'email' => 'teste2@example.com',
            'document' => '12345678902',
        ]);

        $plan = Plan::create([
            'name' => 'Plano Teste',
            'description' => 'Plano utilizado nos testes',
            'price' => 79.90,
            'active' => true,
        ]);

        $billing = Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 79.90,
            'status' => 'pending',
            'due_date' => now()->toDateString(),
        ]);

        $response = $this->postJson("/api/billing/{$billing->id}/pay", [
            'card_holder_name' => 'Cliente Teste',
            'card_number' => '4111111111111111',
            'expiry_date' => '01/20',
            'cvv' => '123',
        ]);

        $response->assertStatus(422);
    }

    public function test_it_uses_the_billing_amount_instead_of_the_amount_sent_by_the_client(): void
    {
        Http::fake([
            'sandbox.asaas.com/api/v3/customers' => Http::response([
                'id' => 'cus_test_123',
            ], 200),

            'sandbox.asaas.com/api/v3/payments' => Http::response([
                'id' => 'pay_test_123',
            ], 200),

            'sandbox.asaas.com/api/v3/payments/pay_test_123/payWithCreditCard' => Http::response([
                'status' => 'CONFIRMED',
                'creditCard' => [
                    'creditCardToken' => 'token_test_123',
                    'creditCardNumber' => '1234',
                    'creditCardBrand' => 'VISA',
                ],
            ], 200),
        ]);

        $customer = Customer::create([
            'name' => 'Cliente Teste',
            'email' => 'teste3@example.com',
            'document' => '12345678903',
        ]);

        $plan = Plan::create([
            'name' => 'Plano Teste',
            'description' => 'Plano utilizado nos testes',
            'price' => 79.90,
            'active' => true,
        ]);

        $billing = Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 79.90,
            'status' => 'pending',
            'due_date' => now()->toDateString(),
        ]);

        $response = $this->postJson("/api/billing/{$billing->id}/pay", [
            'amount' => 1.00,
            'card_holder_name' => 'Cliente Teste',
            'card_number' => '4111111111111111',
            'expiry_date' => '12/30',
            'cvv' => '123',
        ]);

        $response->assertStatus(201);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sandbox.asaas.com/api/v3/payments'
                && $request['value'] === 79.90;
        });

        $this->assertDatabaseHas('billings', [
            'id' => $billing->id,
            'amount' => 79.90,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('payments', [
            'billing_id' => $billing->id,
            'amount_paid' => 79.90,
            'status' => 'CONFIRMED',
        ]);
    }
}