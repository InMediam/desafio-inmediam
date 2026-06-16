<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayBillingRequest;
use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Payment;
use App\Services\AsaasService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class BillingController
{
    private AsaasService $asaasService;

    public function __construct(AsaasService $asaasService)
    {
        $this->asaasService = $asaasService;
    }

    public function show(Billing $billing): JsonResponse
    {
        $billing->load([
            'plan',
            'payments' => fn ($query) => $query->latest('id'),
            'payments.creditCard',
        ]);

        return response()->json($billing);
    }

    public function pay(PayBillingRequest $request, Billing $billing): JsonResponse
    {
        $billing->loadMissing('customer');

        if ($billing->status !== 'pending') {
            return response()->json([
                'message' => 'Esta cobrança já foi processada.',
            ], 409);
        }

        try {
            $this->markAsProcessing($billing);

            $processedPayment = $this->persistPayment(
                billing: $billing,
                asaasPayment: $this->asaasService->collectCreditCardPayment($billing->fresh(['customer']), $request->validated()),
                paymentData: $request->validated(),
            );

            return response()->json($processedPayment, 201);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $throwable) {
            $this->markAsPending($billing);
            report($throwable);

            return response()->json([
                'message' => 'Não foi possível processar o pagamento. Tente novamente em instantes.',
            ], 502);
        }
    }

    private function markAsProcessing(Billing $billing): void
    {
        DB::transaction(function () use ($billing): void {
            $lockedBilling = Billing::query()->whereKey($billing->id)->lockForUpdate()->firstOrFail();

            if ($lockedBilling->status !== 'pending') {
                throw new DomainException('Esta cobrança já foi processada.');
            }

            $lockedBilling->update(['status' => 'processing']);
        });
    }

    private function markAsPending(Billing $billing): void
    {
        Billing::query()
            ->whereKey($billing->id)
            ->where('status', 'processing')
            ->update(['status' => 'pending']);
    }

    private function persistPayment(Billing $billing, array $asaasPayment, array $paymentData): Payment
    {
        return DB::transaction(function () use ($billing, $asaasPayment, $paymentData): Payment {
            $lockedBilling = Billing::query()->whereKey($billing->id)->lockForUpdate()->firstOrFail();

            if ($lockedBilling->status === 'paid') {
                throw new DomainException('Esta cobrança já foi processada.');
            }

            $lastFour = substr($paymentData['card_number'], -4);

            $creditCard = CreditCard::create([
                'customer_id' => $lockedBilling->customer_id,
                'card_holder_name' => $paymentData['card_holder_name'],
                'card_last_four' => $lastFour,
                'card_brand' => $asaasPayment['card_brand'],
                'card_token' => $asaasPayment['card_token'],
            ]);

            $payment = Payment::create([
                'billing_id' => $lockedBilling->id,
                'credit_card_id' => $creditCard->id,
                'amount_paid' => $lockedBilling->amount,
                'status' => $asaasPayment['status'],
                'paid_at' => now(),
            ]);

            $lockedBilling->update(['status' => 'paid']);

            return $payment->load('creditCard');
        });
    }
}
