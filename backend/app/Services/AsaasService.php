<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AsaasService
{
    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.asaas.base_url'))
            ->withHeaders([
                'access_token' => config('services.asaas.key'),
            ])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(60);
    }

    public function createCustomer(string $name, string $email, string $document): array
    {
        return $this->client()
            ->post('/customers', [
                'name' => $name,
                'email' => $email,
                'cpfCnpj' => $document,
                'notificationDisabled' => true,
            ])
            ->throw()
            ->json();
    }

    public function createCharge(string $customerId, string $amount, string $dueDate): array
    {
        return $this->client()
            ->post('/payments', [
                'customer' => $customerId,
                'billingType' => 'CREDIT_CARD',
                'value' => $amount,
                'dueDate' => $dueDate,
            ])
            ->throw()
            ->json();
    }

    public function payWithCreditCard(string $paymentId, array $card, array $holderInfo): array
    {
        return $this->client()
            ->post("/payments/{$paymentId}/payWithCreditCard", [
                'creditCard' => $card,
                'creditCardHolderInfo' => $holderInfo,
            ])
            ->throw()
            ->json();
    }
}
