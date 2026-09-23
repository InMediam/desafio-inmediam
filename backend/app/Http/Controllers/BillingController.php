<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Http\Requests\PayBillingRequest;
use App\Http\Resources\BillingResource;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Services\BillingPaymentService;
use App\Services\BillingService;

class BillingController
{
    public function __construct(
        private BillingPaymentService $billingPaymentService,
        private BillingService $billingService
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        $data = $this->billingService->paginate();

        return BillingResource::collection($data);
    }

    public function show(Billing $billing): BillingResource
    {
        $billing->load('plan', 'customer', 'payments.creditCard');

        return new BillingResource($billing);
    }

    public function pay(Billing $billing, PayBillingRequest $request): PaymentResource
    {
        $data = $request->validated();

        $payment = $this->billingPaymentService->pay($billing, $data);

        return new PaymentResource($payment);
    }
}
