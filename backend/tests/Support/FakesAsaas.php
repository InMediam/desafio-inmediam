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
        Http::fake(array_merge([
            // Registered before "*/payments" so it isn't swallowed by it.
            '*/payments/*/payWithCreditCard' => Http::response([
                'status' => 'CONFIRMED',
                'creditCard' => [
                    'creditCardNumber' => '1234',
                    'creditCardBrand' => 'VISA',
                    'creditCardToken' => 'tok_000000000001',
                ],
            ], 200),
            '*/customers' => Http::response([
                'id' => 'cus_000000000001',
            ], 200),
            '*/payments' => Http::response([
                'id' => 'pay_000000000001',
            ], 200),
        ], $overrides));
    }

    protected function fakeAsaasCreditCardDeclined(): void
    {
        $this->fakeAsaas([
            '*/payments/*/payWithCreditCard' => Http::response([
                'errors' => [
                    ['code' => 'invalid_creditCard', 'description' => 'Cartão recusado pela operadora.'],
                ],
            ], 400),
        ]);
    }

    protected function fakeAsaasServerError(): void
    {
        $this->fakeAsaas([
            '*/payments/*/payWithCreditCard' => Http::response([], 500),
        ]);
    }

    protected function fakeAsaasTimeout(): void
    {
        $this->fakeAsaas([
            '*/payments/*/payWithCreditCard' => function () {
                throw new ConnectionException('Connection timed out.');
            },
        ]);
    }

    /**
     * A credit card expiry date that is always valid, in the MM/AA format
     * the API expects.
     */
    protected function validExpiryDate(): string
    {
        return now()->addYears(2)->format('m/y');
    }
}
