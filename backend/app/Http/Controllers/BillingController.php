<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayBillingRequest;
use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Payment;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BillingController
{
    private const ASAAS_CONFIRMED_STATUSES = ['CONFIRMED', 'RECEIVED'];

    public function index(): JsonResponse
    {
        $billings = Billing::with(['plan', 'customer'])
            ->orderBy('id')
            ->get();

        return response()->json($billings);
    }

    public function show(string $id): JsonResponse
    {
        $billing = Billing::with(['plan', 'payments.creditCard'])->find($id);

        if (!$billing) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        return response()->json($billing);
    }

    public function pay(string $id, PayBillingRequest $request): JsonResponse
    {
        $apiKey = config('services.asaas.api_key');

        if (empty($apiKey)) {
            Log::error('billing.payment.misconfigured', [
                'billing_id' => $id,
            ]);

            return response()->json(['error' => 'Integração com Asaas não configurada.'], 503);
        }

        $baseUrl = config('services.asaas.base_url');
        $expiry = $request->expiryParts();

        return DB::transaction(function () use ($id, $request, $apiKey, $baseUrl, $expiry) {
            $billing = Billing::with('customer')->lockForUpdate()->find($id);

            if (!$billing) {
                Log::warning('billing.payment.not_found', [
                    'billing_id' => $id,
                ]);

                return response()->json(['error' => 'Cobrança não encontrada'], 404);
            }

            if (!$billing->isPending()) {
                Log::info('billing.payment.skipped', [
                    'billing_id' => $billing->id,
                    'status' => $billing->status,
                ]);

                return response()->json([
                    'error' => 'Esta cobrança já foi paga ou não está disponível para pagamento.',
                ], 422);
            }

            Log::info('billing.payment.started', [
                'billing_id' => $billing->id,
                'amount' => $billing->amount,
            ]);

            $customerResponse = Http::withHeaders(['access_token' => $apiKey])
                ->timeout(30)
                ->post("$baseUrl/customers", [
                    'name' => $billing->customer->name,
                    'email' => $billing->customer->email,
                    'cpfCnpj' => $billing->customer->document,
                    'notificationDisabled' => true,
                ]);

            if ($asaasError = $this->asaasErrorResponse($customerResponse, 'create_customer', $billing->id)) {
                return $asaasError;
            }

            $customer = (object) $customerResponse->json();

            $chargeResponse = Http::withHeaders(['access_token' => $apiKey])
                ->timeout(30)
                ->post("$baseUrl/payments", [
                    'customer' => $customer->id,
                    'billingType' => 'CREDIT_CARD',
                    'value' => $billing->amount,
                    'dueDate' => $billing->due_date,
                ]);

            if ($asaasError = $this->asaasErrorResponse($chargeResponse, 'create_charge', $billing->id)) {
                return $asaasError;
            }

            $charge = (object) $chargeResponse->json();

            $paymentResponse = Http::withHeaders(['access_token' => $apiKey])
                ->timeout(30)
                ->post("$baseUrl/payments/{$charge->id}/payWithCreditCard", [
                    'creditCard' => [
                        'holderName' => $request->cardHolderName(),
                        'number' => $request->validated('card_number'),
                        'expiryMonth' => $expiry['month'],
                        'expiryYear' => $expiry['year'],
                        'ccv' => $request->validated('cvv'),
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

            if ($asaasError = $this->asaasErrorResponse($paymentResponse, 'pay_with_credit_card', $billing->id)) {
                return $asaasError;
            }

            $response = (object) $paymentResponse->json();

            if (!in_array($response->status ?? '', self::ASAAS_CONFIRMED_STATUSES, true)) {
                Log::warning('billing.payment.gateway_not_confirmed', [
                    'billing_id' => $billing->id,
                    'asaas_charge_id' => $charge->id ?? null,
                    'asaas_status' => $response->status ?? 'unknown',
                ]);

                return response()->json([
                    'error' => 'Pagamento não confirmado pelo gateway.',
                ], 422);
            }

            $creditCard = CreditCard::create([
                'customer_id' => $billing->customer_id,
                'card_holder_name' => $request->cardHolderName(),
                'card_last_four' => $this->extractLastFour($response->creditCard['creditCardNumber'] ?? ''),
                'card_brand' => $response->creditCard['creditCardBrand'],
                'card_token' => $response->creditCard['creditCardToken'],
            ]);

            $payment = Payment::create([
                'billing_id' => $billing->id,
                'credit_card_id' => $creditCard->id,
                'amount_paid' => $billing->amount,
                'status' => $response->status,
                'paid_at' => now(),
            ]);

            $billing->status = Billing::STATUS_PAID;
            $billing->save();

            Log::info('billing.payment.confirmed', [
                'billing_id' => $billing->id,
                'payment_id' => $payment->id,
                'amount' => $billing->amount,
                'asaas_status' => $response->status,
            ]);

            return response()->json($payment);
        });
    }

    private function extractLastFour(string $maskedNumber): string
    {
        $digits = preg_replace('/\D/', '', $maskedNumber);

        return substr($digits, -4);
    }

    private function asaasErrorResponse(Response $response, string $step, int $billingId): ?JsonResponse
    {
        if ($response->successful()) {
            return null;
        }

        $errors = $response->json('errors');
        $message = is_array($errors) && isset($errors[0]['description'])
            ? $errors[0]['description']
            : 'Erro ao processar pagamento no Asaas';

        Log::warning('billing.payment.asaas_failed', [
            'billing_id' => $billingId,
            'step' => $step,
            'http_status' => $response->status(),
            'error' => $message,
        ]);

        return response()->json(['error' => $message], 422);
    }
}
