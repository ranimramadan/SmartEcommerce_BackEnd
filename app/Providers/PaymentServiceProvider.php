<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Payment\GatewayManager;
use App\Services\Payment\Gateways\CodGateway;
use App\Services\Payment\Gateways\StripeGateway;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // مدير البوابات كـ singleton
        $this->app->singleton(GatewayManager::class, function () {
            $manager = new GatewayManager();

            // سجّلي البوابات المتوفرة
            $manager->register(new CodGateway());
            $manager->register(new StripeGateway());

            return $manager;
        });
    }

    public function boot(): void
    {
        // ما في شيء حالياً
    }
}
