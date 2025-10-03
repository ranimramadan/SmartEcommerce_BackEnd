<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'order'    => \App\Models\Order::class,
            'shipment' => \App\Models\Shipment::class,
            'admin'    => \App\Models\User::class, // لو بدك تستخدميه كمرجع لتعديل يدوي
        ]);
    }
}
