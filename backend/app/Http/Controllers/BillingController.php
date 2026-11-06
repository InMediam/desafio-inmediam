<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayBillingRequest;
use App\Http\Resources\BillingListResource;
use App\Http\Resources\BillingResource;
use App\Http\Resources\PaymentResource;
use App\Models\Billing;
use App\Services\Billing\BillingPaymentService;
use Illuminate\Http\JsonResponse;

class BillingController
{
    public function __construct(private readonly BillingPaymentService $paymentService) {}

    public function index(): JsonResponse
    {
        $billings = Billing::with(['plan', 'customer'])->orderBy('id')->get();

        return BillingListResource::collection($billings)->response();
    }

    public function show(string $id): JsonResponse
    {
        if (! ctype_digit($id)) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        $billing = Billing::with(['plan', 'customer', 'payments.creditCard'])->find($id);

        if (! $billing) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        return (new BillingResource($billing))->response();
    }

    public function pay(string $id, PayBillingRequest $request): JsonResponse
    {
        $payment = $this->paymentService->pay(
            $id,
            $request->toCreditCardData(),
            $request->header('Idempotency-Key'),
        );

        return (new PaymentResource($payment))->response()->setStatusCode(200);
    }
}
