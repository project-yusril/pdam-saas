<?php

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;

/**
 * XenditCharge — provider pembayaran KEDUA (PRD §23; adapter PaymentGatewayInterface).
 * Aktif lewat env PDAM_PAYMENT_PROVIDER=xendit atau field payment saat create.
 *
 * Kontrak: `createTransaction` mengembalikan array dgn `token` + `redirect_url`;
 * `checkSignature` memverifikasi token callback Xendit (shared secret);
 * `getStatus` query payment intent.
 */
class XenditCharge implements PaymentGatewayInterface
{
    private string $secretKey;

    private string $webhookToken;

    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = (string) config('services.xendit.secret_key', '');
        $this->webhookToken = (string) config('services.xendit.webhook_token', '');
        $prod = filter_var(config('services.xendit.is_production', false), FILTER_VALIDATE_BOOLEAN);
        $this->baseUrl = $prod ? 'https://api.xendit.co' : 'https://api.xendit.co'; // Xendit tidak sandbox url berbeda; secret key menentukan mode
    }

    public function createTransaction(Payment $payment, array $customerInfo = []): array
    {
        if ($this->secretKey === '') {
            throw new \RuntimeException('Xendit secret_key belum dikonfigurasi.');
        }

        // Create Payment Method (ewallet) -> lalu Payment Intent (QRIS/OVO/DANA dst.).
        $pm = Http::withBasicAuth($this->secretKey, '')
            ->post($this->baseUrl.'/payment_methods', [
                'type' => 'ewallet',
                'customer_details' => [
                    'given_names' => $customerInfo['name'] ?? 'Pelanggan',
                    'email' => $customerInfo['email'] ?? null,
                ],
                'properties' => ['channel' => 'WECHAT_PAY'],
            ]);

        if (! $pm->successful()) {
            throw new \RuntimeException('Xendit payment_methods error: '.($pm->json('error_code') ?? $pm->body()));
        }

        $intent = Http::withBasicAuth($this->secretKey, '')->post($this->baseUrl.'/payment_intents', [
            'amount' => (int) $payment->amount,
            'payment_method_ids' => ['dummy'],
            'capture_method' => 'Automatic',
            'success_redirect_url' => config('app.frontend_url', config('app.url')).'/payment/success?reference='.$payment->payment_number,
            'failure_redirect_url' => config('app.frontend_url', config('app.url')).'/payment/fail?reference='.$payment->payment_number,
            'metadata' => ['reference' => $payment->payment_number, 'external_id' => $payment->midtrans_order_id],
        ]);
        $intent->failed() && throw new \RuntimeException('Xendit intent error: '.($intent->json('error_code') ?? $intent->body()));

        $id = (string) $intent->json('id', '');

        return [
            'token' => $id,
            'redirect_url' => $intent->json('actions.0.url'),
            'payment_method_id' => $pm->json('id'),
        ];
    }

    public function getStatus(string $orderId): array
    {
        $res = Http::withBasicAuth($this->secretKey, '')->get($this->baseUrl.'/payment_intents/'.$orderId);

        return ['transaction_status' => $res->json('status'), 'raw' => $res->json()];
    }

    public function checkSignature(array $data): bool
    {
        $token = $data['callback_token'] ?? ($data['X_CALLBACK_TOKEN'] ?? null);
        if ($token === null || $this->webhookToken === '') {
            return false;
        }

        return hash_equals($this->webhookToken, (string) $token);
    }

    public function getAvailableChannels(): array
    {
        return ['QRIS', 'OVO', 'DANA', 'LINKAJA', 'SHOPEEPAY'];
    }
}
