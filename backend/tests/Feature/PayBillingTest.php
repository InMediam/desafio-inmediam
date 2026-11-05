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

        $this->assertDatabaseCount('payments', 0);
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
}
