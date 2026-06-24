<?php

namespace App\Services;

use App\Enums\BillingStatus;
use App\Enums\BillingType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Repositories\BillingRepository;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BillingService extends AbstractService
{
    protected AsaasService $asaasService;
    protected CreditCardService $creditCardService;
    protected PaymentService $paymentService;

    public function __construct()
    {
        $this->repository = app(BillingRepository::class);
        $this->asaasService = app(AsaasService::class);
        $this->creditCardService = app(CreditCardService::class);
        $this->paymentService = app(PaymentService::class);
    }

    public function listAll(): Collection
    {
        return $this->repository->allWithRelations();
    }

    public function pay(string $id, array $data): Payment 
    {
        $this->validate($data, [
            'card_holder_name' => 'required|string',
            'card_number' => 'required|string',
            'expiry_date' => 'required|string',
            'cvv' => 'required|string',
            'phone' => 'required|string',
            'postal_code' => 'required|string',
            'address_number' => 'required|string',
        ]);

        $billing = $this->find($id, [], ['plan', 'customer'], ['status' => BillingStatus::PENDING->value]);

        if (!$billing) {
            throw new Exception('Billing not found', 404);
        }

        $payment = $this->paymentService->create([
            'billing_id' => $billing->id,
            'amount_paid' => $billing->plan->price,
            'status' => PaymentStatus::PENDING->value,
            'paid_at' => null,
        ]);

        if (!$payment) {
            throw new Exception('Payment not created', 500);
        }

        try {
            $customer = $this->asaasService->createCustomer([
                'name' => $billing->customer->name,
                'email' => $billing->customer->email,
                'cpfCnpj' => $billing->customer->document,
                'notificationDisabled' => true,
            ]);

            $charge = $this->asaasService->createCharge([
                'customer' => $customer->id,
                'billingType' => BillingType::CREDIT_CARD->value,
                'value' => $billing->plan->price,
                'dueDate' => $billing->due_date,
            ]);

            $response = $this->asaasService->payWithCreditCard($charge->id, [
                'holderName' => $data['card_holder_name'],
                'number' => $data['card_number'],
                'expiryMonth' => explode('/', $data['expiry_date'])[0],
                'expiryYear' => '20' . explode('/', $data['expiry_date'])[1],
                'ccv' => $data['cvv'],
            ],
            [
                'name' => $billing->customer->name,
                'email' => $billing->customer->email,
                'cpfCnpj' => $billing->customer->document,
                'phone' => $data['phone'],
                'postalCode' => $data['postal_code'],
                'addressNumber' => $data['address_number'],
            ]);

            DB::transaction(function () use ($payment, $billing, $response, $data) {
                $credit_card = $this->creditCardService->create([
                    'customer_id' => $billing->customer_id,
                    'card_holder_name' => $data['card_holder_name'],
                    'card_last_four' => $response->creditCard['creditCardNumber'],
                    'card_brand' => $response->creditCard['creditCardBrand'],
                    'card_token' => $response->creditCard['creditCardToken'],
                ]);

                $payment->update([
                    'credit_card_id' => $credit_card->id,
                    'status' => PaymentStatus::CONFIRMED->value,
                    'paid_at' => now(),
                ]);

                $billing->status = BillingStatus::PAID;
                $billing->save();
            });
        } catch (\Exception $e) {
            $payment->update(['status' => PaymentStatus::FAILED]);
            throw $e;
        }

        return $payment;
    }
}
