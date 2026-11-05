<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

    public function pay(string $id, Request $request): JsonResponse
    {
        if (! ctype_digit($id)) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        $billing = Billing::find($id);

        if (! $billing) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        if ($billing->status === 'paid') {
            return response()->json(['error' => 'Cobrança já paga'], 409);
        }

        $apiKey = '$aact_YourSandboxKeyHere';
        $baseUrl = 'https://sandbox.asaas.com/api/v3';

        try {
            $customer = Http::timeout(10)->withHeaders(['access_token' => $apiKey])->post("$baseUrl/customers", [
                'name' => $billing->customer->name,
                'email' => $billing->customer->email,
                'cpfCnpj' => $billing->customer->document,
                'notificationDisabled' => true, // ATENÇÃO: Não recomendo modificar, pois o Asaas envia notificações mesmo no ambiente de sandbox.
            ]);

            $customer = (object) $customer->json();

            $charge = Http::timeout(10)->withHeaders(['access_token' => $apiKey])->post("$baseUrl/payments", [
                'customer' => $customer->id,
                'billingType' => 'CREDIT_CARD',
                'value' => $billing->amount,
                'dueDate' => $billing->due_date,
            ]);

            $charge = (object) $charge->json();

            $response = Http::timeout(10)->withHeaders(['access_token' => $apiKey])->post("$baseUrl/payments/{$charge->id}/payWithCreditCard", [
                'creditCard' => [
                    'holderName' => $request->card_holder_name,
                    'number' => $request->card_number,
                    'expiryMonth' => explode('/', $request->expiry_date)[0],
                    'expiryYear' => '20'.explode('/', $request->expiry_date)[1],
                    'ccv' => $request->cvv,
                ],
                'creditCardHolderInfo' => [
                    'name' => $billing->customer->name,
                    'email' => $billing->customer->email,
                    'cpfCnpj' => $billing->customer->document,
                    'phone' => '0000000000',
                    'postalCode' => '00000000',
                    'addressNumber' => '0',
                ],
            ]);
        } catch (ConnectionException) {
            return response()->json(['error' => 'Não foi possível processar o pagamento. Tente novamente mais tarde.'], 502);
        }

        if ($response->serverError()) {
            return response()->json(['error' => 'Não foi possível processar o pagamento. Tente novamente mais tarde.'], 502);
        }

        if ($response->clientError()) {
            return response()->json(['error' => 'Pagamento recusado. Verifique os dados do cartão e tente novamente.'], 422);
        }

        $response = (object) $response->json();

        $payment = DB::transaction(function () use ($billing, $request, $response) {
            $credit_card = CreditCard::create([
                'customer_id' => $billing->customer_id,
                'card_holder_name' => $request->card_holder_name,
                'card_last_four' => $response->creditCard['creditCardNumber'],
                'card_brand' => $response->creditCard['creditCardBrand'],
                'card_token' => $response->creditCard['creditCardToken'],
            ]);

            $payment = Payment::create([
                'billing_id' => $billing->id,
                'credit_card_id' => $credit_card->id,
                'amount_paid' => $billing->amount,
                'status' => $response->status,
                'paid_at' => now(),
            ]);

            if (in_array($response->status, ['CONFIRMED', 'RECEIVED'], true)) {
                $billing->status = 'paid';
                $billing->save();
            }

            return $payment;
        });

        return response()->json($payment);
    }
}
