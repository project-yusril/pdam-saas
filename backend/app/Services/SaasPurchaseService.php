<?php

namespace App\Services;

use App\Exceptions\MarketplaceException;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\SaasInvoice;
use App\Models\SaasPurchaseOrder;
use App\Models\Subscription;
use App\Models\SubscriptionModule;
use App\Models\User;
use App\Services\Gateways\MidtransSnap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaasPurchaseService
{
    public function __construct(
        private MarketplaceService $marketplace,
        private MidtransSnap $midtrans,
    ) {}

    public function create(User $user, array $items, string $idempotencyKey): SaasPurchaseOrder
    {
        $order = DB::transaction(function () use ($user, $items, $idempotencyKey) {
            $existing = SaasPurchaseOrder::query()->withoutGlobalScopes()
                ->where('pdam_org_id', $user->pdam_org_id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing->load('lines');
            }

            $organization = PdamOrganization::findOrFail($user->pdam_org_id);
            $quote = $this->marketplace->quote($organization, $items);
            $order = SaasPurchaseOrder::create([
                'order_number' => (string) Str::uuid(),
                'pdam_org_id' => $user->pdam_org_id,
                'requested_by' => $user->id,
                'idempotency_key' => $idempotencyKey,
                'status' => 'pending',
                'amount' => $quote['total'],
                'gateway' => 'midtrans',
            ]);
            foreach ($quote['lines'] as $line) {
                $order->lines()->create([
                    'module_code' => $line['module']->code,
                    'module_name' => $line['module']->name,
                    'amount' => $line['price'],
                    'dependencies' => $line['module']->dependencies ?? [],
                ]);
            }

            return $order->load('lines');
        });
        $wasRecentlyCreated = $order->wasRecentlyCreated;

        if ($order->status === 'pending' && ! $order->payment_url) {
            try {
                $transaction = $this->midtrans->createSaasTransaction($order, $user);
                $order->update(['payment_url' => $transaction['redirect_url'] ?? null]);
            } catch (\Throwable $exception) {
                report($exception);
                throw new MarketplaceException(
                    'PAYMENT_GATEWAY_UNAVAILABLE',
                    'Pesanan tersimpan, tetapi kanal pembayaran belum tersedia. Ulangi dengan Idempotency-Key yang sama.',
                );
            }
        }

        $order = $order->fresh('lines');
        $order->wasRecentlyCreated = $wasRecentlyCreated;

        return $order;
    }

    public function settle(string $orderNumber, string $transactionId): SaasPurchaseOrder
    {
        return DB::transaction(function () use ($orderNumber, $transactionId) {
            $order = SaasPurchaseOrder::query()->withoutGlobalScopes()->with('lines')
                ->where('order_number', $orderNumber)->lockForUpdate()->first();
            if (! $order) {
                throw new MarketplaceException('ORDER_NOT_FOUND', 'Order tidak dikenal.');
            }
            if ($order->status === 'settled') {
                return $order;
            }
            if ($order->status !== 'pending') {
                throw new MarketplaceException('ORDER_NOT_SETTLEABLE', 'Order tidak dapat diselesaikan.');
            }

            $modules = Module::whereIn('code', $order->lines->pluck('module_code'))->get();
            if ($modules->count() !== $order->lines->count()) {
                throw new MarketplaceException('ORDER_INVALID', 'Modul order tidak lagi tersedia.');
            }
            $this->marketplace->assertDependencies($order->pdam_org_id, $modules);

            foreach ($order->lines as $line) {
                SubscriptionModule::query()->withoutGlobalScopes()->updateOrCreate(
                    ['pdam_org_id' => $order->pdam_org_id, 'module_code' => $line->module_code],
                    ['status' => 'active', 'activation_method' => 'gateway', 'amount_paid' => $line->amount, 'activated_by' => $order->requested_by, 'activated_at' => now(), 'expires_at' => now()->addYear(), 'locked_by' => null, 'locked_at' => null],
                );
            }
            Subscription::query()->updateOrCreate(
                ['pdam_org_id' => $order->pdam_org_id, 'status' => 'active'],
                ['plan_tier' => 'modular', 'start_date' => today(), 'end_date' => today()->addYear(), 'billing_cycle' => 'yearly'],
            );
            SaasInvoice::query()->withoutGlobalScopes()->create(['pdam_org_id' => $order->pdam_org_id, 'period' => now()->format('Y'), 'amount' => $order->amount, 'status' => 'paid', 'paid_at' => now()]);
            $order->update(['status' => 'settled', 'gateway_transaction_id' => $transactionId, 'settled_at' => now()]);

            return $order->fresh('lines');
        });
    }
}
