<?php

namespace Tests\Unit\Services;

use App\Services\AsaasService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AsaasServiceTest extends TestCase
{
    private AsaasService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['asaas.apiKey' => 'test_api_key']);
        config(['asaas.baseUrl' => 'https://sandbox.asaas.com/api/v3']);

        $this->service = app(AsaasService::class);
    }

    public function test_create_customer_success(): void
    {
        Http::fake([
            'sandbox.asaas.com/api/v3/customers' => Http::response([
                'id' => 'cus_123',
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ], 200),
        ]);

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'cpfCnpj' => '12345678901',
            'notificationDisabled' => true,
        ];

        $result = $this->service->createCustomer($data);

        $this->assertEquals('cus_123', $result->id);
        $this->assertEquals('John Doe', $result->name);
    }

    public function test_create_charge_success(): void
    {
        Http::fake([
            'sandbox.asaas.com/api/v3/payments' => Http::response([
                'id' => 'pay_123',
                'status' => 'PENDING',
            ], 200),
        ]);

        $data = [
            'customer' => 'cus_123',
            'billingType' => 'CREDIT_CARD',
            'value' => 100.00,
            'dueDate' => '2026-12-31',
        ];

        $result = $this->service->createCharge($data);

        $this->assertEquals('pay_123', $result->id);
    }

    public function test_pay_with_credit_card_success(): void
    {
        Http::fake([
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

        $creditCard = [
            'holderName' => 'John Doe',
            'number' => '4111111111111111',
            'expiryMonth' => '12',
            'expiryYear' => '2028',
            'ccv' => '123',
        ];

        $holderInfo = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'cpfCnpj' => '12345678901',
            'phone' => '11999999999',
            'postalCode' => '01001000',
            'addressNumber' => '100',
        ];

        $result = $this->service->payWithCreditCard('pay_123', $creditCard, $holderInfo);

        $this->assertEquals('CONFIRMED', $result->status);
        $this->assertEquals('1234', $result->creditCard['creditCardNumber']);
    }

    public function test_create_customer_fails(): void
    {
        Http::fake([
            'sandbox.asaas.com/api/v3/customers' => Http::response([
                'errors' => ['name' => 'Invalid name'],
            ], 400),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);

        $this->service->createCustomer([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'cpfCnpj' => '12345678901',
            'notificationDisabled' => true,
        ]);
    }

    public function test_create_customer_validation_fails(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createCustomer([
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'cpfCnpj' => '12345678901',
            'notificationDisabled' => true,
        ]);
    }

    public function test_create_charge_fails(): void
    {
        Http::fake([
            'sandbox.asaas.com/api/v3/payments' => Http::response([
                'errors' => ['value' => 'Invalid value'],
            ], 500),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(500);

        $this->service->createCharge([
            'customer' => 'cus_123',
            'billingType' => 'CREDIT_CARD',
            'value' => -10,
            'dueDate' => '2026-12-31',
        ]);
    }

    public function test_create_charge_validation_fails(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createCharge([
            'customer' => '',
            'billingType' => '',
            'value' => 'not-numeric',
            'dueDate' => '',
        ]);
    }

    public function test_pay_with_credit_card_fails(): void
    {
        Http::fake([
            'sandbox.asaas.com/api/v3/payments/*/payWithCreditCard' => Http::response([
                'errors' => ['creditCard' => 'Invalid card'],
            ], 402),
        ]);

        $this->expectException(Exception::class);

        $this->service->payWithCreditCard('pay_123', [
            'holderName' => 'John Doe',
            'number' => '4111111111111111',
            'expiryMonth' => '12',
            'expiryYear' => '2028',
            'ccv' => '123',
        ], [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'cpfCnpj' => '12345678901',
            'phone' => '11999999999',
            'postalCode' => '01001000',
            'addressNumber' => '100',
        ]);
    }

    public function test_pay_with_credit_card_credit_card_validation_fails(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->payWithCreditCard('pay_123', [], [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'cpfCnpj' => '12345678901',
            'phone' => '11999999999',
            'postalCode' => '01001000',
            'addressNumber' => '100',
        ]);
    }

    public function test_pay_with_credit_card_holder_info_validation_fails(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->payWithCreditCard('pay_123', [
            'holderName' => 'John Doe',
            'number' => '4111111111111111',
            'expiryMonth' => '12',
            'expiryYear' => '2028',
            'ccv' => '123',
        ], []);
    }
}
