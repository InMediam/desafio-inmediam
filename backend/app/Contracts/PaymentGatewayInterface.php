<?php

namespace App\Contracts;

use App\Models\Customer;

interface PaymentGatewayInterface
{
    /**
     * Nome do gateway (ex: 'asaas', 'stripe') para log e identificação.
     */
    public function getName(): string;

    /**
     * Retorna o ID do cliente armazenado para este gateway no model Customer, ou null.
     */
    public function getStoredCustomerId(Customer $customer): ?string;

    /**
     * Persiste o ID do cliente do gateway no model Customer. Passar null para limpar.
     */
    public function setStoredCustomerId(Customer $customer, ?string $gatewayCustomerId): void;

    /**
     * Cria cliente no gateway.
     *
     * @return array{success: bool, data: array|null, errors: array, message: string}
     */
    public function createCustomer(Customer $customer): array;

    /**
     * Verifica status do cliente no gateway (active, removed, deleted, error).
     * Gateways que não suportam podem retornar success: false e status: null.
     *
     * @return array{success: bool, status: string|null, data: array|null, errors: array, message: string}
     */
    public function getCustomerStatus(string $gatewayCustomerId): array;

    /**
     * Restaura cliente removido/deletado no gateway.
     * Gateways que não suportam podem retornar success: false.
     *
     * @return array{success: bool, data: array|null, errors: array, message: string}
     */
    public function restoreCustomer(string $gatewayCustomerId): array;

    /**
     * Cria cobrança no gateway.
     *
     * @return array{success: bool, data: array|null, errors: array, message: string}
     */
    public function createCharge(string $gatewayCustomerId, float $value, string $dueDate): array;

    /**
     * Processa pagamento com cartão. data deve conter id (pagamento), status e creditCard quando sucesso.
     *
     * @return array{success: bool, data: array|null, errors: array, message: string}
     */
    public function payWithCreditCard(
        string $chargeId,
        array $creditCard,
        array $holderInfo,
        ?string $remoteIp = null
    ): array;
}
