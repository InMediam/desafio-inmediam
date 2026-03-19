<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayBillingRequest;
use App\Http\Requests\UpdateCustomerAddressRequest;
use App\Models\Billing;
use App\Services\BillingPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class BillingController
{
    public function __construct(
        private BillingPaymentService $billingPaymentService
    ) {
    }

    public function index(): JsonResponse
    {
        $billings = Billing::with(['plan', 'customer'])
            ->orderBy('due_date')
            ->get();

        return response()->json($billings);
    }

    public function show(string $id): JsonResponse
    {
        if (!Str::isUuid($id)) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        $billing = Billing::with(['plan', 'customer', 'payments.creditCard'])->where('uuid', $id)->first();

        if (!$billing) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        return response()->json($billing);
    }

    public function pay(PayBillingRequest $request, string $id): JsonResponse
    {
        if (!Str::isUuid($id)) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        $billing = Billing::with('customer')->where('uuid', $id)->first();

        if (!$billing) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        if ($billing->status === 'paid') {
            return response()->json([
                'message' => 'Esta cobrança já foi paga.',
            ], 409);
        }

        $result = $this->billingPaymentService->processPayment($billing, $request, $request->ip());

        if (!$result['ok']) {
            return response()->json(
                ['message' => $result['message']],
                $result['httpStatus']
            );
        }

        return response()->json($result['payment']);
    }

    public function updateCustomerAddress(UpdateCustomerAddressRequest $request, string $id): JsonResponse
    {
        if (!Str::isUuid($id)) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        $billing = Billing::with('customer')->where('uuid', $id)->first();

        if (!$billing || !$billing->customer) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        $billing->customer->update($request->validated());

        return response()->json(['message' => 'Endereço salvo com sucesso.', 'customer' => $billing->customer->fresh()]);
    }
}
