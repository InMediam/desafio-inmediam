<?php

namespace Tests\Feature;

use App\Models\Billing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesAsaas;
use Tests\TestCase;

class PayBillingTest extends TestCase
{
    use FakesAsaas;
    use RefreshDatabase;

    public function test_it_charges_the_billing_amount_and_ignores_the_payload_amount(): void
    {
        $this->fakeAsaas();

        $billing = Billing::factory()->pending()->create(['amount' => 150.00]);

        $response = $this->postJson("/api/billing/{$billing->id}/pay", [
            'amount' => 0.01,
            'card_number' => self::APPROVED_TEST_CARD_NUMBER,
            'card_holder_name' => 'JOAO DA SILVA',
            'expiry_date' => $this->validExpiryDate(),
            'cvv' => '123',
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/payments')
                && (float) $request['value'] === 150.00;
        });

        $this->assertDatabaseHas('payments', [
            'billing_id' => $billing->id,
            'amount_paid' => 150.00,
        ]);
    }

    public function test_it_returns_404_and_calls_asaas_nothing_when_billing_does_not_exist(): void
    {
        $response = $this->postJson('/api/billing/999999/pay', [
            'card_number' => self::APPROVED_TEST_CARD_NUMBER,
            'card_holder_name' => 'JOAO DA SILVA',
            'expiry_date' => $this->validExpiryDate(),
            'cvv' => '123',
        ]);

        $response->assertStatus(404);

        Http::assertNothingSent();
    }

    public function test_it_returns_404_for_a_non_numeric_billing_id(): void
    {
        $response = $this->postJson('/api/billing/not-a-valid-id/pay', [
            'card_number' => self::APPROVED_TEST_CARD_NUMBER,
            'card_holder_name' => 'JOAO DA SILVA',
            'expiry_date' => $this->validExpiryDate(),
            'cvv' => '123',
        ]);

        $response->assertStatus(404);

        Http::assertNothingSent();
    }

    public function test_it_returns_409_and_calls_asaas_nothing_when_billing_is_already_paid(): void
    {
        $billing = Billing::factory()->paid()->create();

        $response = $this->postJson("/api/billing/{$billing->id}/pay", [
            'card_number' => self::APPROVED_TEST_CARD_NUMBER,
            'card_holder_name' => 'JOAO DA SILVA',
            'expiry_date' => $this->validExpiryDate(),
            'cvv' => '123',
        ]);

        $response->assertStatus(409);

        Http::assertNothingSent();

        // The already-paid billing comes with its own payment from the factory;
        // the point is that the request above did not create a second one.
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_it_returns_422_with_a_friendly_message_when_the_card_is_declined(): void
    {
        $this->fakeAsaasCreditCardDeclined();

        $billing = Billing::factory()->pending()->create();

        $response = $this->postJson("/api/billing/{$billing->id}/pay", [
            'card_number' => self::APPROVED_TEST_CARD_NUMBER,
            'card_holder_name' => 'JOAO DA SILVA',
            'expiry_date' => $this->validExpiryDate(),
            'cvv' => '123',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'Pagamento recusado. Verifique os dados do cartão e tente novamente.',
        ]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('credit_cards', 0);
        $this->assertSame('pending', $billing->refresh()->status);
    }

    public function test_it_returns_502_with_a_generic_message_on_asaas_server_error(): void
    {
        $this->fakeAsaasServerError();

        $billing = Billing::factory()->pending()->create();

        $response = $this->postJson("/api/billing/{$billing->id}/pay", [
            'card_number' => self::APPROVED_TEST_CARD_NUMBER,
            'card_holder_name' => 'JOAO DA SILVA',
            'expiry_date' => $this->validExpiryDate(),
            'cvv' => '123',
        ]);

        $response->assertStatus(502);
        $response->assertJson([
            'error' => 'Não foi possível processar o pagamento. Tente novamente mais tarde.',
        ]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('credit_cards', 0);
        $this->assertSame('pending', $billing->refresh()->status);
    }

    public function test_it_returns_502_with_a_generic_message_on_asaas_timeout(): void
    {
        $this->fakeAsaasTimeout();

        $billing = Billing::factory()->pending()->create();

        $response = $this->postJson("/api/billing/{$billing->id}/pay", [
            'card_number' => self::APPROVED_TEST_CARD_NUMBER,
            'card_holder_name' => 'JOAO DA SILVA',
            'expiry_date' => $this->validExpiryDate(),
            'cvv' => '123',
        ]);

        $response->assertStatus(502);
        $response->assertJson([
            'error' => 'Não foi possível processar o pagamento. Tente novamente mais tarde.',
        ]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('credit_cards', 0);
        $this->assertSame('pending', $billing->refresh()->status);
    }

    public function test_it_only_marks_the_billing_as_paid_when_asaas_confirms_or_receives_it(): void
    {
        $this->fakeAsaas([
            '*/payments/*/payWithCreditCard' => Http::response([
                'status' => 'PENDING',
                'creditCard' => [
                    'creditCardNumber' => '1234',
                    'creditCardBrand' => 'VISA',
                    'creditCardToken' => 'tok_000000000001',
                ],
            ], 200),
        ]);

        $billing = Billing::factory()->pending()->create();

        $response = $this->postJson("/api/billing/{$billing->id}/pay", [
            'card_number' => self::APPROVED_TEST_CARD_NUMBER,
            'card_holder_name' => 'JOAO DA SILVA',
            'expiry_date' => $this->validExpiryDate(),
            'cvv' => '123',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payments', [
            'billing_id' => $billing->id,
            'status' => 'PENDING',
        ]);

        $this->assertSame('pending', $billing->refresh()->status);
    }

    public function test_it_does_not_save_partial_data_when_something_fails_mid_write(): void
    {
        $billing = Billing::factory()->pending()->create();

        $this->fakeAsaas([
            '*/payments/*/payWithCreditCard' => function () use ($billing) {
                // Simulates the billing being deleted concurrently between the
                // charge being created and the credit card/payment being saved,
                // so the payment insert violates the billings foreign key.
                $billing->delete();

                return Http::response([
                    'status' => 'CONFIRMED',
                    'creditCard' => [
                        'creditCardNumber' => '1234',
                        'creditCardBrand' => 'VISA',
                        'creditCardToken' => 'tok_000000000001',
                    ],
                ], 200);
            },
        ]);

        $this->postJson("/api/billing/{$billing->id}/pay", [
            'card_number' => self::APPROVED_TEST_CARD_NUMBER,
            'card_holder_name' => 'JOAO DA SILVA',
            'expiry_date' => $this->validExpiryDate(),
            'cvv' => '123',
        ]);

        $this->assertDatabaseCount('credit_cards', 0);
        $this->assertDatabaseCount('payments', 0);
    }
}
