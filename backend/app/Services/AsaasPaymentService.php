<?php

namespace App\Services;

use App\Models\Billing;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AsaasPaymentService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.asaas.key');
        $this->baseUrl = rtrim((string) config('services.asaas.base_url'), '/');
    }

    public function payWithCreditCard(Billing $billing, array $cardData): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('Chave da API Asaas não configurada.');
        }

        $customer = $this->createCustomer($billing);
        $charge = $this->createCharge($billing, $customer['id']);

        $response = $this->client()->post("/payments/{$charge['id']}/payWithCreditCard", [
            'creditCard' => [
                'holderName' => $cardData['card_holder_name'],
                'number' => $cardData['card_number'],
                'expiryMonth' => $cardData['expiry_month'],
                'expiryYear' => $cardData['expiry_year'],
                'ccv' => $cardData['cvv'],
            ],
            'creditCardHolderInfo' => [
                'name' => $billing->customer->name,
                'email' => $billing->customer->email,
                'cpfCnpj' => $billing->customer->document,
                'phone' => config('services.asaas.default_holder_phone'),
                'postalCode' => config('services.asaas.default_holder_postal_code'),
                'addressNumber' => config('services.asaas.default_holder_address_number'),
            ],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException($this->extractErrorMessage($response->json()) ?: 'Falha ao processar pagamento no Asaas.');
        }

        return $response->json();
    }

    private function createCustomer(Billing $billing): array
    {
        $response = $this->client()->post('/customers', [
            'name' => $billing->customer->name,
            'email' => $billing->customer->email,
            'cpfCnpj' => $billing->customer->document,
            'notificationDisabled' => true,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException($this->extractErrorMessage($response->json()) ?: 'Falha ao criar cliente no Asaas.');
        }

        return $response->json();
    }

    private function createCharge(Billing $billing, string $asaasCustomerId): array
    {
        $response = $this->client()->post('/payments', [
            'customer' => $asaasCustomerId,
            'billingType' => 'CREDIT_CARD',
            'value' => (float) $billing->amount,
            'dueDate' => $billing->due_date,
            'description' => "Assinatura {$billing->plan->name}",
        ]);

        if (!$response->successful()) {
            throw new RuntimeException($this->extractErrorMessage($response->json()) ?: 'Falha ao criar cobrança no Asaas.');
        }

        return $response->json();
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['access_token' => $this->apiKey])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.asaas.timeout', 15))
            ->retry(2, 200, throw: false);
    }

    private function extractErrorMessage(?array $payload): ?string
    {
        $firstError = Arr::first($payload['errors'] ?? []);

        return $firstError['description'] ?? $payload['message'] ?? null;
    }
}
