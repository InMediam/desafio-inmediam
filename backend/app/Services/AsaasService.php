<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Exception;

class AsaasService
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('asaas.api_key');
        $this->baseUrl = config('asaas.base_url');
    }

    private function client()
    {
        return Http::withHeaders(['access_token' => $this->apiKey,])->baseUrl($this->baseUrl);
    }

    /**
     * Encontra ou cria o cliente na API do Asaas para evitar duplicidade.
     */
    public function findOrCreateCustomer(Customer $customer): string
    {
        if (!empty($customer->asaas_id)) {
            return $customer->asaas_id;
        }

        $response = $this->client()->post('/customers', [
            'name' => $customer->name,
            'email' => $customer->email,
            'cpfCnpj' => $customer->document,
            'notificationDisabled' => true,
        ]);

        if (!$response->successful()) {
            throw new Exception('Falha ao criar cliente no gateway de pagamento: ' . $response->body());
        }

        $data = $response->json();
        $customer->update(['asaas_id' => $data['id']]);

        return $data['id'];
    }

    /**
     * Registra uma nova cobrança pendente.
     */
    public function createCharge(string $asaasCustomerId, float $amount, string $dueDate): array
    {
        $response = $this->client()->post('/payments', [
            'customer' => $asaasCustomerId,
            'billingType' => 'CREDIT_CARD',
            'value' => $amount,
            'dueDate' => $dueDate,
        ]);

        if (!$response->successful()) {
            throw new Exception('Falha ao gerar cobrança no gateway: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Executa o pagamento efetivo usando o cartão de crédito.
     */
    public function tokenizeAndPay(string $asaasChargeId, array $cardData, Customer $customer): array
    {
        [$expiryMonth, $expiryYear] = explode('/', $cardData['expiry_date']);

        $response = $this->client()->post("/payments/{$asaasChargeId}/payWithCreditCard", [
            'creditCard' => [
                'holderName' => $cardData['card_holder_name'],
                'number' => $cardData['card_number'],
                'expiryMonth' => Carbon::createFromFormat('m/y', $cardData['expiry_date'])->format('m'),
                'expiryYear' => Carbon::createFromFormat('m/y', $cardData['expiry_date'])->format('Y'),
                'ccv' => $cardData['cvv'],
            ],
            'creditCardHolderInfo' => [
                'name' => $customer->name,
                'email' => $customer->email,
                'cpfCnpj' => $customer->document,
                'phone' => '0000000000', 
                'postalCode' => '00000000',
                'addressNumber' => '0',
            ],
        ]);

        if (!$response->successful()) {
            throw new Exception('Pagamento recusado ou falha na transação: ' . $response->body());
        }

        return $response->json();
    }
}