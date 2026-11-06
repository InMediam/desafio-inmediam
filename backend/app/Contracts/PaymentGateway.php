<?php

namespace App\Contracts;

interface PaymentGateway
{
    public function createCustomer(string $name, string $email, string $document): array;

    public function createCharge(string $customerId, float $amount, string $dueDate): array;

    public function payWithCreditCard(string $chargeId, array $card, array $holderInfo): array;
}
