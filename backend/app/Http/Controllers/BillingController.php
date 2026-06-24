<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayBillingRequest;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;

class BillingController
{
    protected BillingService $service;

    public function __construct()
    {
        $this->service = app(BillingService::class);
    }

    public function index(): JsonResponse
    {
        $billings = $this->service->listAll();
        return response()->json($billings);
    }

    public function show(string $id): JsonResponse
    {
        $billing = $this->service->find($id, [], ['plan', 'payments.creditCard']);

        if (!$billing) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        return response()->json($billing);
    }

    public function pay(string $id, PayBillingRequest $request): JsonResponse
    {
        try {
            $result = $this->service->pay($id, $request->all());

            return response()->json($result);

        } catch (\Exception $e) {
            $httpCode = is_int($e->getCode()) && $e->getCode() > 0 ? $e->getCode() : 500;
            return response()->json(['error' => $e->getMessage()], $httpCode);
        }

    }
}
