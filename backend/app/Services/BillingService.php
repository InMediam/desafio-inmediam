<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orquestra o fluxo de pagamento de cobranças com segurança contra duplicidade,
 * consistência transacional e tratamento de falhas externas.
 */
class BillingService
{
    public function __construct(
        protected AsaasService $asaas
    ) {}

    /**
     * Processa pagamento de uma cobrança via cartão de crédito.
     *
     * @param Billing $billing
     * @param array $data
     * @return Payment
     * @throws Throwable
     */
    public function pay(Billing $billing, array $data): Payment
    {
        $lockKey = "billing-pay-{$billing->id}";

        return Cache::lock($lockKey, 10)->block(5, function () use ($billing, $data) {

            return DB::transaction(function () use ($billing, $data) {

                $billing = Billing::where('id', $billing->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($billing->isPaid()) {
                    return $billing->payments()->latest()->first();
                }

                $billing->markAsProcessing();

                try {
                    $customer = $this->asaas->createCustomer([
                        'name' => $billing->customer->name,
                        'email' => $billing->customer->email,
                        'cpfCnpj' => $billing->customer->document,
                        'notificationDisabled' => true,
                    ]);

                    $charge = $this->asaas->createPayment([
                        'customer' => $customer['id'],
                        'billingType' => 'CREDIT_CARD',
                        'value' => $billing->amount,
                        'dueDate' => $billing->due_date,
                    ]);

                    $paymentResponse = $this->asaas->payCreditCard(
                        $charge['id'],
                        [
                            'creditCard' => [
                                'holderName' => $data['card_holder_name'],
                                'number' => $data['card_number'],
                                'expiryMonth' => explode('/', $data['expiry_date'])[0],
                                'expiryYear' => '20' . explode('/', $data['expiry_date'])[1],
                                'ccv' => $data['cvv'],
                            ],
                            'creditCardHolderInfo' => [
                                'name' => $billing->customer->name,
                                'email' => $billing->customer->email,
                                'cpfCnpj' => $billing->customer->document,
                                'phone' => '0000000000',
                                'postalCode' => '00000000',
                                'addressNumber' => '0',
                            ],
                        ]
                    );

                    $creditCard = CreditCard::create([
                        'customer_id' => $billing->customer_id,
                        'card_holder_name' => $data['card_holder_name'],
                        'card_last_four' => $paymentResponse['creditCard']['creditCardNumber'] ?? null,
                        'card_brand' => $paymentResponse['creditCard']['creditCardBrand'] ?? null,
                        'card_token' => $paymentResponse['creditCard']['creditCardToken'] ?? null,
                    ]);

                    $payment = Payment::create([
                        'billing_id' => $billing->id,
                        'credit_card_id' => $creditCard->id,
                        'amount_paid' => $paymentResponse['value'] ?? $billing->amount,
                        'status' => $paymentResponse['status'] ?? 'unknown',
                        'paid_at' => now(),
                    ]);

                    $billing->markAsPaid();

                    return $payment;

                } catch (Throwable $e) {

                    $billing->markAsFailed();

                    Log::error('PAYMENT_FAILED', [
                        'billing_id' => $billing->id,
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
                }
            });
        });
    }
}