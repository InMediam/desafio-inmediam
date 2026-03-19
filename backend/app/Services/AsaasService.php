<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Customer;
use App\Models\CustomerGateway;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsaasService implements PaymentGatewayInterface
{
    private string $apiKey;

    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) (config('services.asaas.api_key') ?? '');
        $this->baseUrl = rtrim((string) (config('services.asaas.base_url') ?? 'https://sandbox.asaas.com/api/v3'), '/');
    }

    public function getName(): string
    {
        return 'asaas';
    }

    public function getStoredCustomerId(Customer $customer): ?string
    {
        $row = CustomerGateway::where('customer_id', $customer->id)
            ->where('gateway', $this->getName())
            ->first();

        return $row?->external_id;
    }

    public function setStoredCustomerId(Customer $customer, ?string $gatewayCustomerId): void
    {
        $query = CustomerGateway::where('customer_id', $customer->id)->where('gateway', $this->getName());

        if ($gatewayCustomerId === null || $gatewayCustomerId === '') {
            $query->delete();
            return;
        }

        CustomerGateway::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'gateway' => $this->getName(),
            ],
            ['external_id' => $gatewayCustomerId]
        );
    }

    private function headers(): array
    {
        return ['access_token' => $this->apiKey];
    }

    /**
     * Cria cliente no Asaas. Não interrompe o fluxo com exceção: retorna resultado estruturado.
     *
     * @return array{success: bool, data: array|null, errors: array, message: string}
     */
    public function createCustomer(Customer $customer): array
    {
        $response = Http::timeout(15)
            ->withHeaders($this->headers())
            ->post("{$this->baseUrl}/customers", [
                'name' => $customer->name,
                'email' => $customer->email,
                'cpfCnpj' => $customer->document,
                'notificationDisabled' => true,
            ]);

        return $this->parseResponse('customers', $response, 'Erro ao criar cliente no gateway de pagamento.', [
            'user_id' => $customer->id,
        ]);
    }

    /**
     * Verifica o status de um cliente no Asaas.
     * Retorna: 'active', 'removed', 'deleted', 'error' ou null (inválido).
     *
     * @return array{success: bool, status: string|null, data: array|null, errors: array, message: string}
     */
    public function getCustomerStatus(string $gatewayCustomerId): array
    {
        if ($gatewayCustomerId === '') {
            return [
                'success' => false,
                'status' => null,
                'data' => null,
                'errors' => [],
                'message' => 'ID do cliente inválido.',
            ];
        }

        $response = Http::timeout(15)
            ->withHeaders($this->headers())
            ->get("{$this->baseUrl}/customers/{$gatewayCustomerId}");

        if ($response->successful()) {
            $data = $response->json();
            $deleted = $data['deleted'] ?? false;
            $status = $deleted === true ? 'removed' : 'active';
            return [
                'success' => true,
                'status' => $status,
                'data' => is_array($data) ? $data : null,
                'errors' => [],
                'message' => '',
            ];
        }

        if ($response->status() === 404) {
            Log::info('Asaas: cliente não encontrado (possivelmente deletado)', [
                'customer_id' => $gatewayCustomerId,
            ]);
            return [
                'success' => true,
                'status' => 'deleted',
                'data' => null,
                'errors' => [],
                'message' => '',
            ];
        }

        Log::warning('Asaas: erro ao verificar status do cliente', [
            'customer_id' => $gatewayCustomerId,
            'status' => $response->status(),
            'response' => $response->json(),
        ]);
        return [
            'success' => false,
            'status' => 'error',
            'data' => null,
            'errors' => [],
            'message' => 'Erro ao verificar status do cliente no gateway.',
        ];
    }

    /**
     * Restaura um cliente removido no Asaas.
     *
     * @return array{success: bool, data: array|null, errors: array, message: string}
     */
    public function restoreCustomer(string $gatewayCustomerId): array
    {
        if ($gatewayCustomerId === '') {
            return [
                'success' => false,
                'data' => null,
                'errors' => [],
                'message' => 'ID do cliente inválido.',
            ];
        }

        $response = Http::timeout(15)
            ->withHeaders($this->headers())
            ->post("{$this->baseUrl}/customers/{$gatewayCustomerId}/restore");

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'data' => is_array($data) ? $data : null,
                'errors' => [],
                'message' => '',
            ];
        }

        $body = $response->json();
        $errors = [];
        $message = 'Erro ao restaurar cliente no gateway.';
        if (is_array($body) && !empty($body['errors'])) {
            foreach ($body['errors'] as $err) {
                $errors[] = [
                    'code' => $err['code'] ?? null,
                    'description' => $err['description'] ?? null,
                ];
            }
            $first = $errors[0] ?? [];
            $message = $first['description'] ?? $first['code'] ?? $message;
        }

        Log::error('Asaas: erro ao restaurar cliente', [
            'customer_id' => $gatewayCustomerId,
            'status' => $response->status(),
            'response' => $body,
        ]);
        return [
            'success' => false,
            'data' => null,
            'errors' => $errors,
            'message' => $message,
        ];
    }

    /**
     * Cria cobrança no Asaas.
     *
     * @return array{success: bool, data: array|null, errors: array, message: string}
     */
    public function createCharge(string $gatewayCustomerId, float $value, string $dueDate): array
    {
        $response = Http::timeout(15)
            ->withHeaders($this->headers())
            ->post("{$this->baseUrl}/payments", [
                'customer' => $gatewayCustomerId,
                'billingType' => 'CREDIT_CARD',
                'value' => $value,
                'dueDate' => $dueDate,
            ]);

        return $this->parseResponse('payments', $response, 'Erro ao criar cobrança no gateway de pagamento.', [
            'customer_id' => $gatewayCustomerId,
        ]);
    }

    /**
     * Processa pagamento com cartão no Asaas.
     * Inclui remoteIp quando informado (recomendado pelo Asaas para antifraude).
     *
     * @return array{success: bool, data: array|null, errors: array, message: string}
     */
    public function payWithCreditCard(
        string $chargeId,
        array $creditCard,
        array $holderInfo,
        ?string $remoteIp = null
    ): array {
        $payload = [
            'creditCard' => $creditCard,
            'creditCardHolderInfo' => $holderInfo,
        ];
        if ($remoteIp !== null && $remoteIp !== '') {
            $payload['remoteIp'] = $remoteIp;
        }

        $response = Http::timeout(15)
            ->withHeaders($this->headers())
            ->post("{$this->baseUrl}/payments/{$chargeId}/payWithCreditCard", $payload);

        return $this->parseResponse('payWithCreditCard', $response, 'Erro ao processar pagamento com cartão.', [
            'charge_id' => $chargeId,
        ]);
    }

    /**
     * Parseia resposta do Asaas: sucesso retorna data; falha retorna errors e message sem lançar exceção.
     *
     * @param  array<string, mixed>  $logContext
     * @return array{success: bool, data: array|null, errors: array, message: string}
     */
    private function parseResponse(string $operation, Response $response, string $genericMessage, array $logContext = []): array
    {
        $body = $response->json();
        $errors = [];
        $message = $genericMessage;

        if ($response->successful()) {
            $data = is_array($body) ? $body : null;
            return [
                'success' => true,
                'data' => $data,
                'errors' => [],
                'message' => '',
            ];
        }

        if (is_array($body) && !empty($body['errors'])) {
            foreach ($body['errors'] as $err) {
                $errors[] = [
                    'code' => $err['code'] ?? null,
                    'description' => $err['description'] ?? null,
                ];
            }
            $first = $errors[0] ?? [];
            $message = $first['description'] ?? $first['code'] ?? $genericMessage;
        } elseif (is_array($body) && !empty($body['error'])) {
            $message = is_string($body['error']) ? $body['error'] : json_encode($body['error']);
            $errors[] = ['code' => null, 'description' => $message];
        }

        Log::warning("Asaas: falha em {$operation}", array_merge($logContext, [
            'status' => $response->status(),
            'errors' => $errors,
            'response' => $body,
        ]));

        return [
            'success' => false,
            'data' => null,
            'errors' => $errors,
            'message' => $message,
        ];
    }
}
