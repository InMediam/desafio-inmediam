<?php

namespace App\Http\Controllers;

use App\Enums\BillingStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\PayBillingRequest;
use App\Http\Resources\BillingResource;
use App\Http\Resources\PaymentResource;
use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Payment;
use App\Services\AsaasService;
use App\Support\DocumentValidator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingController
{
    private AsaasService $asaasService;

    public function __construct(AsaasService $asaasService)
    {
        $this->asaasService = $asaasService;
    }

    public function show(string $id): JsonResponse
    {
        $billing = Billing::with([
            'plan',
            'payments.creditCard',
        ])->find($id);

        if (! $billing) {
            return response()->json([
                'message' => 'Cobrança não encontrada.',
            ], 404);
        }

        return (new BillingResource($billing))->response();
    }

    public function pay(string $id, PayBillingRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($id, $request) {
                $billing = Billing::with('customer')
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first();

                if (! $billing) {
                    return response()->json([
                        'message' => 'Cobrança não encontrada.',
                    ], 404);
                }

                if ($billing->status !== BillingStatus::PENDING) {
                    return response()->json([
                        'message' => 'Esta cobrança não está disponível para pagamento.',
                    ], 409);
                }

                if (! DocumentValidator::isValid($billing->customer->document)) {
                    Log::error('Documento inválido do cliente ao processar pagamento.', [
                        'billing_id' => $id,
                        'customer_id' => $billing->customer_id,
                    ]);

                    return response()->json([
                        'message' => 'O CPF/CNPJ do cliente é inválido.',
                    ], 422);
                }

                $customer = $this->asaasService->createCustomer(
                    $billing->customer->name,
                    $billing->customer->email,
                    $billing->customer->document
                );

                if (empty($customer['id'])) {
                    Log::error('Resposta inválida do Asaas ao criar cliente.', [
                        'billing_id' => $id,
                    ]);

                    return response()->json([
                        'message' => 'Não foi possível processar o pagamento.',
                    ], 502);
                }

                $charge = $this->asaasService->createCharge(
                    $customer['id'],
                    $billing->amount,
                    $billing->due_date
                );

                if (empty($charge['id'])) {
                    Log::error('Resposta inválida do Asaas ao criar cobrança.', [
                        'billing_id' => $id,
                    ]);

                    return response()->json([
                        'message' => 'Não foi possível processar o pagamento.',
                    ], 502);
                }

                [$expiryMonth, $expiryYear] = explode(
                    '/',
                    $request->expiry_date
                );

                $response = $this->asaasService->payWithCreditCard(
                    $charge['id'],
                    [
                        'holderName' => $request->card_holder_name,
                        'number' => $request->card_number,
                        'expiryMonth' => $expiryMonth,
                        'expiryYear' => '20'.$expiryYear,
                        'ccv' => $request->cvv,
                    ],
                    [
                        'name' => $billing->customer->name,
                        'email' => $billing->customer->email,
                        'cpfCnpj' => $billing->customer->document,
                        'phone' => '0000000000',
                        'postalCode' => '00000000',
                        'addressNumber' => '0',
                    ]
                );

                $status = $response['status'] ?? null;

                if ($status !== PaymentStatus::CONFIRMED->value) {
                    return response()->json([
                        'message' => 'O pagamento não foi confirmado pelo Asaas.',
                    ], 422);
                }

                $creditCardData = $response['creditCard'] ?? null;

                if (
                    ! is_array($creditCardData) ||
                    empty($creditCardData['creditCardBrand']) ||
                    empty($creditCardData['creditCardToken'])
                ) {
                    Log::error('Resposta inválida do Asaas ao confirmar pagamento.', [
                        'billing_id' => $id,
                    ]);

                    return response()->json([
                        'message' => 'Não foi possível processar o pagamento.',
                    ], 502);
                }

                $cardToken = $creditCardData['creditCardToken'];

                $creditCard = CreditCard::create([
                    'customer_id' => $billing->customer_id,
                    'card_holder_name' => $request->card_holder_name,
                    'card_last_four' => substr($request->card_number, -4),
                    'card_brand' => $creditCardData['creditCardBrand'],
                    'card_token' => $cardToken,
                    'card_token_hash' => CreditCard::tokenHash($cardToken),
                ]);

                $payment = Payment::create([
                    'billing_id' => $billing->id,
                    'credit_card_id' => $creditCard->id,
                    'amount_paid' => $billing->amount,
                    'status' => PaymentStatus::CONFIRMED,
                    'paid_at' => now(),
                ]);

                $billing->status = BillingStatus::PAID;
                $billing->save();

                $payment->load('creditCard');

                return (new PaymentResource($payment))
                    ->toResponse($request)
                    ->setStatusCode(200);
            });
        } catch (ConnectionException $e) {
            Log::error('Erro de conexão com o Asaas ao processar pagamento.', [
                'billing_id' => $id,
                'exception' => $e::class,
            ]);

            return response()->json([
                'message' => 'Não foi possível conectar ao serviço de pagamento. Tente novamente.',
            ], 503);
        } catch (RequestException $e) {
            Log::error('Erro retornado pela API do Asaas ao processar pagamento.', [
                'billing_id' => $id,
                'status' => $e->response?->status(),
                'exception' => $e::class,
            ]);

            return response()->json([
                'message' => 'Não foi possível processar o pagamento.',
            ], 502);
        }
    }
}
