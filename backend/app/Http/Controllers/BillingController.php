<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayBillingRequest;
use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Payment;
use App\Services\AsaasPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class BillingController
{
    public function show(string $id): JsonResponse
    {
        $billing = Billing::with(['customer', 'plan', 'payments.creditCard'])
            ->find($id);

        if (!$billing) {
            return response()->json(['message' => 'Cobrança não encontrada.'], 404);
        }

        return response()->json($billing);
    }

    public function pay(string $id, PayBillingRequest $request, AsaasPaymentService $asaasPaymentService): JsonResponse
    {
        $billing = Billing::with(['customer', 'plan'])->find($id);

        if (!$billing) {
            return response()->json(['message' => 'Cobrança não encontrada.'], 404);
        }

        if ($billing->status !== Billing::STATUS_PENDING) {
            return response()->json(['message' => 'Esta cobrança já foi paga ou não está disponível para pagamento.'], 409);
        }

        if (abs((float) $request->validated('amount') - (float) $billing->amount) > 0.01) {
            return response()->json(['message' => 'O valor informado não corresponde ao valor da cobrança.'], 422);
        }

        [$expiryMonth, $expiryYear] = explode('/', $request->validated('expiry_date'));

        try {
            $payment = DB::transaction(function () use ($billing, $request, $asaasPaymentService, $expiryMonth, $expiryYear) {
                $lockedBilling = Billing::query()
                    ->whereKey($billing->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedBilling->status !== Billing::STATUS_PENDING) {
                    throw new RuntimeException('Esta cobrança já foi processada.');
                }

                $asaasResponse = $asaasPaymentService->payWithCreditCard($lockedBilling->load(['customer', 'plan']), [
                    'card_holder_name' => $request->validated('card_holder_name'),
                    'card_number' => $request->validated('card_number'),
                    'expiry_month' => $expiryMonth,
                    'expiry_year' => '20'.$expiryYear,
                    'cvv' => $request->validated('cvv'),
                ]);

                $approvedStatuses = ['CONFIRMED', 'RECEIVED'];

                if (!in_array($asaasResponse['status'] ?? null, $approvedStatuses, true)) {
                    throw new RuntimeException('Pagamento recusado ou não confirmado pelo Asaas.');
                }

                $creditCardInfo = $asaasResponse['creditCard'] ?? [];
                $cardLastFour = substr(preg_replace('/\D/', '', (string) $request->validated('card_number')), -4);

                $creditCard = CreditCard::create([
                    'customer_id' => $lockedBilling->customer_id,
                    'card_holder_name' => $request->validated('card_holder_name'),
                    'card_last_four' => $cardLastFour,
                    'card_brand' => $creditCardInfo['creditCardBrand'] ?? 'UNKNOWN',
                    'card_token' => $creditCardInfo['creditCardToken'] ?? null,
                ]);

                $payment = Payment::create([
                    'billing_id' => $lockedBilling->id,
                    'credit_card_id' => $creditCard->id,
                    'amount_paid' => $lockedBilling->amount,
                    'status' => $asaasResponse['status'] ?? Payment::STATUS_CONFIRMED,
                    'paid_at' => now(),
                ]);

                $lockedBilling->update(['status' => Billing::STATUS_PAID]);

                return $payment->load('creditCard');
            });
        } catch (RuntimeException $exception) {
            Log::warning('Falha controlada ao processar pagamento.', ['billing_id' => $id, 'message' => $exception->getMessage()]);

            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Log::error('Erro inesperado ao processar pagamento.', ['billing_id' => $id, 'exception' => $exception]);

            return response()->json(['message' => 'Não foi possível processar o pagamento. Tente novamente.'], 500);
        }

        return response()->json($payment, 201);
    }
}
