<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AsaasService
{
    private PendingRequest $client;

    public function __construct()
    {
        $this->client = Http::baseUrl(config('services.asaas.base_url'))
            ->withHeaders([
                'access_token' => config('services.asaas.api_key'),
            ]);
    }

    // Cria um cliente na Asaas.
    public function createCustomer(array $data)
    {
        try {
            return $this->client->post('/customers', $data);
        } catch (ConnectionException $e) {
            Log::error('Erro de conexão com a Asaas ao criar cliente.', [
                'exception' => $e->getMessage(),
            ]);
            throw new HttpException(
                502,
                'Não foi possível conectar à Asaas',
                $e
            );
        }
    }

    // Reutiliza o cliente existente na Asaas ou cria um novo quando necessário.
    public function getOrCreateCustomer($customer)
    {
        if ($customer->asaas_customer_id) {
            return $customer->asaas_customer_id;
        }

        $response = $this->createCustomer([
            'name' => $customer->name,
            'email' => $customer->email,
            'cpfCnpj' => $customer->document,
            'notificationDisabled' => true,
        ]);

        if (!$response->successful()) {
            Log::error('Erro ao criar cliente na Asaas.', [
                'status' => $response->status(),
                'error' => $response->json('errors.0.description'),
            ]);
            throw new HttpException(
                502,
                'Não foi possível cadastrar o cliente para o pagamento.'
            );
        }

        $customer->asaas_customer_id = $response->json('id');
        $customer->save();

        return $customer->asaas_customer_id;
    }

    // Cria uma cobrança na Asaas.
    public function createPayment(array $data)
    {
        try {
            return $this->client->post('/payments', $data);
        } catch (ConnectionException $e) {
            Log::error('Erro de conexão com a Asaas ao criar cobrança.', [
                'exception' => $e->getMessage(),
            ]);
            throw new HttpException(
                502,
                'Não foi possível conectar à Asaas',
                $e
            );
        }
    }

    // Processa uma cobrança com cartão de crédito na Asaas.
    public function payWithCreditCard(string $chargeId, array $data)
    {
        try {
            return $this->client->post(
                "/payments/{$chargeId}/payWithCreditCard",
                $data
            );
        } catch (ConnectionException $e) {
            Log::error('Erro de conexão com a Asaas ao processar pagamento com cartão.', [
                'charge_id' => $chargeId,
                'exception' => $e->getMessage(),
            ]);
            throw new HttpException(
                502,
                'Não foi possível conectar à Asaas',
                $e
            );
        }
    }

    // Cria a cobrança e processa o pagamento com cartão na Asaas.
    public function chargeCreditCard(array $paymentData, array $cardData)
    {
        $response = $this->createPayment($paymentData);

        if (!$response->successful()) {
            Log::error('Erro ao criar cobrança na Asaas.', [
                'status' => $response->status(),
                'error' => $response->json('errors.0.description'),
            ]);
            throw new HttpException(
                502,
                'Não foi possível criar a cobrança.'
            );
        }

        $chargeId = $response->json('id');

        $response = $this->payWithCreditCard($chargeId, $cardData);

        if (!$response->successful()) {
            Log::error('Erro ao processar pagamento com cartão na Asaas.', [
                'status' => $response->status(),
                'error' => $response->json('errors.0.description'),
            ]);
            throw new HttpException(
                422,
                'Não foi possível processar o pagamento.'
            );
        }

        return $response;
    }
}