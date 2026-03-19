<?php

namespace App\Contracts;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface WebhookHandlerInterface
{
    /**
     * Nome do gateway (ex: asaas, stripe) para rota e registro.
     */
    public function getName(): string;

    /**
     * Valida se a requisição é legítima (token, assinatura, etc.).
     * Retorna false para rejeitar com 401.
     */
    public function validate(Request $request): bool;

    /**
     * Processa o payload do webhook e atualiza o estado (pagamento, cobrança).
     * Deve retornar 200 rapidamente; processamento idempotente.
     */
    public function handle(Request $request): JsonResponse;
}
