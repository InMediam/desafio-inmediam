<?php

namespace App\Services\Billing;

final class CreditCardData
{
    public function __construct(
        public readonly string $holderName,
        public readonly string $number,
        public readonly string $expiryMonth,
        public readonly string $expiryYear,
        public readonly string $cvv,
    ) {}
}
