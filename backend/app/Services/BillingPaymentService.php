<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Http\Requests\PayBillingRequest;
use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Customer;
use App\Models\GatewayTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class BillingPaymentService
{
    public function __construct(
        private PaymentGatewayInterface $gateway
    ) {
    }

    /**
     * Orquestra o pagamento: cliente no gateway → cobrança → pagamento com cartão.
     * Não lança exceção; erros do gateway são tratados e devolvidos no resultado.
     *
     * @return array{ok: bool, payment: \App\Models\Payment|null, httpStatus: int, message: string}
     */
    public function processPayment(Billing $billing, PayBillingRequest $request, ?string $clientIp = null): array
    {
        $billingId = $billing->id;
        $customer = $billing->customer;
        $gatewayName = $this->gateway->getName();

        if (!$customer) {
            Log::warning('BillingPayment: cobrança sem cliente', ['billing_id' => $billingId]);
            $this->registerTransaction(
                $billing,
                null,
                null,
                null,
                null,
                null,
                ['step' => 'ensure_customer'],
                null,
                'Cliente não encontrado para esta cobrança.',
                GatewayTransaction::STATUS_FAILED
            );
            return [
                'ok' => false,
                'payment' => null,
                'httpStatus' => 404,
                'message' => 'Cliente não encontrado para esta cobrança.',
            ];
        }

        $amount = (float) $billing->amount;
        $dueDate = $billing->due_date;

        // 1) Garantir cliente no gateway: reutilizar existente (com check/restore) ou criar
        $ensureResult = $this->ensureGatewayCustomer($customer, $billingId);
        if (!$ensureResult['ok']) {
            $this->registerTransaction(
                $billing,
                $customer->id,
                null,
                null,
                null,
                null,
                ['step' => 'ensure_customer'],
                $ensureResult,
                $ensureResult['message'],
                GatewayTransaction::STATUS_FAILED
            );
            return [
                'ok' => false,
                'payment' => null,
                'httpStatus' => $ensureResult['httpStatus'],
                'message' => $ensureResult['message'],
            ];
        }
        $gatewayCustomerId = $ensureResult['gatewayCustomerId'];

        // 2) Criar cobrança
        $chargeResult = $this->gateway->createCharge($gatewayCustomerId, $amount, $dueDate);
        if (!$chargeResult['success']) {
            $msg = $chargeResult['message'] ?: 'Falha ao criar cobrança no gateway.';
            Log::info("BillingPayment: criação de cobrança {$gatewayName} falhou", [
                'billing_id' => $billingId,
                'errors' => $chargeResult['errors'],
            ]);
            $this->registerTransaction(
                $billing,
                $customer->id,
                null,
                null,
                null,
                null,
                ['step' => 'create_charge', 'gateway_customer_id' => $gatewayCustomerId],
                array_merge($chargeResult['data'] ?? [], ['errors' => $chargeResult['errors']]),
                $msg,
                GatewayTransaction::STATUS_FAILED
            );
            return [
                'ok' => false,
                'payment' => null,
                'httpStatus' => 422,
                'message' => $msg,
            ];
        }

        $chargeId = $chargeResult['data']['id'] ?? null;
        if (!$chargeId) {
            Log::error("BillingPayment: {$gatewayName} não retornou ID da cobrança", [
                'billing_id' => $billingId,
                'response' => $chargeResult['data'],
            ]);
            $this->registerTransaction(
                $billing,
                $customer->id,
                null,
                null,
                null,
                null,
                ['step' => 'create_charge'],
                $chargeResult['data'],
                'Gateway não retornou o ID da cobrança.',
                GatewayTransaction::STATUS_FAILED
            );
            return [
                'ok' => false,
                'payment' => null,
                'httpStatus' => 502,
                'message' => 'Gateway não retornou o ID da cobrança. Tente novamente.',
            ];
        }

        // 3) Pagar com cartão
        $parts = explode('/', $request->validated('expiry_date'));
        $expiryMonth = $parts[0] ?? '';
        $expiryYear = '20' . (isset($parts[1]) ? trim($parts[1]) : '');

        // Asaas exige CEP válido no creditCardHolderInfo; sem endereço do cliente usamos um CEP genérico válido (Av. Paulista, SP)
        $phone = $customer->phone ?? '11999999999';
        $postalCode = $customer->postal_code ?? '01310100';
        $addressNumber = $customer->address_number ?? '1';

        $paymentPayload = $this->gateway->payWithCreditCard(
            $chargeId,
            [
                'holderName' => $request->validated('card_holder_name'),
                'number' => $request->validated('card_number'),
                'expiryMonth' => $expiryMonth,
                'expiryYear' => $expiryYear,
                'ccv' => $request->validated('cvv'),
            ],
            [
                'name' => $customer->name,
                'email' => $customer->email,
                'cpfCnpj' => $customer->document,
                'phone' => $phone,
                'mobilePhone' => $phone,
                'postalCode' => $postalCode,
                'addressNumber' => $addressNumber,
            ],
            $clientIp
        );

        if (!$paymentPayload['success']) {
            $msg = $paymentPayload['message'] ?: 'Pagamento não foi aprovado pelo gateway.';
            Log::info("BillingPayment: pagamento com cartão falhou ({$gatewayName})", [
                'billing_id' => $billingId,
                'errors' => $paymentPayload['errors'],
            ]);
            $this->registerTransaction(
                $billing,
                $customer->id,
                null,
                $chargeId,
                null,
                null,
                ['step' => 'pay_with_card', 'charge_id' => $chargeId],
                array_merge($paymentPayload['data'] ?? [], ['errors' => $paymentPayload['errors']]),
                $msg,
                GatewayTransaction::STATUS_FAILED
            );
            return [
                'ok' => false,
                'payment' => null,
                'httpStatus' => 422,
                'message' => $msg,
            ];
        }

        $data = $paymentPayload['data'];
        $status = $data['status'] ?? '';
        $creditCardData = $data['creditCard'] ?? null;

        if (strtoupper($status) !== 'CONFIRMED' && strtoupper($status) !== 'RECEIVED') {
            $msg = $data['errors'][0]['description'] ?? 'Pagamento não foi aprovado pelo gateway.';
            Log::info("BillingPayment: status do pagamento não é sucesso ({$gatewayName})", [
                'billing_id' => $billingId,
                'status' => $status,
                'response' => $data,
            ]);
            $this->registerTransaction(
                $billing,
                $customer->id,
                null,
                $chargeId,
                null,
                null,
                ['step' => 'pay_with_card', 'charge_id' => $chargeId],
                $data,
                $msg,
                GatewayTransaction::STATUS_FAILED
            );
            return [
                'ok' => false,
                'payment' => null,
                'httpStatus' => 422,
                'message' => $msg,
            ];
        }

        if (!$creditCardData || !is_array($creditCardData)) {
            Log::warning("BillingPayment: dados do cartão não retornados ({$gatewayName})", ['billing_id' => $billingId]);
            $this->registerTransaction(
                $billing,
                $customer->id,
                null,
                $data['id'] ?? $chargeId,
                null,
                null,
                ['step' => 'pay_with_card', 'charge_id' => $chargeId],
                $data,
                'Dados do cartão não retornados pelo gateway.',
                GatewayTransaction::STATUS_FAILED
            );
            return [
                'ok' => false,
                'payment' => null,
                'httpStatus' => 502,
                'message' => 'Dados do cartão não retornados pelo gateway.',
            ];
        }

        $cardLastFour = $creditCardData['creditCardNumber'] ?? substr($request->validated('card_number'), -4);
        $cardToken = $creditCardData['creditCardToken'] ?? ('local_' . uniqid('', true));

        $creditCard = CreditCard::create([
            'customer_id' => $customer->id,
            'card_holder_name' => $request->validated('card_holder_name'),
            'card_last_four' => substr((string) $cardLastFour, -4),
            'card_brand' => $creditCardData['creditCardBrand'] ?? 'UNKNOWN',
            'card_token' => $cardToken,
        ]);

        $asaasPaymentId = $data['id'] ?? null;
        $payment = Payment::create([
            'billing_id' => $billing->id,
            'credit_card_id' => $creditCard->id,
            'asaas_payment_id' => $asaasPaymentId,
            'amount_paid' => $amount,
            'status' => $status,
            'paid_at' => now(),
        ]);

        $billing->status = 'paid';
        $billing->save();

        $cardDisplay = '****' . substr((string) $cardLastFour, -4);
        $this->registerTransaction(
            $billing,
            $customer->id,
            $payment->id,
            $asaasPaymentId,
            $creditCard->id,
            $cardDisplay,
            ['step' => 'pay_with_card', 'charge_id' => $chargeId],
            $data,
            'Pagamento concluído com sucesso.',
            GatewayTransaction::STATUS_SUCCESS
        );

        Log::info("BillingPayment: pagamento concluído ({$gatewayName})", [
            'billing_id' => $billingId,
            'payment_id' => $payment->id,
        ]);

        return [
            'ok' => true,
            'payment' => $payment->load('creditCard'),
            'httpStatus' => 200,
            'message' => '',
        ];
    }

    /**
     * Registra uma tentativa de transação no histórico (sucesso ou falha).
     *
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>|null  $response
     */
    private function registerTransaction(
        Billing $billing,
        ?int $customerId,
        ?int $paymentId,
        ?string $externalId,
        ?int $creditCardId,
        ?string $cardDisplay,
        ?array $payload,
        ?array $response,
        ?string $message,
        string $status
    ): void {
        GatewayTransaction::record(
            $billing->id,
            $customerId,
            $paymentId,
            $externalId,
            $creditCardId,
            $cardDisplay,
            $payload,
            $response,
            $message,
            $billing->amount,
            $status,
            $this->gateway->getName()
        );
    }

    /**
     * Garante que o cliente existe e está ativo no gateway.
     * Reutiliza ID armazenado (com check/restore quando suportado) ou cria novo e persiste.
     *
     * @return array{ok: bool, gatewayCustomerId: string|null, httpStatus: int, message: string}
     */
    private function ensureGatewayCustomer(Customer $customer, int $billingId): array
    {
        $gatewayName = $this->gateway->getName();
        $storedId = $this->gateway->getStoredCustomerId($customer);

        if ($storedId) {
            $statusResult = $this->gateway->getCustomerStatus($storedId);

            if ($statusResult['success'] && ($statusResult['status'] === 'active')) {
                return [
                    'ok' => true,
                    'gatewayCustomerId' => $storedId,
                    'httpStatus' => 200,
                    'message' => '',
                ];
            }

            if ($statusResult['status'] === 'removed' || $statusResult['status'] === 'deleted') {
                Log::info("BillingPayment: cliente {$gatewayName} removido/deletado, tentando restaurar", [
                    'billing_id' => $billingId,
                    'customer_id' => $customer->id,
                    'gateway_customer_id' => $storedId,
                ]);
                $restoreResult = $this->gateway->restoreCustomer($storedId);
                if ($restoreResult['success']) {
                    Log::info("BillingPayment: cliente {$gatewayName} restaurado", [
                        'billing_id' => $billingId,
                        'gateway_customer_id' => $storedId,
                    ]);
                    return [
                        'ok' => true,
                        'gatewayCustomerId' => $storedId,
                        'httpStatus' => 200,
                        'message' => '',
                    ];
                }
                Log::warning("BillingPayment: falha ao restaurar cliente {$gatewayName}", [
                    'billing_id' => $billingId,
                    'errors' => $restoreResult['errors'],
                ]);
                return [
                    'ok' => false,
                    'gatewayCustomerId' => null,
                    'httpStatus' => 422,
                    'message' => $restoreResult['message'] ?: 'Cliente foi removido no gateway e não foi possível restaurá-lo. Tente novamente.',
                ];
            }

            if ($statusResult['status'] === 'error') {
                Log::warning("BillingPayment: erro ao verificar status do cliente {$gatewayName}, criando novo", [
                    'billing_id' => $billingId,
                    'gateway_customer_id' => $storedId,
                ]);
                $this->gateway->setStoredCustomerId($customer, null);
                $customer->refresh();
            }
        }

        $customerResult = $this->gateway->createCustomer($customer);
        if (!$customerResult['success']) {
            $msg = $customerResult['message'] ?: 'Falha ao registrar cliente no gateway de pagamento.';
            Log::info("BillingPayment: criação de cliente {$gatewayName} falhou", [
                'billing_id' => $billingId,
                'customer_id' => $customer->id,
                'errors' => $customerResult['errors'],
            ]);
            return [
                'ok' => false,
                'gatewayCustomerId' => null,
                'httpStatus' => 422,
                'message' => $msg,
            ];
        }

        $gatewayCustomerId = $customerResult['data']['id'] ?? null;
        if (!$gatewayCustomerId) {
            Log::error("BillingPayment: {$gatewayName} não retornou ID do cliente", [
                'billing_id' => $billingId,
                'response' => $customerResult['data'],
            ]);
            return [
                'ok' => false,
                'gatewayCustomerId' => null,
                'httpStatus' => 502,
                'message' => 'Gateway não retornou o ID do cliente. Tente novamente.',
            ];
        }

        $this->gateway->setStoredCustomerId($customer, $gatewayCustomerId);

        return [
            'ok' => true,
            'gatewayCustomerId' => $gatewayCustomerId,
            'httpStatus' => 200,
            'message' => '',
        ];
    }
}
