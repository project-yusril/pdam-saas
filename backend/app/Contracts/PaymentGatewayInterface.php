<?php

namespace App\Contracts;

use App\Models\Payment;

interface PaymentGatewayInterface
{
    public function createTransaction(Payment $payment, array $customerInfo = []): array;

    public function getStatus(string $orderId): array;

    public function checkSignature(array $data): bool;

    public function getAvailableChannels(): array;
}
