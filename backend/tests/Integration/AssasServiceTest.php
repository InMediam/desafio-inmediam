<?php

namespace Tests\Integration;

use App\Enums\BillingType;
use App\Services\AsaasService;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('integration')]
class AssasServiceTest extends TestCase
{
    private AsaasService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AsaasService::class);
    }

    public function test_create_customer_in_sandbox(): void
    {
        $result = $this->service->createCustomer([
            'name' => 'Sandbox Test',
            'email' => 'sandbox_' . uniqid() . '@test.com',
            'cpfCnpj' => '24971563792',
            'notificationDisabled' => true,
        ]);

        $this->assertObjectHasProperty('id', $result);
        $this->assertNotEmpty($result->id);
        $this->assertEquals('Sandbox Test', $result->name);
    }

    public function test_create_charge_in_sandbox(): void
    {
        $customer = $this->service->createCustomer([
            'name' => 'Charge Test',
            'email' => 'charge_' . uniqid() . '@test.com',
            'cpfCnpj' => '24971563792',
            'notificationDisabled' => true,
        ]);

        $result = $this->service->createCharge([
            'customer' => $customer->id,
            'billingType' => BillingType::CREDIT_CARD->value,
            'value' => 100.00,
            'dueDate' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $this->assertObjectHasProperty('id', $result);
        $this->assertEquals('PENDING', $result->status);
    }

    public function test_pay_with_credit_card_in_sandbox(): void
    {
        $customer = $this->service->createCustomer([
            'name' => 'Pay Test',
            'email' => 'pay_' . uniqid() . '@test.com',
            'cpfCnpj' => '24971563792',
            'notificationDisabled' => true,
        ]);

        $charge = $this->service->createCharge([
            'customer' => $customer->id,
            'billingType' => BillingType::CREDIT_CARD->value,
            'value' => 50.00,
            'dueDate' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $result = $this->service->payWithCreditCard($charge->id, [
            'holderName' => 'Test Holder',
            'number' => '5162306219378829',
            'expiryMonth' => '12',
            'expiryYear' => '2030',
            'ccv' => '318',
        ], [
            'name' => 'Test Holder',
            'email' => 'holder@test.com',
            'cpfCnpj' => '24971563792',
            'phone' => '11999999999',
            'postalCode' => '01001000',
            'addressNumber' => '100',
        ]);

        $this->assertObjectHasProperty('status', $result);
        $this->assertContains($result->status, ['CONFIRMED']);
    }
}
