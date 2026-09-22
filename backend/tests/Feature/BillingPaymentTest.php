<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_uses_billing_amount_instead_of_client_amount(): void
    {
        $plan = Plan::create([
            'name' => 'Profissional',
            'description' => 'Plano profissional',
            'price' => 79.90,
            'active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'João da Silva',
            'email' => 'joao@example.com',
            'document' => '45596714003',
        ]);

        $billing = Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 79.90,
            'status' => 'pending',
            'due_date' => now()->toDateString(),
        ]);

        Http::fake([
            '*/customers' => Http::response([
                'id' => 'cus_test',
            ], 200),

            '*/payments' => function ($request) {
                if ($request->method() === 'POST') {
                    return Http::response([
                        'id' => 'pay_test',
                        'status' => 'PENDING',
                    ], 200);
                }

                return Http::response([], 200);
            },

            '*/payments/pay_test/payWithCreditCard' => Http::response([
                'id' => 'pay_test',
                'status' => 'CONFIRMED',
                'creditCard' => [
                    'creditCardNumber' => '****1234',
                    'creditCardBrand' => 'VISA',
                    'creditCardToken' => 'token_test',
                ],
            ], 200),
        ]);

        $response = $this->postJson(
            "/api/billing/{$billing->id}/pay",
            [
                'amount' => 1.00,
                'card_holder_name' => 'João da Silva',
                'card_number' => '4111111111111111',
                'expiry_date' => '12/30',
                'cvv' => '123',
            ]
        );

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            return $request->url() === config('services.asaas.base_url').'/payments'
                && (float) $request['value'] === 79.90;
        });
    }

    public function test_payment_is_not_completed_when_asaas_does_not_confirm(): void
    {
        $plan = Plan::create([
            'name' => 'Profissional',
            'description' => 'Plano profissional',
            'price' => 79.90,
            'active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'João da Silva',
            'email' => 'joao2@example.com',
            'document' => '45596714003',
        ]);

        $billing = Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 79.90,
            'status' => 'pending',
            'due_date' => now()->toDateString(),
        ]);

        Http::fake([
            '*/customers' => Http::response([
                'id' => 'cus_test',
            ], 200),

            '*/payments' => Http::response([
                'id' => 'pay_test',
                'status' => 'PENDING',
            ], 200),

            '*/payments/pay_test/payWithCreditCard' => Http::response([
                'id' => 'pay_test',
                'status' => 'PENDING',
            ], 200),
        ]);

        $response = $this->postJson(
            "/api/billing/{$billing->id}/pay",
            [
                'card_holder_name' => 'João da Silva',
                'card_number' => '4111111111111111',
                'expiry_date' => '12/30',
                'cvv' => '123',
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseHas('billings', [
            'id' => $billing->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_returns_502_when_asaas_returns_an_error(): void
    {
        $plan = Plan::create([
            'name' => 'Profissional',
            'description' => 'Plano profissional',
            'price' => 79.90,
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
            'amount' => 79.90,
            'status' => 'pending',
            'due_date' => now()->toDateString(),
        ]);

        Http::fake([
            '*/customers' => Http::response([
                'message' => 'Erro interno',
            ], 500),
        ]);

        $response = $this->postJson(
            "/api/billing/{$billing->id}/pay",
            [
                'card_holder_name' => 'Maria Oliveira',
                'card_number' => '4111111111111111',
                'expiry_date' => '12/30',
                'cvv' => '123',
            ]
        );

        $response->assertStatus(502)
            ->assertJson([
                'message' => 'Não foi possível processar o pagamento.',
            ]);

        $this->assertDatabaseHas('billings', [
            'id' => $billing->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_returns_503_when_asaas_connection_fails(): void
    {
        $plan = Plan::create([
            'name' => 'Profissional',
            'description' => 'Plano profissional',
            'price' => 79.90,
            'active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Carlos Silva',
            'email' => 'carlos@example.com',
            'document' => '45596714003',
        ]);

        $billing = Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 79.90,
            'status' => 'pending',
            'due_date' => now()->toDateString(),
        ]);

        Http::fake([
            '*/customers' => function () {
                throw new ConnectionException(
                    'Falha de conexão com o Asaas.'
                );
            },
        ]);

        $response = $this->postJson(
            "/api/billing/{$billing->id}/pay",
            [
                'card_holder_name' => 'Carlos Silva',
                'card_number' => '4111111111111111',
                'expiry_date' => '12/30',
                'cvv' => '123',
            ]
        );

        $response->assertStatus(503)
            ->assertJson([
                'message' => 'Não foi possível conectar ao serviço de pagamento. Tente novamente.',
            ]);

        $this->assertDatabaseHas('billings', [
            'id' => $billing->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_credit_card_token_is_not_exposed_in_billing_response(): void
    {
        $plan = Plan::create([
            'name' => 'Profissional',
            'description' => 'Plano profissional',
            'price' => 79.90,
            'active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'João da Silva',
            'email' => 'joao-token@example.com',
            'document' => '45596714003',
        ]);

        $billing = Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 79.90,
            'status' => 'paid',
            'due_date' => now()->toDateString(),
        ]);

        $creditCard = CreditCard::create([
            'customer_id' => $customer->id,
            'card_holder_name' => 'João da Silva',
            'card_last_four' => '1234',
            'card_brand' => 'VISA',
            'card_token' => 'token_super_secreto',
            'card_token_hash' => CreditCard::tokenHash('token_super_secreto'),
        ]);

        Payment::create([
            'billing_id' => $billing->id,
            'credit_card_id' => $creditCard->id,
            'amount_paid' => 79.90,
            'status' => PaymentStatus::CONFIRMED,
            'paid_at' => now(),
        ]);

        $response = $this->getJson("/api/billing/{$billing->id}");

        $response->assertStatus(200)
            ->assertJsonMissing([
                'card_token' => 'token_super_secreto',
            ]);
    }

    public function test_paid_billing_cannot_be_paid_again(): void
    {
        $plan = Plan::create([
            'name' => 'Profissional',
            'description' => 'Plano profissional',
            'price' => 79.90,
            'active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'João da Silva',
            'email' => 'joao-pago@example.com',
            'document' => '45596714003',
        ]);

        $billing = Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 79.90,
            'status' => 'paid',
            'due_date' => now()->toDateString(),
        ]);

        Http::fake();

        $response = $this->postJson(
            "/api/billing/{$billing->id}/pay",
            [
                'card_holder_name' => 'João da Silva',
                'card_number' => '4111111111111111',
                'expiry_date' => '12/30',
                'cvv' => '123',
            ]
        );

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Esta cobrança não está disponível para pagamento.',
            ]);

        $this->assertDatabaseCount('payments', 0);

        Http::assertNothingSent();
    }

    public function test_payment_returns_404_when_billing_does_not_exist(): void
    {
        Http::fake();

        $response = $this->postJson(
            '/api/billing/999999/pay',
            [
                'card_holder_name' => 'João da Silva',
                'card_number' => '4111111111111111',
                'expiry_date' => '12/30',
                'cvv' => '123',
            ]
        );

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Cobrança não encontrada.',
            ]);

        $this->assertDatabaseCount('payments', 0);

        Http::assertNothingSent();
    }

    public function test_invalid_payment_data_is_rejected_before_calling_asaas(): void
    {
        Http::fake();

        $response = $this->postJson(
            '/api/billing/1/pay',
            [
                'card_holder_name' => 'A',
                'card_number' => '123',
                'expiry_date' => '13/30',
                'cvv' => '12',
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'card_holder_name',
                'card_number',
                'expiry_date',
                'cvv',
            ]);

        Http::assertNothingSent();
    }

    public function test_expired_card_is_rejected_before_calling_asaas(): void
    {
        Http::fake();

        $response = $this->postJson(
            '/api/billing/1/pay',
            [
                'card_holder_name' => 'João da Silva',
                'card_number' => '4111111111111111',
                'expiry_date' => now()->subMonth()->format('m/y'),
                'cvv' => '123',
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'expiry_date',
            ]);

        Http::assertNothingSent();
    }

    public function test_invalid_luhn_card_number_is_rejected_before_calling_asaas(): void
    {
        Http::fake();

        $response = $this->postJson(
            '/api/billing/1/pay',
            [
                'card_holder_name' => 'João da Silva',
                'card_number' => '4111111111111112',
                'expiry_date' => '12/30',
                'cvv' => '123',
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'card_number',
            ]);

        Http::assertNothingSent();
    }

    public function test_payment_returns_502_when_asaas_customer_response_is_invalid(): void
    {
        $plan = Plan::create([
            'name' => 'Profissional',
            'description' => 'Plano profissional',
            'price' => 79.90,
            'active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'João da Silva',
            'email' => 'joao-invalid-response@example.com',
            'document' => '45596714003',
        ]);

        $billing = Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 79.90,
            'status' => 'pending',
            'due_date' => now()->toDateString(),
        ]);

        Http::fake([
            '*/customers' => Http::response([], 200),
        ]);

        $response = $this->postJson(
            "/api/billing/{$billing->id}/pay",
            [
                'card_holder_name' => 'João da Silva',
                'card_number' => '4111111111111111',
                'expiry_date' => '12/30',
                'cvv' => '123',
            ]
        );

        $response->assertStatus(502)
            ->assertJson([
                'message' => 'Não foi possível processar o pagamento.',
            ]);

        $this->assertDatabaseHas('billings', [
            'id' => $billing->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_accepts_valid_cnpj(): void
    {
        $plan = Plan::create([
            'name' => 'Profissional',
            'description' => 'Plano profissional',
            'price' => 79.90,
            'active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Empresa Teste LTDA',
            'email' => 'empresa@example.com',
            'document' => '04252011000110',
        ]);

        $billing = Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 79.90,
            'status' => 'pending',
            'due_date' => now()->toDateString(),
        ]);

        Http::fake([
            '*/customers' => Http::response([
                'id' => 'cus_cnpj_test',
            ], 200),

            '*/payments' => Http::response([
                'id' => 'pay_cnpj_test',
                'status' => 'PENDING',
            ], 200),

            '*/payments/pay_cnpj_test/payWithCreditCard' => Http::response([
                'id' => 'pay_cnpj_test',
                'status' => 'CONFIRMED',
                'creditCard' => [
                    'creditCardBrand' => 'VISA',
                    'creditCardToken' => 'token_cnpj_test',
                ],
            ], 200),
        ]);

        $response = $this->postJson(
            "/api/billing/{$billing->id}/pay",
            [
                'card_holder_name' => 'Empresa Teste LTDA',
                'card_number' => '4111111111111111',
                'expiry_date' => '12/30',
                'cvv' => '123',
            ]
        );

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            return $request->url() === config('services.asaas.base_url').'/customers'
                && $request['cpfCnpj'] === '04252011000110';
        });
    }

    public function test_invalid_cpf_is_rejected_before_calling_asaas(): void
    {
        $plan = Plan::create([
            'name' => 'Profissional',
            'description' => 'Plano profissional',
            'price' => 79.90,
            'active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Cliente CPF Inválido',
            'email' => 'cpf-invalido@example.com',
            'document' => '45596714002',
        ]);

        $billing = Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 79.90,
            'status' => 'pending',
            'due_date' => now()->toDateString(),
        ]);

        Http::fake();

        $response = $this->postJson(
            "/api/billing/{$billing->id}/pay",
            [
                'card_holder_name' => 'Cliente CPF Inválido',
                'card_number' => '4111111111111111',
                'expiry_date' => '12/30',
                'cvv' => '123',
            ]
        );

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'O CPF/CNPJ do cliente é inválido.',
            ]);

        Http::assertNothingSent();

        $this->assertDatabaseHas('billings', [
            'id' => $billing->id,
            'status' => 'pending',
        ]);
    }

    public function test_invalid_cnpj_is_rejected_before_calling_asaas(): void
    {
        $plan = Plan::create([
            'name' => 'Profissional',
            'description' => 'Plano profissional',
            'price' => 79.90,
            'active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Empresa CNPJ Inválido',
            'email' => 'cnpj-invalido@example.com',
            'document' => '04252011000111',
        ]);

        $billing = Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 79.90,
            'status' => 'pending',
            'due_date' => now()->toDateString(),
        ]);

        Http::fake();

        $response = $this->postJson(
            "/api/billing/{$billing->id}/pay",
            [
                'card_holder_name' => 'Empresa CNPJ Inválido',
                'card_number' => '4111111111111111',
                'expiry_date' => '12/30',
                'cvv' => '123',
            ]
        );

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'O CPF/CNPJ do cliente é inválido.',
            ]);

        Http::assertNothingSent();

        $this->assertDatabaseHas('billings', [
            'id' => $billing->id,
            'status' => 'pending',
        ]);
    }

    public function test_payment_returns_503_when_asaas_times_out(): void
    {
        $plan = Plan::create([
            'name' => 'Profissional',
            'description' => 'Plano profissional',
            'price' => 79.90,
            'active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Carlos Silva',
            'email' => 'carlos-timeout@example.com',
            'document' => '45596714003',
        ]);

        $billing = Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 79.90,
            'status' => 'pending',
            'due_date' => now()->toDateString(),
        ]);

        Http::fake([
            '*/customers' => function () {
                throw new ConnectionException(
                    'Operation timed out while connecting to Asaas.'
                );
            },
        ]);

        $response = $this->postJson(
            "/api/billing/{$billing->id}/pay",
            [
                'card_holder_name' => 'Carlos Silva',
                'card_number' => '4111111111111111',
                'expiry_date' => '12/30',
                'cvv' => '123',
            ]
        );

        $response->assertStatus(503)
            ->assertJson([
                'message' => 'Não foi possível conectar ao serviço de pagamento. Tente novamente.',
            ]);

        $this->assertDatabaseHas('billings', [
            'id' => $billing->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_rolls_back_local_changes_when_payment_storage_fails(): void
    {
        $plan = Plan::create([
            'name' => 'Profissional',
            'description' => 'Plano profissional',
            'price' => 79.90,
            'active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'João da Silva',
            'email' => 'joao-rollback@example.com',
            'document' => '45596714003',
        ]);

        $billing = Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 79.90,
            'status' => 'pending',
            'due_date' => now()->toDateString(),
        ]);

        $existingToken = 'existing_token_rollback';

        $existingCreditCard = CreditCard::create([
            'customer_id' => $customer->id,
            'card_holder_name' => 'João da Silva',
            'card_last_four' => '1234',
            'card_brand' => 'VISA',
            'card_token' => $existingToken,
            'card_token_hash' => CreditCard::tokenHash($existingToken),
        ]);

        Payment::create([
            'billing_id' => $billing->id,
            'credit_card_id' => $existingCreditCard->id,
            'amount_paid' => 79.90,
            'status' => PaymentStatus::CONFIRMED,
            'paid_at' => now(),
        ]);

        $newToken = 'new_token_rollback';

        Http::fake([
            '*/customers' => Http::response([
                'id' => 'cus_rollback_test',
            ], 200),

            '*/payments' => Http::response([
                'id' => 'pay_rollback_test',
                'status' => 'PENDING',
            ], 200),

            '*/payments/pay_rollback_test/payWithCreditCard' => Http::response([
                'id' => 'pay_rollback_test',
                'status' => 'CONFIRMED',
                'creditCard' => [
                    'creditCardBrand' => 'VISA',
                    'creditCardToken' => $newToken,
                ],
            ], 200),
        ]);

        try {
            $response = $this->postJson(
                "/api/billing/{$billing->id}/pay",
                [
                    'card_holder_name' => 'João da Silva',
                    'card_number' => '4111111111111111',
                    'expiry_date' => '12/30',
                    'cvv' => '123',
                ]
            );

            $response->assertStatus(500);
        } catch (QueryException $e) {
            $this->assertTrue(
                str_contains(
                    strtolower($e->getMessage()),
                    'unique'
                )
            );
        }

        $this->assertDatabaseHas('billings', [
            'id' => $billing->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('payments', 1);

        $this->assertDatabaseCount('credit_cards', 1);

        $this->assertDatabaseMissing('credit_cards', [
            'card_token_hash' => CreditCard::tokenHash($newToken),
        ]);

        $this->assertDatabaseHas('credit_cards', [
            'id' => $existingCreditCard->id,
            'card_token_hash' => CreditCard::tokenHash($existingToken),
        ]);
    }

    public function test_billing_cannot_have_two_payments(): void
    {
        $plan = Plan::create([
            'name' => 'Profissional',
            'description' => 'Plano profissional',
            'price' => 79.90,
            'active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'João da Silva',
            'email' => 'joao-unique@example.com',
            'document' => '45596714003',
        ]);

        $billing = Billing::create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 79.90,
            'status' => 'paid',
            'due_date' => now()->toDateString(),
        ]);

        $creditCard = CreditCard::create([
            'customer_id' => $customer->id,
            'card_holder_name' => 'João da Silva',
            'card_last_four' => '1234',
            'card_brand' => 'VISA',
            'card_token' => 'unique_test_token',
            'card_token_hash' => CreditCard::tokenHash('unique_test_token'),
        ]);

        Payment::create([
            'billing_id' => $billing->id,
            'credit_card_id' => $creditCard->id,
            'amount_paid' => 79.90,
            'status' => PaymentStatus::CONFIRMED,
            'paid_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        Payment::create([
            'billing_id' => $billing->id,
            'credit_card_id' => $creditCard->id,
            'amount_paid' => 79.90,
            'status' => PaymentStatus::CONFIRMED,
            'paid_at' => now(),
        ]);
    }
}
