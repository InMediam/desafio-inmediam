<?php

namespace App\Services;

use App\Models\Billing;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AsaasService
{
    public function collectCreditCardPayment(Billing $billing, array $paymentData): array
    {
        $customerId = $this->createCustomer($billing);
        $paymentId = $this->createPayment($billing, $customerId);

        return $this->payWithCreditCard($billing, $paymentId, $paymentData);
    }

    private function createCustomer(Billing $billing): string
    {
        $response = $this->client()->post('/customers', [
            'name' => $billing->customer->name,
            'email' => $billing->customer->email,
            'cpfCnpj' => $billing->customer->document,
            'notificationDisabled' => true,
        ]);

        $payload = $this->jsonOrFail($response, 'criar o cliente');
        return $this->requiredString($payload, 'id', 'O Asaas não retornou o identificador do cliente.');
    }

    private function createPayment(Billing $billing, string $customerId): string
    {
        $response = $this->client()->post('/payments', [
            'customer' => $customerId,
            'billingType' => 'CREDIT_CARD',
            'value' => $billing->amount,
            'dueDate' => $billing->due_date->format('Y-m-d'),
        ]);

        $payload = $this->jsonOrFail($response, 'criar a cobrança');
        return $this->requiredString($payload, 'id', 'O Asaas não retornou o identificador da cobrança.');
    }

    private function payWithCreditCard(Billing $billing, string $paymentId, array $paymentData): array
    {
        $response = $this->client()->post("/payments/{$paymentId}/payWithCreditCard", [
            'creditCard' => [
                'holderName' => $paymentData['card_holder_name'],
                'number' => $paymentData['card_number'],
                'expiryMonth' => explode('/', $paymentData['expiry_date'])[0],
                'expiryYear' => '20'.explode('/', $paymentData['expiry_date'])[1],
                'ccv' => $paymentData['cvv'],
            ],
            'creditCardHolderInfo' => [
                'name' => $billing->customer->name,
                'email' => $billing->customer->email,
                'cpfCnpj' => $billing->customer->document,
                'phone' => '0000000000',
                'postalCode' => '00000000',
                'addressNumber' => '0',
            ],
        ]);

        $payload = $this->jsonOrFail($response, 'aprovar o pagamento');

        return [
            'status' => strtoupper($this->requiredString($payload, 'status', 'O Asaas não retornou o status do pagamento.')),
            'card_brand' => $this->stringOrDefault(data_get($payload, 'creditCard.creditCardBrand'), 'UNKNOWN'),
            'card_token' => $this->requiredString($payload, 'creditCard.creditCardToken', 'O Asaas não retornou o token do cartão.'),
        ];
    }

    private function client(): PendingRequest
    {
        $apiKey = config('services.asaas.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('A chave de API do Asaas não foi configurada.');
        }

        return Http::baseUrl(config('services.asaas.base_url'))
            ->withHeaders([
                'access_token' => $apiKey,
            ])
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }

    private function jsonOrFail(Response $response, string $action): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $message = data_get($response->json(), 'errors.0.description')
            ?? data_get($response->json(), 'message')
            ?? "Não foi possível {$action} no Asaas.";

        throw new RuntimeException($message);
    }

    private function requiredString(array $payload, string $key, string $message): string
    {
        $value = data_get($payload, $key);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException($message);
        }

        return $value;
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }
}
