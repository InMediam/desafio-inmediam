<?php

namespace Tests\Unit\Services\Asaas;

use App\Services\Asaas\AsaasGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AsaasGatewayTest extends TestCase
{
    public function test_a_response_missing_the_card_brand_does_not_break_and_becomes_null(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'CONFIRMED',
                'creditCard' => [
                    'creditCardNumber' => '1234',
                    'creditCardToken' => 'tok_000000000001',
                ],
            ], 200),
        ]);

        $gateway = new AsaasGateway('gateway-key', 'https://gateway.test/v3');

        $result = $gateway->payWithCreditCard(
            'pay_000000000001',
            [
                'holderName' => 'JOAO DA SILVA',
                'number' => '4539656048392220',
                'expiryMonth' => '01',
                'expiryYear' => '2030',
                'cvv' => '123',
            ],
            [
                'name' => 'Joao da Silva',
                'email' => 'joao@example.com',
                'document' => '12345678901',
                'phone' => null,
                'postalCode' => null,
                'addressNumber' => null,
            ],
        );

        $this->assertSame('1234', $result['cardLastFour']);
        $this->assertNull($result['cardBrand']);
        $this->assertSame('tok_000000000001', $result['cardToken']);
    }
}
