<?php

namespace App\Services\Webhooks;

use App\Contracts\WebhookHandlerInterface;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsaasWebhookHandler implements WebhookHandlerInterface
{
    public function getName(): string
    {
        return 'asaas';
    }

    public function validate(Request $request): bool
    {
        $token = $request->header('asaas-access-token');
        $expectedToken = config('services.asaas.webhook_token');

        if ($expectedToken !== null && $expectedToken !== '' && $token !== $expectedToken) {
            Log::warning('Asaas webhook: token inválido ou ausente');
            return false;
        }

        return true;
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $event = $payload['event'] ?? null;
        $paymentData = $payload['payment'] ?? null;
        $paymentId = is_array($paymentData) ? ($paymentData['id'] ?? null) : null;

        if (!$event || !$paymentId) {
            Log::info('Asaas webhook: evento ou payment.id ausente', ['payload' => $payload]);
            return response()->json(['message' => 'ok']);
        }

        if (!in_array($event, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'], true)) {
            return response()->json(['message' => 'ok']);
        }

        $payment = Payment::where('asaas_payment_id', $paymentId)->first();
        if (!$payment) {
            Log::info('Asaas webhook: pagamento local não encontrado', [
                'event' => $event,
                'asaas_payment_id' => $paymentId,
            ]);
            return response()->json(['message' => 'ok']);
        }

        $payment->status = $event === 'PAYMENT_RECEIVED' ? 'RECEIVED' : 'CONFIRMED';
        $payment->save();

        $billing = $payment->billing;
        if ($billing && $billing->status !== 'paid') {
            $billing->status = 'paid';
            $billing->save();
        }

        Log::info('Asaas webhook: pagamento e cobrança atualizados', [
            'event' => $event,
            'payment_id' => $payment->id,
            'billing_id' => $billing?->id,
        ]);

        return response()->json(['message' => 'ok']);
    }
}
