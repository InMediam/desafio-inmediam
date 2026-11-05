<?php

namespace Tests\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

trait FakesAsaas
{
    /**
     * Official Asaas sandbox test card for a simulated approval.
     * Passes Luhn and is the number documented by Asaas for this purpose.
     *
     * @see https://docs.asaas.com/docs/testando-pagamento-com-cartao-de-credito
     */
    public const APPROVED_TEST_CARD_NUMBER = '4539656048392220';

    /**
     * Fakes the full Asaas call chain (create customer, create charge,
     * pay with credit card), defaulting to a successful payment.
     * Pass $overrides to replace any of the three endpoints.
     */
    protected function fakeAsaas(array $overrides = []): void
    {
        Http::preventStrayRequests();

        Http::fake(array_merge([
            'sandbox.asaas.com/api/v3/customers' => Http::response([
                'id' => 'cus_000000000001',
            ], 200),
            'sandbox.asaas.com/api/v3/payments' => Http::response([
                'id' => 'pay_000000000001',
            ], 200),
            'sandbox.asaas.com/api/v3/payments/*/payWithCreditCard' => Http::response([
                'status' => 'CONFIRMED',
                'creditCard' => [
                    'creditCardNumber' => '1234',
                    'creditCardBrand' => 'VISA',
                    'creditCardToken' => 'tok_000000000001',
                ],
            ], 200),
        ], $overrides));
    }

    protected function fakeAsaasCreditCardDeclined(): void
    {
        $this->fakeAsaas([
            'sandbox.asaas.com/api/v3/payments/*/payWithCreditCard' => Http::response([
                'errors' => [
                    ['code' => 'invalid_creditCard', 'description' => 'Cartão recusado pela operadora.'],
                ],
            ], 400),
        ]);
    }

    protected function fakeAsaasServerError(): void
    {
        $this->fakeAsaas([
            'sandbox.asaas.com/api/v3/payments/*/payWithCreditCard' => Http::response([], 500),
        ]);
    }

    protected function fakeAsaasTimeout(): void
    {
        $this->fakeAsaas([
            'sandbox.asaas.com/api/v3/payments/*/payWithCreditCard' => function () {
                throw new ConnectionException('Connection timed out.');
            },
        ]);
    }
}
