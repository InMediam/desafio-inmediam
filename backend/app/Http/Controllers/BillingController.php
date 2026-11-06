<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayBillingRequest;
use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BillingController
{
    public function show(string $id): JsonResponse
    {
        if (! ctype_digit($id)) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        $billing = Billing::with(['plan', 'payments.creditCard'])->find($id);

        if (! $billing) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        return response()->json($billing);
    }

    public function pay(string $id, PayBillingRequest $request): JsonResponse
    {
        if (! ctype_digit($id)) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        Log::channel('payments')->info('billing.payment.started', ['billing_id' => (int) $id]);

        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey) {
            $existingPayment = Payment::where('idempotency_key', $idempotencyKey)->first();

            if ($existingPayment) {
                return response()->json($existingPayment);
            }
        }

        $result = DB::transaction(function () use ($id, $request, $idempotencyKey) {
            $billing = Billing::lockForUpdate()->find($id);

            if (! $billing) {
                Log::channel('payments')->warning('billing.payment.rejected', [
                    'billing_id' => (int) $id,
                    'reason' => 'not_found',
                ]);

                return ['status' => 404, 'error' => 'Cobrança não encontrada'];
            }

            if ($billing->status === 'paid') {
                Log::channel('payments')->warning('billing.payment.rejected', [
                    'billing_id' => $billing->id,
                    'asaas_charge_id' => $billing->asaas_charge_id,
                    'reason' => 'already_paid',
                ]);

                return ['status' => 409, 'error' => 'Cobrança já paga'];
            }

            $apiKey = config('services.asaas.key');
            $baseUrl = config('services.asaas.base_url');

            if (! $apiKey) {
                Log::channel('payments')->error('billing.payment.failed', ['billing_id' => $billing->id]);

                return ['status' => 502, 'error' => 'Não foi possível processar o pagamento. Tente novamente mais tarde.'];
            }

            try {
                $customerId = $billing->customer->asaas_customer_id;

                if (! $customerId) {
                    $customerResponse = Http::timeout(10)->withHeaders(['access_token' => $apiKey])->post("$baseUrl/customers", [
                        'name' => $billing->customer->name,
                        'email' => $billing->customer->email,
                        'cpfCnpj' => $billing->customer->document,
                        'notificationDisabled' => true, // ATENÇÃO: Não recomendo modificar, pois o Asaas envia notificações mesmo no ambiente de sandbox.
                    ]);

                    $customerId = $customerResponse->json('id');

                    $billing->customer->asaas_customer_id = $customerId;
                    $billing->customer->save();
                }

                $chargeId = $billing->asaas_charge_id;

                if (! $chargeId) {
                    $chargeResponse = Http::timeout(10)->withHeaders(['access_token' => $apiKey])->post("$baseUrl/payments", [
                        'customer' => $customerId,
                        'billingType' => 'CREDIT_CARD',
                        'value' => $billing->amount,
                        'dueDate' => $billing->due_date,
                    ]);

                    $chargeId = $chargeResponse->json('id');

                    $billing->asaas_charge_id = $chargeId;
                    $billing->save();
                }

                $response = Http::timeout(10)->withHeaders(['access_token' => $apiKey])->post("$baseUrl/payments/{$chargeId}/payWithCreditCard", [
                    'creditCard' => [
                        'holderName' => $request->card_holder_name,
                        'number' => $request->card_number,
                        'expiryMonth' => $request->expiryMonth(),
                        'expiryYear' => $request->expiryYear(),
                        'ccv' => $request->cvv,
                    ],
                    'creditCardHolderInfo' => [
                        'name' => $billing->customer->name,
                        'email' => $billing->customer->email,
                        'cpfCnpj' => $billing->customer->document,
                        'phone' => $billing->customer->phone,
                        'postalCode' => $billing->customer->postal_code,
                        'addressNumber' => $billing->customer->address_number,
                    ],
                ]);
            } catch (ConnectionException) {
                Log::channel('payments')->error('billing.payment.failed', [
                    'billing_id' => $billing->id,
                    'asaas_charge_id' => $chargeId ?? null,
                ]);

                return ['status' => 502, 'error' => 'Não foi possível processar o pagamento. Tente novamente mais tarde.'];
            }

            if ($response->serverError()) {
                Log::channel('payments')->error('billing.payment.failed', [
                    'billing_id' => $billing->id,
                    'asaas_charge_id' => $chargeId,
                    'http_status' => $response->status(),
                ]);

                return ['status' => 502, 'error' => 'Não foi possível processar o pagamento. Tente novamente mais tarde.'];
            }

            if ($response->clientError()) {
                Log::channel('payments')->warning('billing.payment.declined', [
                    'billing_id' => $billing->id,
                    'asaas_charge_id' => $chargeId,
                    'http_status' => $response->status(),
                ]);

                return ['status' => 422, 'error' => 'Pagamento recusado. Verifique os dados do cartão e tente novamente.'];
            }

            $responseData = (object) $response->json();

            $creditCard = CreditCard::create([
                'customer_id' => $billing->customer_id,
                'card_holder_name' => $request->card_holder_name,
                'card_last_four' => $responseData->creditCard['creditCardNumber'],
                'card_brand' => $responseData->creditCard['creditCardBrand'],
                'card_token' => $responseData->creditCard['creditCardToken'],
            ]);

            $payment = Payment::create([
                'billing_id' => $billing->id,
                'credit_card_id' => $creditCard->id,
                'amount_paid' => $billing->amount,
                'status' => $responseData->status,
                'paid_at' => now(),
                'asaas_charge_id' => $chargeId,
                'idempotency_key' => $idempotencyKey,
            ]);

            $billing->status = in_array($responseData->status, ['CONFIRMED', 'RECEIVED'], true)
                ? 'paid'
                : 'pending';
            $billing->save();

            Log::channel('payments')->info('billing.payment.confirmed', [
                'billing_id' => $billing->id,
                'asaas_charge_id' => $chargeId,
                'status' => $responseData->status,
                'http_status' => $response->status(),
            ]);

            return ['status' => 200, 'payment' => $payment];
        });

        if ($result['status'] !== 200) {
            return response()->json(['error' => $result['error']], $result['status']);
        }

        return response()->json($result['payment']);
    }
}
