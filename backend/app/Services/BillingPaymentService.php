<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BillingPaymentService
{
    public function __construct(
        private AsaasService $asaasService
    ) {
    }

    public function pay(Billing $billing, array $data)
    {

        if ($billing->status === 'paid') {
            throw new HttpException(
                409,
                'Pagamento já foi concluído'
            );
        }

        $customer = $billing->customer;

        $asaasCustomerId = $this->asaasService->getOrCreateCustomer($customer);

        $response = $this->asaasService->chargeCreditCard(
            [
                'customer' => $asaasCustomerId,
                'billingType' => 'CREDIT_CARD',
                'value' => $billing->amount,
                'dueDate' => $billing->due_date,
            ],
            [
                'creditCard' => [
                    'holderName' => $data['card_holder_name'],
                    'number' => $data['card_number'],
                    'expiryMonth' => substr($data['expiry_date'], 0, 2),
                    'expiryYear' => '20' . substr($data['expiry_date'], 3, 2),
                    'ccv' => $data['cvv'],
                ],
                'creditCardHolderInfo' => [
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'cpfCnpj' => $customer->document,
                    'phone' => $customer->phone,
                    'postalCode' => $customer->postal_code,
                    'addressNumber' => $customer->address_number,
                ],
            ]
        );

        $responseData = $response->json();

        $cardToken = $responseData['creditCard']['creditCardToken'];

        $paymentStatus = $responseData['status'];

        if ($paymentStatus !== 'CONFIRMED') {
            throw new HttpException(
                422,
                'Pagamento não concluído'
            );
        }

        $credit_card = CreditCard::firstOrCreate(
            [
                'customer_id' => $customer->id,
                'card_token' => $cardToken,
            ],
            [
                'card_holder_name' => $data['card_holder_name'],
                'card_last_four' => $responseData['creditCard']['creditCardNumber'],
                'card_brand' => $responseData['creditCard']['creditCardBrand'],
            ]
        );

        // Registra o pagamento e atualiza a cobrança de forma atômica no banco local.
        $payment = DB::transaction(function () use ($billing, $credit_card, $paymentStatus) {
            $payment = Payment::create([
                'billing_id' => $billing->id,
                'credit_card_id' => $credit_card->id,
                'amount_paid' => $billing->amount,
                'status' => $paymentStatus,
                'paid_at' => now(),
            ]);

            $billing->status = 'paid';
            $billing->save();

            return $payment;
        });

        return $payment;
    }
}