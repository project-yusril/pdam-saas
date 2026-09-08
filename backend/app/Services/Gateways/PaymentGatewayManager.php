<?php

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use RuntimeException;

/**
 * PaymentGatewayManager — provider gateway (PRD §23: "selain Midtrans").
 *
 * Default provider dibaca dari config('business.payment.default_provider')
 * (env PDAM_PAYMENT_PROVIDERS bisa daftar; default 'midtrans'). Adapter
 * yang dikenal: MidtransSnap + XenditCharge (lihat `providers` di config).
 */
class PaymentGatewayManager
{
    /** @param  array<string, class-string<PaymentGatewayInterface>>  $map */
    public function __construct(private array $map = []) {}

    public function make(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?: config('business.payment.default_provider', 'midtrans');

        $class = $this->map[$name] ?? config("business.payment.providers.$name");
        if (! $class || ! is_string($class)) {
            throw new RuntimeException("Payment gateway {$name} tidak dikonfigurasi.");
        }

        return app($class);
    }

    /** @return list<string> */
    public function allowed(): array
    {
        return array_keys($this->map + config('business.payment.providers', []));
    }
}
