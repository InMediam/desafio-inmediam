<?php

namespace App\Services\Billing;

use App\Contracts\PaymentGateway;
use App\Enums\BillingStatus;
use App\Exceptions\BillingAlreadyPaidException;
use App\Exceptions\BillingNotFoundException;
use App\Exceptions\PaymentDeclinedException;
use App\Exceptions\PaymentGatewayException;
use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BillingPaymentService
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function pay(string $billingId, CreditCardData $card, ?string $idempotencyKey): Payment
    {
        if (! ctype_digit($billingId)) {
            throw new BillingNotFoundException;
        }

        $correlationId = (string) Str::uuid();

        Log::channel('payments')->info('billing.payment.started', [
            'billing_id' => (int) $billingId,
            'correlation_id' => $correlationId,
        ]);

        if ($idempotencyKey) {
            $existingPayment = Payment::where('idempotency_key', $idempotencyKey)->first();

            if ($existingPayment) {
                Log::channel('payments')->info('billing.payment.idempotent_replay', [
                    'billing_id' => (int) $existingPayment->billing_id,
                    'correlation_id' => $correlationId,
                ]);

                return $existingPayment;
            }
        }

        $outcome = DB::transaction(fn () => $this->attemptPayment($billingId, $card, $idempotencyKey, $correlationId));

        return match ($outcome['type']) {
            'not_found' => throw new BillingNotFoundException,
            'already_paid' => throw new BillingAlreadyPaidException,
            'gateway_error' => throw new PaymentGatewayException,
            'declined' => throw new PaymentDeclinedException,
            'success' => $outcome['payment'],
        };
    }

    private function attemptPayment(string $billingId, CreditCardData $card, ?string $idempotencyKey, string $correlationId): array
    {
        $billing = Billing::lockForUpdate()->find($billingId);

        if (! $billing) {
            Log::channel('payments')->warning('billing.payment.rejected', [
                'billing_id' => (int) $billingId,
                'correlation_id' => $correlationId,
                'reason' => 'not_found',
            ]);

            return ['type' => 'not_found'];
        }

        if ($billing->status === BillingStatus::Paid->value) {
            Log::channel('payments')->warning('billing.payment.rejected', [
                'billing_id' => $billing->id,
                'correlation_id' => $correlationId,
                'asaas_charge_id' => $billing->asaas_charge_id,
                'reason' => 'already_paid',
            ]);

            return ['type' => 'already_paid'];
        }

        if (! config('services.asaas.key')) {
            Log::channel('payments')->error('billing.payment.failed', [
                'billing_id' => $billing->id,
                'correlation_id' => $correlationId,
            ]);

            return ['type' => 'gateway_error'];
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
                    'holderName' => $card->holderName,
                    'number' => $card->number,
                    'expiryMonth' => $card->expiryMonth,
                    'expiryYear' => $card->expiryYear,
                    'cvv' => $card->cvv,
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

            return ['type' => 'gateway_error'];
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

            return ['type' => 'gateway_error'];
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

            return ['type' => 'declined'];
        }

        $creditCard = CreditCard::create([
            'customer_id' => $billing->customer_id,
            'card_holder_name' => $card->holderName,
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

        $billing->status = ($isConfirmed ? BillingStatus::Paid : BillingStatus::Pending)->value;
        $billing->save();

        Log::channel('payments')->info($isConfirmed ? 'billing.payment.confirmed' : 'billing.payment.pending', [
            'billing_id' => $billing->id,
            'correlation_id' => $correlationId,
            'asaas_charge_id' => $chargeId,
            'status' => $chargeResult['status'],
            'http_status' => $chargeResult['httpStatus'],
            'duration_ms' => $asaasDurationMs,
        ]);

        return ['type' => 'success', 'payment' => $payment];
    }
}
