<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\PayBillingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class BillingController
{
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
        $billing = Billing::with('customer')->find($id);

        if (!$billing) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        if ($billing->status === 'paid') {
            return response()->json(['error' => 'Cobrança já paga'], 422);
        }

        $apiKey = config('services.asaas.key');
        $baseUrl = config('services.asaas.url');

        try {
            $customerResponse = Http::withHeaders(['access_token' => $apiKey])->post("$baseUrl/customers", [
                'name' => $billing->customer->name,
                'email' => $billing->customer->email,
                'cpfCnpj' => $billing->customer->document,
                'notificationDisabled' => true, // ATENÇÃO: Não recomendo modificar, pois o Asaas envia notificações mesmo no ambiente de sandbox.
            ]);

            if ($customerResponse->failed()) {
                return response()->json(['error' => 'Erro ao registrar cliente no gateway de pagamento'], 502);
            }

            $customer = (object) $customerResponse->json();

            $chargeResponse = Http::withHeaders(['access_token' => $apiKey])->post("$baseUrl/payments", [
                'customer' => $customer->id,
                'billingType' => 'CREDIT_CARD',
                'value' => $request->amount,
                'dueDate' => $billing->due_date,
            ]);

            if ($chargeResponse->failed()) {
                return response()->json(['error' => 'Erro ao criar cobrança no gateway de pagamento'], 502);
            }

            $charge = (object) $chargeResponse->json();

            $payResponse = Http::withHeaders(['access_token' => $apiKey])->post("$baseUrl/payments/{$charge->id}/payWithCreditCard", [
                'creditCard' => [
                    'holderName' => $request->card_holder_name,
                    'number' => $request->card_number,
                    'expiryMonth' => explode('/', $request->expiry_date)[0],
                    'expiryYear' => '20' . explode('/', $request->expiry_date)[1],
                    'ccv' => $request->cvv,
                ],
                'creditCardHolderInfo' => [
                    'name' => $billing->customer->name,
                    'email' => $billing->customer->email,
                    'cpfCnpj' => $billing->customer->document,
                    'phone' => $request->phone,
                    'postalCode' => $request->postal_code,
                    'addressNumber' => $request->address_number,
                ],
                'remoteIp' => $request->ip(),
            ]);

            if ($payResponse->failed()) {
                return response()->json(['error' => 'Pagamento recusado pelo gateway de pagamento'], 422);
            }

            $response = (object) $payResponse->json();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Falha na comunicação com o gateway de pagamento'], 502);
        }

        $payment = DB::transaction(function () use ($billing, $request, $response) {
            $credit_card = CreditCard::create([
                'customer_id' => $billing->customer_id,
                'card_holder_name' => $request->card_holder_name,
                'card_last_four' => substr($response->creditCard['creditCardNumber'] ?? '', -4),
                'card_brand' => $response->creditCard['creditCardBrand'],
                'card_token' => $response->creditCard['creditCardToken'],
            ]);

            $payment = Payment::create([
                'billing_id' => $billing->id,
                'credit_card_id' => $credit_card->id,
                'amount_paid' => $request->amount,
                'status' => $response->status,
                'paid_at' => now(),
            ]);

            $billing->status = 'paid';
            $billing->save();

            return $payment;
        });

        return response()->json($payment);
    }
}
