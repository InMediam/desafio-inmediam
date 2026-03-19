<?php

namespace App\Http\Controllers;

use App\Contracts\WebhookHandlerInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController
{
    /**
     * Delega o webhook para o handler do gateway (Strategy).
     * Rota: POST /api/webhooks/{gateway} (ex: /api/webhooks/asaas).
     */
    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        $handlers = config('payment.webhook_handlers', []);
        $handlerClass = $handlers[$gateway] ?? null;

        if (!$handlerClass || !is_subclass_of($handlerClass, WebhookHandlerInterface::class)) {
            Log::warning('Webhook: gateway não registrado', ['gateway' => $gateway]);
            return response()->json(['error' => 'Gateway não suportado'], 404);
        }

        /** @var WebhookHandlerInterface $handler */
        $handler = app($handlerClass);

        if (!$handler->validate($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $handler->handle($request);
    }
}
