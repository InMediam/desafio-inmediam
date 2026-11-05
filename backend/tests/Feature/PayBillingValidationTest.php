<?php

namespace Tests\Feature;

use App\Models\Billing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesAsaas;
use Tests\TestCase;

class PayBillingValidationTest extends TestCase
{
    use FakesAsaas;
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'card_number' => self::APPROVED_TEST_CARD_NUMBER,
            'card_holder_name' => 'JOAO DA SILVA',
            'expiry_date' => $this->validExpiryDate(),
            'cvv' => '123',
        ], $overrides);
    }

    public function test_it_requires_all_fields(): void
    {
        $billing = Billing::factory()->pending()->create();

        $response = $this->postJson("/api/billing/{$billing->id}/pay", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['card_number', 'card_holder_name', 'expiry_date', 'cvv']);
    }

    public function test_card_number_must_have_13_to_19_digits_after_removing_spaces(): void
    {
        $billing = Billing::factory()->pending()->create();

        $tooShort = $this->postJson("/api/billing/{$billing->id}/pay", $this->validPayload([
            'card_number' => '123456789012',
        ]));
        $tooShort->assertStatus(422);
        $tooShort->assertJsonValidationErrors(['card_number']);

        $tooLong = $this->postJson("/api/billing/{$billing->id}/pay", $this->validPayload([
            'card_number' => '12345678901234567890',
        ]));
        $tooLong->assertStatus(422);
        $tooLong->assertJsonValidationErrors(['card_number']);

        $notDigits = $this->postJson("/api/billing/{$billing->id}/pay", $this->validPayload([
            'card_number' => '453965604839abcd',
        ]));
        $notDigits->assertStatus(422);
        $notDigits->assertJsonValidationErrors(['card_number']);
    }

    public function test_card_number_accepts_spaces_and_strips_them_before_validating(): void
    {
        $this->fakeAsaas();

        $billing = Billing::factory()->pending()->create();

        $response = $this->postJson("/api/billing/{$billing->id}/pay", $this->validPayload([
            'card_number' => '4539 6560 4839 2220',
        ]));

        $response->assertOk();
    }

    public function test_card_number_must_pass_the_luhn_check(): void
    {
        $billing = Billing::factory()->pending()->create();

        $response = $this->postJson("/api/billing/{$billing->id}/pay", $this->validPayload([
            'card_number' => '4539656048392221',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['card_number']);
    }

    public function test_card_holder_name_is_required(): void
    {
        $billing = Billing::factory()->pending()->create();

        $response = $this->postJson("/api/billing/{$billing->id}/pay", $this->validPayload([
            'card_holder_name' => '',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['card_holder_name']);
    }

    public function test_expiry_date_must_be_mm_aa_with_month_between_01_and_12(): void
    {
        $billing = Billing::factory()->pending()->create();

        $invalidMonth = $this->postJson("/api/billing/{$billing->id}/pay", $this->validPayload([
            'expiry_date' => '13/28',
        ]));
        $invalidMonth->assertStatus(422);
        $invalidMonth->assertJsonValidationErrors(['expiry_date']);

        $wrongFormat = $this->postJson("/api/billing/{$billing->id}/pay", $this->validPayload([
            'expiry_date' => '1/28',
        ]));
        $wrongFormat->assertStatus(422);
        $wrongFormat->assertJsonValidationErrors(['expiry_date']);
    }

    public function test_expiry_date_must_not_be_in_the_past(): void
    {
        $billing = Billing::factory()->pending()->create();

        $response = $this->postJson("/api/billing/{$billing->id}/pay", $this->validPayload([
            'expiry_date' => now()->subYears(2)->format('m/y'),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['expiry_date']);
    }

    public function test_cvv_must_have_3_or_4_digits(): void
    {
        $billing = Billing::factory()->pending()->create();

        $tooShort = $this->postJson("/api/billing/{$billing->id}/pay", $this->validPayload([
            'cvv' => '12',
        ]));
        $tooShort->assertStatus(422);
        $tooShort->assertJsonValidationErrors(['cvv']);

        $tooLong = $this->postJson("/api/billing/{$billing->id}/pay", $this->validPayload([
            'cvv' => '12345',
        ]));
        $tooLong->assertStatus(422);
        $tooLong->assertJsonValidationErrors(['cvv']);

        $notDigits = $this->postJson("/api/billing/{$billing->id}/pay", $this->validPayload([
            'cvv' => 'abc',
        ]));
        $notDigits->assertStatus(422);
        $notDigits->assertJsonValidationErrors(['cvv']);
    }
}
