<?php

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use App\Models\SaasPurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class MidtransSnap implements PaymentGatewayInterface
{
    private string $serverKey;

    private string $clientKey;

    private string $baseUrl;

    private bool $isProduction;

    public function __construct()
    {
        $this->serverKey = (string) config('services.midtrans.server_key', '');
        $this->clientKey = (string) config('services.midtrans.client_key', '');
        $this->isProduction = filter_var(config('services.midtrans.is_production', false), FILTER_VALIDATE_BOOLEAN);
        $this->baseUrl = $this->isProduction
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
    }

    public function createTransaction(Payment $payment, array $customerInfo = []): array
    {
        $transaction = [
            'transaction_details' => [
                'order_id' => $payment->midtrans_order_id,
                'gross_amount' => (int) $payment->amount,
            ],
            'enabled_payments' => $this->getAvailableChannels(),
        ];

        if (! empty($customerInfo)) {
            $transaction['customer_details'] = [
                'first_name' => $customerInfo['name'] ?? 'Pelanggan',
                'email' => $customerInfo['email'] ?? null,
                'phone' => $customerInfo['phone'] ?? null,
            ];
        }

        if ($payment->bill_id) {
            $transaction['item_details'] = [[
                'id' => 'BILL-'.$payment->bill_id,
                'price' => (int) $payment->amount,
                'quantity' => 1,
                'name' => 'Pembayaran Tagihan Air',
            ]];
        } elseif ($payment->prospect_id) {
            $transaction['item_details'] = [[
                'id' => 'INSTALL-'.$payment->prospect_id,
                'price' => (int) $payment->amount,
                'quantity' => 1,
                'name' => 'Biaya Pemasangan Baru',
            ]];
        }

        $token = base64_encode($this->serverKey.':');

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$token,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl.'/snap/v1/transactions', $transaction);

        if (! $response->successful()) {
            throw new \RuntimeException('Midtrans error: '.($response->json('error_messages.0') ?? $response->body()));
        }

        return $response->json();
    }

    public function createSaasTransaction(SaasPurchaseOrder $order, User $user): array
    {
        $transaction = [
            'transaction_details' => [
                'order_id' => $order->order_number,
                'gross_amount' => (int) $order->amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'item_details' => $order->lines->map(fn ($line) => [
                'id' => $line->module_code,
                'price' => (int) $line->amount,
                'quantity' => 1,
                'name' => $line->module_name,
            ])->values()->all(),
            'enabled_payments' => $this->getAvailableChannels(),
        ];

        $token = base64_encode($this->serverKey.':');
        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$token,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl.'/snap/v1/transactions', $transaction);

        if (! $response->successful()) {
            throw new \RuntimeException('Midtrans error: '.($response->json('error_messages.0') ?? $response->body()));
        }

        return $response->json();
    }

    public function getStatus(string $orderId): array
    {
        $token = base64_encode($this->serverKey.':');

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$token,
        ])->get($this->baseUrl.'/v2/'.$orderId.'/status');

        return $response->json();
    }

    public function checkSignature(array $data): bool
    {
        $orderId = $data['order_id'] ?? '';
        $statusCode = $data['status_code'] ?? '';
        $grossAmount = (string) ($data['gross_amount'] ?? '');
        $signature = $data['signature_key'] ?? '';

        $computed = hash('sha512', $orderId.$statusCode.$grossAmount.$this->serverKey);

        return hash_equals($computed, $signature);
    }

    public function getAvailableChannels(): array
    {
        return ['qris', 'bank_transfer', 'echannel', 'bca_klikpay', 'bri_epay', 'cimb_clicks', 'danamon_online', 'gopay', 'shopeepay', 'credit_card', 'indomaret', 'alfamart'];
    }

    public function getSnapToken(Payment $payment, array $customerInfo = []): array
    {
        return $this->createTransaction($payment, $customerInfo);
    }
}
