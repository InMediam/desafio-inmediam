<?php

namespace Tests\Unit\Services;

use App\Models\CreditCard;
use App\Models\Customer;
use App\Services\CreditCardService;
use Tests\TestCase;

class CreditCardServiceTest extends TestCase
{
    private CreditCardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CreditCardService::class);
    }

    public function test_create_credit_card(): void
    {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'card_holder_name' => 'John Doe',
            'card_last_four' => '1234',
            'card_brand' => 'Visa',
            'card_token' => 'tok_' . uniqid(),
        ];

        $creditCard = $this->service->create($data);

        $this->assertInstanceOf(CreditCard::class, $creditCard);
        $this->assertEquals('John Doe', $creditCard->card_holder_name);
        $this->assertEquals('Visa', $creditCard->card_brand);
    }

    public function test_find_credit_card(): void
    {
        $customer = Customer::factory()->create();
        $creditCard = CreditCard::factory()->create(['customer_id' => $customer->id]);

        $found = $this->service->find($creditCard->id);

        $this->assertInstanceOf(CreditCard::class, $found);
        $this->assertEquals($creditCard->id, $found->id);
    }

    public function test_all_credit_cards(): void
    {
        $customer = Customer::factory()->create();
        CreditCard::factory()->count(3)->create(['customer_id' => $customer->id]);

        $cards = $this->service->all();

        $this->assertCount(3, $cards);
    }

    public function test_update_credit_card(): void
    {
        $customer = Customer::factory()->create();
        $creditCard = CreditCard::factory()->create(['customer_id' => $customer->id]);

        $updated = $this->service->update($creditCard->id, ['card_brand' => 'Mastercard']);

        $this->assertInstanceOf(CreditCard::class, $updated);
        $this->assertEquals('Mastercard', $updated->card_brand);
    }

    public function test_delete_credit_card(): void
    {
        $customer = Customer::factory()->create();
        $creditCard = CreditCard::factory()->create(['customer_id' => $customer->id]);

        $result = $this->service->delete($creditCard->id);

        $this->assertTrue($result);
        $this->assertNull(CreditCard::find($creditCard->id));
    }
}
