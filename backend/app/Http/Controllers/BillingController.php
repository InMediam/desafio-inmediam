<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Services\BillingService;
use App\Http\Requests\PayBillingRequest;
use Illuminate\Http\JsonResponse;

/**
 * Responsável por expor endpoints de cobrança.
 * Mantém responsabilidade fina e delega regras de negócio ao BillingService.
 */
class BillingController
{
    public function __construct(
        protected BillingService $billingService
    ) {}

    /**
     * Exibe os detalhes de uma cobrança.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $billing = Billing::with(['plan', 'payments.creditCard'])
            ->findOrFail($id);

        return response()->json($billing);
    }

    /**
     * Processa o pagamento de uma cobrança.
     *
     * @param string $id
     * @param PayBillingRequest $request
     * @return JsonResponse
     */
    public function pay(string $id, PayBillingRequest $request): JsonResponse
    {
        $billing = Billing::findOrFail($id);

        $payment = $this->billingService->pay(
            $billing,
            $request->validated()
        );

        return response()->json($payment);
    }
}