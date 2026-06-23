<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Payment;
use App\Services\AsaasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Exception;

class BillingController
{
    protected AsaasService $asaasService;
    protected array $statuses = ['CONFIRMED', 'RECEIVED'];

    public function __construct(AsaasService $asaasService)
    {
        $this->asaasService = $asaasService;
    }

    public function index(): JsonResponse
    {
        $billings = Billing::with(['customer', 'plan'])->orderBy('id', 'desc')->get();
        return response()->json($billings);
    }

    public function show(string $id): JsonResponse
    {
        $billing = Billing::with(['plan', 'customer', 'payments.creditCard'])->find($id);

        if (!$billing) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        return response()->json($billing);
    }

    public function pay(string $id, PaymentRequest $request): JsonResponse
    {
        // 1. Resgata a cobrança com Eager Loading para prevenir problemas de N+1
        $billing = Billing::with(['plan', 'customer'])->find($id);

        if (!$billing) {
            return response()->json(['error' => 'Cobrança não encontrada'], 404);
        }

        // 2. Proteção contra dupla cobrança
        if ($billing->status === 'paid') {
            return response()->json(['error' => 'Esta cobrança já foi devidamente paga.'], 400);
        }

        // 3. Blindagem de lógica financeira: O valor vem estritamente do banco de dados, nunca do request
        $amountToCharge = (float) $billing->plan->price;

        try {
            // Inicia uma transação no banco de dados local para manter a integridade dos dados
            $payment = DB::transaction(function () use ($billing, $request, $amountToCharge) {
                
                // Chamada externa 1: Resolve o cliente
                $asaasCustomerId = $this->asaasService->findOrCreateCustomer($billing->customer);

                // Chamada externa 2: Cria a intenção de cobrança
                $asaasCharge = $this->asaasService->createCharge(
                    $asaasCustomerId, 
                    $amountToCharge, 
                    $billing->due_date
                );

                // Chamada externa 3: Processa o pagamento do cartão de crédito
                $paymentResponse = $this->asaasService->tokenizeAndPay(
                    $asaasCharge['id'], 
                    $request->validated(), 
                    $billing->customer
                );

                // 4. Verificação estrita do status retornado pelo gateway antes de validar no sistema
                if (!in_array($paymentResponse['status'], $this->statuses)) {
                    throw new Exception('O pagamento não foi autorizado pelo gateway.');
                }

                // Salva os dados do cartão 
                $creditCard = CreditCard::create([
                    'customer_id'      => $billing->customer_id,
                    'card_holder_name' => $request->card_holder_name,
                    'card_last_four'   => $paymentResponse['creditCard']['creditCardNumber'] ?? substr($request->card_number, -4),
                    'card_brand'       => $paymentResponse['creditCard']['creditCardBrand'] ?? 'UNKNOWN',
                    'card_token'       => $paymentResponse['creditCard']['creditCardToken'] ?? null,
                ]);

                // Registra o log do pagamento interno
                $paymentRecord = Payment::create([
                    'billing_id'     => $billing->id,
                    'credit_card_id' => $creditCard->id,
                    'amount_paid'    => $amountToCharge,
                    'status'         => $paymentResponse['status'],
                    'paid_at'        => now(),
                ]);

                // Atualiza o status da fatura principal
                $billing->update(['status' => 'paid']);

                return $paymentRecord;
            });

            return response()->json([
                'success' => true,
                'message' => 'Pagamento processado com sucesso!',
                'data' => $payment
            ]);

        } catch (Exception $e) {
            // Se qualquer operação falhar dentro do bloco try, o banco sofre Rollback automático
            return response()->json([
                'error' => 'Falha no processamento do pagamento',
                'details' => $e->getMessage()
            ], 422);
        }
    }
}