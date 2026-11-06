<?php

namespace App\Services\Asaas;

use App\Contracts\PaymentGateway;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AsaasGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly int $timeout = 10,
    ) {}

    public function createCustomer(string $name, string $email, string $document): array
    {
        $response = $this->post('/customers', [
            'name' => $name,
            'email' => $email,
            'cpfCnpj' => $document,
            'notificationDisabled' => true, // ATENÇÃO: Não recomendo modificar, pois o Asaas envia notificações mesmo no ambiente de sandbox.
        ]);

        return ['id' => $response->json('id') ?? null];
    }

    public function createCharge(string $customerId, float $amount, string $dueDate): array
    {
        $response = $this->post('/payments', [
            'customer' => $customerId,
            'billingType' => 'CREDIT_CARD',
            'value' => $amount,
            'dueDate' => $dueDate,
        ]);

        return ['id' => $response->json('id') ?? null];
    }

    public function payWithCreditCard(string $chargeId, array $card, array $holderInfo): array
    {
        $response = $this->post("/payments/{$chargeId}/payWithCreditCard", [
            'creditCard' => [
                'holderName' => $card['holderName'],
                'number' => $card['number'],
                'expiryMonth' => $card['expiryMonth'],
                'expiryYear' => $card['expiryYear'],
                'ccv' => $card['cvv'],
            ],
            'creditCardHolderInfo' => [
                'name' => $holderInfo['name'],
                'email' => $holderInfo['email'],
                'cpfCnpj' => $holderInfo['document'],
                'phone' => $holderInfo['phone'],
                'postalCode' => $holderInfo['postalCode'],
                'addressNumber' => $holderInfo['addressNumber'],
            ],
        ]);

        return [
            'httpStatus' => $response->status(),
            'status' => $response->json('status') ?? null,
            'cardLastFour' => $response->json('creditCard.creditCardNumber') ?? null,
            'cardBrand' => $response->json('creditCard.creditCardBrand') ?? null,
            'cardToken' => $response->json('creditCard.creditCardToken') ?? null,
            'errorCode' => $response->json('errors.0.code') ?? null,
            'errorDescription' => $response->json('errors.0.description') ?? null,
        ];
    }

    private function post(string $path, array $payload): Response
    {
        return Http::timeout($this->timeout)
            ->withHeaders(['access_token' => $this->apiKey])
            ->post("{$this->baseUrl}{$path}", $payload);
    }
}
