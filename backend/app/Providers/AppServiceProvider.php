<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Services\Gateways\MidtransSnap;
use App\Services\Gateways\PaymentGatewayManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // PaymentGatewayManager (PRD 23: provider pertama Midtrans, kedua XenditCharge).
        $this->app->singleton(PaymentGatewayManager::class, fn () => new PaymentGatewayManager(
            (array) config('business.payment.providers', [])
        ));

        // Interface default -> provider aktif saat ini (MidtransSnap agar webhook/callback lama tidak berubah).
        $this->app->bind(PaymentGatewayInterface::class, MidtransSnap::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Definisikan rate limiter yang dipakai oleh `throttleApi()` di
     * bootstrap/app.php. Tanpa definisi ini, setiap request API melempar
     * MissingRateLimiterException (HTTP 500).
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(60)->by((string) $key);
        });
    }
}
