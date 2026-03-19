<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway de pagamento padrão
    |--------------------------------------------------------------------------
    |
    | Define qual implementação de PaymentGatewayInterface será usada.
    | Valores possíveis: 'asaas'. Novos gateways (ex: stripe, pagarme) podem
    | ser adicionados em app/Services/Gateways e registrados aqui.
    |
    */
    'gateway' => env('PAYMENT_GATEWAY', 'asaas'),

    /*
    |--------------------------------------------------------------------------
    | Handlers de webhook por gateway
    |--------------------------------------------------------------------------
    |
    | Mapeia o nome do gateway (segmento da URL /api/webhooks/{gateway}) para
    | a classe que implementa WebhookHandlerInterface.
    |
    */
    'webhook_handlers' => [
        'asaas' => \App\Services\Webhooks\AsaasWebhookHandler::class,
    ],

];
