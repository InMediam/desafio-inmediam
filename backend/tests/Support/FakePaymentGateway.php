<?php

namespace Tests\Support;

use App\Contracts\PaymentGateway;

class FakePaymentGateway implements PaymentGateway
{
    public int $payWithCreditCardCalls = 0;

    public function __construct(
        private array $customer = ['id' => 'cus_000000000001'],
        private array $charge = ['id' => 'pay_000000000001'],
        private array $chargeResult = [
            'httpStatus' => 200,
            'status' => 'CONFIRMED',
            'cardLastFour' => '1234',
            'cardBrand' => 'VISA',
            'cardToken' => 'tok_000000000001',
            'errorCode' => null,
            'errorDescription' => null,
        ],
    ) {}

    public function createCustomer(string $name, string $email, string $document): array
    {
        return $this->customer;
    }

    public function createCharge(string $customerId, float $amount, string $dueDate): array
    {
        return $this->charge;
    }

    public function payWithCreditCard(string $chargeId, array $card, array $holderInfo): array
    {
        $this->payWithCreditCardCalls++;

        return $this->chargeResult;
    }
}
