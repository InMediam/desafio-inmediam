<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_billing_details(): void
    {
        $billing = $this->makePendingBilling();

        $this->getJson("/api/billing/{$billing->id}")
            ->assertOk()
            ->assertJsonPath('id', $billing->id)
            ->assertJsonPath('plan.name', 'Profissional')
            ->assertJsonPath('status', 'pending');
    }

    public function test_it_processes_a_pending_billing_payment(): void
    {
        $billing = $this->makePendingBilling();

        config()->set('services.asaas.api_key', 'test-token');
        config()->set('services.asaas.base_url', 'https://sandbox.asaas.com/api/v3');

        Http::fake(function (Request $request) {
            return match (true) {
                str_contains($request->url(), '/customers') => Http::response([
                    'id' => 'cus_123',
                ], 200),
                str_contains($request->url(), '/payWithCreditCard') => Http::response([
                    'status' => 'CONFIRMED',
                    'creditCard' => [
                        'creditCardBrand' => 'VISA',
                        'creditCardToken' => 'tok_123',
                    ],
                ], 200),
                str_contains($request->url(), '/payments') => Http::response([
                    'id' => 'pay_123',
                ], 200),
                default => Http::response([], 404),
            };
        });

        $this->postJson("/api/billing/{$billing->id}/pay", [
            'card_holder_name' => 'João da Silva',
            'card_number' => '4242 4242 4242 4242',
            'expiry_date' => '12/29',
            'cvv' => '123',
            'amount' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'CONFIRMED')
            ->assertJsonPath('credit_card.card_last_four', '4242');

        $billing->refresh();

        $this->assertSame('paid', $billing->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('credit_cards', 1);

        $payment = Payment::query()->with('creditCard')->firstOrFail();
        $this->assertSame('CONFIRMED', $payment->status);
        $this->assertSame('79.90', $payment->amount_paid);
        $this->assertSame('4242', $payment->creditCard->card_last_four);
        $this->assertSame('tok_123', $payment->creditCard->card_token ?? null);
    }

    public function test_it_rejects_already_paid_billings_without_calling_the_gateway(): void
    {
        $billing = $this->makePaidBilling();

        Http::fake();

        $this->postJson("/api/billing/{$billing->id}/pay", [
            'card_holder_name' => 'Maria Oliveira',
            'card_number' => '4111111111111111',
            'expiry_date' => '12/29',
            'cvv' => '123',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Esta cobrança já foi processada.');

        Http::assertNothingSent();
        $this->assertDatabaseCount('payments', 1);
    }

    private function makePendingBilling(): Billing
    {
        $plan = Plan::create([
            'name' => 'Profissional',
            'description' => 'Plano para equipes pequenas.',
            'price' => 79.90,
            'active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'João da Silva',
            'email' => 'joao@example.com',
            'document' => '45596714003',
        ]);

        return Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 79.90,
            'status' => 'pending',
            'due_date' => now()->addDay()->toDateString(),
        ]);
    }

    private function makePaidBilling(): Billing
    {
        $plan = Plan::create([
            'name' => 'Básico',
            'description' => 'Plano básico.',
            'price' => 29.90,
            'active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Maria Oliveira',
            'email' => 'maria@example.com',
            'document' => '65577833000',
        ]);

        $billing = Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 29.90,
            'status' => 'paid',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $creditCard = CreditCard::create([
            'customer_id' => $customer->id,
            'card_holder_name' => 'Maria Oliveira',
            'card_last_four' => '1234',
            'card_brand' => 'VISA',
            'card_token' => 'sandbox_token_abc123',
        ]);

        Payment::create([
            'billing_id' => $billing->id,
            'credit_card_id' => $creditCard->id,
            'amount_paid' => 29.90,
            'status' => 'CONFIRMED',
            'paid_at' => now(),
        ]);

        return $billing;
    }
}
