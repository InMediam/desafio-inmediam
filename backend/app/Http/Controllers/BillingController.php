<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Http\Requests\PayBillingRequest;
use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BillingController
{
    public function __construct(private readonly PaymentGateway $gateway) {}

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

        $correlationId = (string) Str::uuid();

        Log::channel('payments')->info('billing.payment.started', [
            'billing_id' => (int) $id,
            'correlation_id' => $correlationId,
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey) {
            $existingPayment = Payment::where('idempotency_key', $idempotencyKey)->first();

            if ($existingPayment) {
                Log::channel('payments')->info('billing.payment.idempotent_replay', [
                    'billing_id' => (int) $existingPayment->billing_id,
                    'correlation_id' => $correlationId,
                ]);

                return response()->json($existingPayment);
            }
        }

        $result = DB::transaction(function () use ($id, $request, $idempotencyKey, $correlationId) {
            $billing = Billing::lockForUpdate()->find($id);

            if (! $billing) {
                Log::channel('payments')->warning('billing.payment.rejected', [
                    'billing_id' => (int) $id,
                    'correlation_id' => $correlationId,
                    'reason' => 'not_found',
                ]);

                return ['status' => 404, 'error' => 'Cobrança não encontrada'];
            }

            if ($billing->status === 'paid') {
                Log::channel('payments')->warning('billing.payment.rejected', [
                    'billing_id' => $billing->id,
                    'correlation_id' => $correlationId,
                    'asaas_charge_id' => $billing->asaas_charge_id,
                    'reason' => 'already_paid',
                ]);

                return ['status' => 409, 'error' => 'Cobrança já paga'];
            }

            if (! config('services.asaas.key')) {
                Log::channel('payments')->error('billing.payment.failed', [
                    'billing_id' => $billing->id,
                    'correlation_id' => $correlationId,
                ]);

                return ['status' => 502, 'error' => 'Não foi possível processar o pagamento. Tente novamente mais tarde.'];
            }

            $asaasCallStartedAt = microtime(true);

            try {
                $customerId = $billing->customer->asaas_customer_id;

                if (! $customerId) {
                    $customer = $this->gateway->createCustomer(
                        $billing->customer->name,
                        $billing->customer->email,
                        $billing->customer->document,
                    );

                    $customerId = $customer['id'];

                    $billing->customer->asaas_customer_id = $customerId;
                    $billing->customer->save();
                }

                $chargeId = $billing->asaas_charge_id;

                if (! $chargeId) {
                    $charge = $this->gateway->createCharge($customerId, $billing->amount, $billing->due_date);

                    $chargeId = $charge['id'];

                    $billing->asaas_charge_id = $chargeId;
                    $billing->save();
                }

                $chargeResult = $this->gateway->payWithCreditCard(
                    $chargeId,
                    [
                        'holderName' => $request->card_holder_name,
                        'number' => $request->card_number,
                        'expiryMonth' => $request->expiryMonth(),
                        'expiryYear' => $request->expiryYear(),
                        'cvv' => $request->cvv,
                    ],
                    [
                        'name' => $billing->customer->name,
                        'email' => $billing->customer->email,
                        'document' => $billing->customer->document,
                        'phone' => $billing->customer->phone,
                        'postalCode' => $billing->customer->postal_code,
                        'addressNumber' => $billing->customer->address_number,
                    ],
                );
            } catch (ConnectionException $exception) {
                Log::channel('payments')->error('billing.payment.failed', [
                    'billing_id' => $billing->id,
                    'correlation_id' => $correlationId,
                    'asaas_charge_id' => $chargeId ?? null,
                    'exception_class' => $exception::class,
                    'duration_ms' => (int) ((microtime(true) - $asaasCallStartedAt) * 1000),
                ]);

                return ['status' => 502, 'error' => 'Não foi possível processar o pagamento. Tente novamente mais tarde.'];
            }

            $asaasDurationMs = (int) ((microtime(true) - $asaasCallStartedAt) * 1000);

            if ($chargeResult['httpStatus'] >= 500) {
                Log::channel('payments')->error('billing.payment.failed', [
                    'billing_id' => $billing->id,
                    'correlation_id' => $correlationId,
                    'asaas_charge_id' => $chargeId,
                    'http_status' => $chargeResult['httpStatus'],
                    'asaas_error_code' => $chargeResult['errorCode'],
                    'asaas_error_description' => $chargeResult['errorDescription'],
                    'duration_ms' => $asaasDurationMs,
                ]);

                return ['status' => 502, 'error' => 'Não foi possível processar o pagamento. Tente novamente mais tarde.'];
            }

            if ($chargeResult['httpStatus'] >= 400) {
                Log::channel('payments')->warning('billing.payment.declined', [
                    'billing_id' => $billing->id,
                    'correlation_id' => $correlationId,
                    'asaas_charge_id' => $chargeId,
                    'http_status' => $chargeResult['httpStatus'],
                    'asaas_error_code' => $chargeResult['errorCode'],
                    'asaas_error_description' => $chargeResult['errorDescription'],
                    'duration_ms' => $asaasDurationMs,
                ]);

                return ['status' => 422, 'error' => 'Pagamento recusado. Verifique os dados do cartão e tente novamente.'];
            }

            $creditCard = CreditCard::create([
                'customer_id' => $billing->customer_id,
                'card_holder_name' => $request->card_holder_name,
                'card_last_four' => $chargeResult['cardLastFour'],
                'card_brand' => $chargeResult['cardBrand'],
                'card_token' => $chargeResult['cardToken'],
            ]);

            $payment = Payment::create([
                'billing_id' => $billing->id,
                'credit_card_id' => $creditCard->id,
                'amount_paid' => $billing->amount,
                'status' => $chargeResult['status'],
                'paid_at' => now(),
                'asaas_charge_id' => $chargeId,
                'idempotency_key' => $idempotencyKey,
            ]);

            $isConfirmed = in_array($chargeResult['status'], ['CONFIRMED', 'RECEIVED'], true);

            $billing->status = $isConfirmed ? 'paid' : 'pending';
            $billing->save();

            Log::channel('payments')->info($isConfirmed ? 'billing.payment.confirmed' : 'billing.payment.pending', [
                'billing_id' => $billing->id,
                'correlation_id' => $correlationId,
                'asaas_charge_id' => $chargeId,
                'status' => $chargeResult['status'],
                'http_status' => $chargeResult['httpStatus'],
                'duration_ms' => $asaasDurationMs,
            ]);

            return ['status' => 200, 'payment' => $payment];
        });

        if ($result['status'] !== 200) {
            return response()->json(['error' => $result['error']], $result['status']);
        }

        return response()->json($result['payment']);
    }
}
