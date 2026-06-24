<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class AsaasService extends AbstractService
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('asaas.apiKey');
        $this->baseUrl = config('asaas.baseUrl');
    }

    public function createCustomer(array $data): object
    {
        $this->validate($data, [
            'name' => 'required|string',
            'email' => 'required|email',
            'cpfCnpj' => 'required|string',
            'notificationDisabled' => 'required|boolean',
        ]);

        $response = Http::withHeaders(['access_token' => $this->apiKey])
            ->post("{$this->baseUrl}/customers", $data);

        if ($response->failed()) {
            throw new Exception("Failed to create customer: {$response->body()}", $response->status());
        }

        return (object) $response->json();
    }

    public function createCharge(array $data): object
    {
        $this->validate($data, [
            'customer' => 'required|string',
            'billingType' => 'required|string',
            'value' => 'required|numeric',
            'dueDate' => 'required|string',
        ]);

        $response = Http::withHeaders(['access_token' => $this->apiKey])
            ->post("{$this->baseUrl}/payments", $data);

        if ($response->failed()) {
            throw new Exception("Failed to create charge: {$response->body()}", $response->status());
        }

        return (object) $response->json();
    }

    public function payWithCreditCard(string $chargeId, array $creditCard, array $holderInfo): object
    {
        $this->validate($creditCard, [
            'holderName' => 'required|string',
            'number' => 'required|string',
            'expiryMonth' => 'required|string',
            'expiryYear' => 'required|string',
            'ccv' => 'required|string',
        ]);

        $this->validate($holderInfo, [
            'name' => 'required|string',
            'email' => 'required|email',
            'cpfCnpj' => 'required|string',
            'phone' => 'required|string',
            'postalCode' => 'required|string',
            'addressNumber' => 'required|string',
        ]);

        $response = Http::withHeaders(['access_token' => $this->apiKey])
            ->post("{$this->baseUrl}/payments/{$chargeId}/payWithCreditCard", [
                'creditCard' => $creditCard,
                'creditCardHolderInfo' => $holderInfo,
            ]);

        if ($response->failed()) {
            throw new Exception("Payment failed: {$response->body()}", $response->status());
        }

        return (object) $response->json();
    }
}
