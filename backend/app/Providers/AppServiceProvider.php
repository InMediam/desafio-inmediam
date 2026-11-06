<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\Asaas\AsaasGateway;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => new AsaasGateway(
            apiKey: (string) config('services.asaas.key'),
            baseUrl: (string) config('services.asaas.base_url'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();
    }
}
