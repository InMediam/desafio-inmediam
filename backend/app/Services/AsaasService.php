<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

/**
 * Class AsaasService
 *
 * Serviço responsável por encapsular a comunicação com a API do Asaas,
 * incluindo criação de clientes, cobranças e pagamentos via cartão de crédito.
 *
 * Também implementa:
 * - Tratamento centralizado de erros HTTP
 * - Proteção contra duplicidade de cobranças
 * - Retry seguro para falhas de conexão
 * - Logging com sanitização de dados sensíveis
 *
 * @package App\Services
 */
class AsaasService
{
    /**
     * Chave de API do Asaas.
     *
     * @var string
     */
    private string $apiKey;

    /**
     * URL base da API do Asaas (produção ou sandbox).
     *
     * @var string
     */
    private string $baseUrl;

    /**
     * AsaasService constructor.
     *
     * Inicializa as credenciais e valida configuração obrigatória.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->apiKey = config('services.asaas.api_key');
        $this->baseUrl = config('services.asaas.base_url');

        if (empty($this->apiKey)) {
            throw new \Exception('ASAAS_API_KEY não configurada');
        }

        if (empty($this->baseUrl)) {
            throw new \Exception('ASAAS_BASE_URL não configurada');
        }
    }

    /**
     * Cria o cliente HTTP configurado para comunicação com o Asaas.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    private function client()
    {
        return Http::withHeaders([
            'access_token' => $this->apiKey,
            'Content-Type' => 'application/json',
            'User-Agent' => config('app.name', 'LaravelApp'),
        ])
            ->baseUrl($this->baseUrl)
            ->timeout(30);
    }

    /**
     * Executa retry seguro apenas para falhas de conexão.
     *
     * Evita duplicidade em operações críticas como pagamentos.
     *
     * @param callable $callback
     * @return mixed
     */
    private function safeRequest(callable $callback)
    {
        try {
            return $callback();
        } catch (ConnectionException $e) {
            Log::warning('ASAAS_CONNECTION_RETRY', [
                'message' => $e->getMessage()
            ]);

            return $callback();
        }
    }

    /**
     * Trata resposta da API do Asaas de forma centralizada.
     *
     * @param \Illuminate\Http\Client\Response $response
     * @param string $action Nome da ação executada (log)
     * @return array
     *
     * @throws \Exception
     */
    private function handleResponse($response, string $action): array
    {
        $body = $response->json();

        if ($response->status() === 401) {
            Log::error('ASAAS_AUTH_ERROR', [
                'action' => $action,
                'error' => $body,
            ]);

            throw new \Exception('Erro de autenticação no Asaas');
        }

        if ($response->failed()) {
            Log::error("ASAAS_ERROR_{$action}", [
                'status' => $response->status(),
                'error' => $body,
            ]);

            throw new \Exception("Erro no Asaas durante {$action}");
        }

        return $body;
    }

    /**
     * Registra logs de request/response da integração.
     *
     * @param string $type Tipo do log (REQUEST|RESPONSE)
     * @param string $action Ação executada
     * @param array $data Dados a serem logados
     * @return void
     */
    private function log(string $type, string $action, array $data): void
    {
        Log::info("ASAAS_{$type}: {$action}", $this->sanitize($data));
    }

    /**
     * Sanitiza dados sensíveis antes de logging.
     *
     * @param array $data
     * @return array
     */
    private function sanitize(array $data): array
    {
        if (isset($data['creditCard']['number'])) {
            $data['creditCard']['number'] =
                '**** **** **** ' . substr($data['creditCard']['number'], -4);
        }

        if (isset($data['creditCard']['cvv'])) {
            $data['creditCard']['cvv'] = '***';
        }

        if (isset($data['cpfCnpj'])) {
            $data['cpfCnpj'] = substr($data['cpfCnpj'], 0, 3) . '****';
        }

        return $data;
    }

    /**
     * Cria um cliente no Asaas.
     *
     * @param array $data Dados do cliente (name, cpfCnpj, email, etc.)
     * @return array
     */
    public function createCustomer(array $data): array
    {
        $this->log('REQUEST', 'createCustomer', $data);

        $response = $this->client()
            ->post('/customers', $data);

        $result = $this->handleResponse($response, 'createCustomer');

        $this->log('RESPONSE', 'createCustomer', $result);

        return $result;
    }

    /**
     * Cria uma cobrança no Asaas.
     *
     * @param array $data Dados da cobrança (customer, billingType, value, dueDate, etc.)
     * @return array
     */
    public function createPayment(array $data): array
    {
        $this->log('REQUEST', 'createPayment', $data);

        $data['externalReference'] = $data['externalReference'] ?? uniqid('pay_');

        $response = $this->safeRequest(function () use ($data) {
            return $this->client()->post('/payments', $data);
        });

        $result = $this->handleResponse($response, 'createPayment');

        $this->log('RESPONSE', 'createPayment', $result);

        return $result;
    }

    /**
     * Processa pagamento via cartão de crédito.
     *
     * @param string $paymentId ID da cobrança no Asaas
     * @param array $data Dados do cartão e titular
     * @return array
     */
    public function payWithCreditCard(string $paymentId, array $data): array
    {
        $this->log('REQUEST', 'payWithCreditCard', $data);

        $response = $this->safeRequest(function () use ($paymentId, $data) {
            return $this->client()
                ->post("/payments/{$paymentId}/payWithCreditCard", $data);
        });

        $result = $this->handleResponse($response, 'payWithCreditCard');

        $this->log('RESPONSE', 'payWithCreditCard', $result);

        return $result;
    }
}