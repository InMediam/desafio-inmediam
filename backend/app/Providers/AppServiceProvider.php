<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Services\AsaasService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $gateway = config('payment.gateway', 'asaas');
            return match ($gateway) {
                'asaas' => $app->make(AsaasService::class),
                default => throw new \InvalidArgumentException("Gateway de pagamento não suportado: {$gateway}"),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
