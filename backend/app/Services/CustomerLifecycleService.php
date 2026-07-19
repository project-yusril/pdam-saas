<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Disconnection;
use App\Models\OwnershipTransfer;
use App\Models\Reconnection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * CustomerLifecycleService — perubahan siklus hidup pelanggan aktif:
 * pemutusan (isolir/terminasi), penyambungan kembali, dan balik nama.
 * Semua perubahan status tercatat di customer_status_history (audit).
 * PRD 3.1 / lifecycle Fase 2.6.
 */
class CustomerLifecycleService
{
    /** Putuskan sambungan (isolir / terminasi / tutup sementara). */
    public function disconnect(Customer $customer, string $type, ?string $reason = null, ?int $requestedBy = null): Disconnection
    {
        if (in_array($customer->status, ['isolir', 'terminated'], true)) {
            throw new RuntimeException('Pelanggan sudah dalam status terputus.');
        }

        return DB::transaction(function () use ($customer, $type, $reason, $requestedBy) {
            $disconnection = Disconnection::create([
                'pdam_org_id' => $customer->pdam_org_id,
                'customer_id' => $customer->id,
                'type' => $type,
                'reason' => $reason,
                'effective_date' => now()->toDateString(),
                'requested_by' => $requestedBy,
            ]);

            // Peta type pemutusan → status pelanggan
            $status = match ($type) {
                'terminated' => 'terminated',
                'temporary_closed' => 'temporary_closed',
                default => 'isolir',
            };
            $customer->changeStatus($status, $reason, $requestedBy);

            return $disconnection;
        });
    }

    /** Sambung kembali (buka isolir) dengan biaya buka isolir. */
    public function reconnect(Customer $customer, float $fee = 0, ?int $processedBy = null): Reconnection
    {
        if (! in_array($customer->status, ['isolir', 'temporary_closed'], true)) {
            throw new RuntimeException('Pelanggan tidak dalam status terputus sementara.');
        }

        return DB::transaction(function () use ($customer, $fee, $processedBy) {
            $lastDisconnection = Disconnection::where('customer_id', $customer->id)
                ->latest('id')->first();

            $reconnection = Reconnection::create([
                'pdam_org_id' => $customer->pdam_org_id,
                'customer_id' => $customer->id,
                'disconnection_id' => $lastDisconnection?->id,
                'fee' => $fee,
                'effective_date' => now()->toDateString(),
                'processed_by' => $processedBy,
            ]);

            $customer->changeStatus('active', 'Penyambungan kembali', $processedBy);

            return $reconnection;
        });
    }

    /** Balik nama kepemilikan pelanggan. */
    public function transferOwnership(Customer $customer, array $data, ?int $processedBy = null): OwnershipTransfer
    {
        return DB::transaction(function () use ($customer, $data, $processedBy) {
            $transfer = OwnershipTransfer::create([
                'pdam_org_id' => $customer->pdam_org_id,
                'customer_id' => $customer->id,
                'old_owner_name' => $customer->full_name,
                'new_owner_name' => $data['new_owner_name'],
                'new_owner_nik' => $data['new_owner_nik'] ?? null,
                'new_owner_phone' => $data['new_owner_phone'] ?? null,
                'effective_date' => now()->toDateString(),
                'processed_by' => $processedBy,
            ]);

            // Perbarui data pemilik di record pelanggan
            $customer->update([
                'full_name' => $data['new_owner_name'],
                'phone' => $data['new_owner_phone'] ?? $customer->phone,
            ]);

            return $transfer;
        });
    }
}
