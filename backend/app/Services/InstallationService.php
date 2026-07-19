<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerProspect;
use App\Models\InstallationSchedule;
use App\Models\SubscriptionModule;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * InstallationService — penjadwalan, pelaksanaan, dan aktivasi pemasangan baru.
 * PRD 3.1 tahap 6: payment_paid → installation_scheduled → installed → active.
 */
class InstallationService
{
    /**
     * Order material pemasangan untuk prospek.
     * Saat ini hanya mengembalikan rencana material dari survey. Status WH
     * dilaporkan eksplisit, tetapi reservasi, stock-out, dan jurnal belum terjadi.
     *
     * @return array{mode:string, warehouse_module_active:bool, stock_reserved:bool, stock_issued:bool, accounting_posted:bool, materials:array}
     */
    public function orderMaterials(CustomerProspect $prospect): array
    {
        if (! in_array($prospect->status, ['payment_paid', 'installation_scheduled'], true)) {
            throw new RuntimeException('Prospek belum siap untuk order material (harus sudah bayar).');
        }

        $survey = $prospect->survey; // laporan survey terbaru (estimasi material)
        $materials = $survey?->estimated_materials ?? [];

        // Cek entitlement modul WH untuk tenant ini (tanpa hard-dependency).
        $whActive = SubscriptionModule::query()
            ->where('pdam_org_id', $prospect->pdam_org_id)
            ->where('module_code', 'WH')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if (! $whActive) {
            return [
                'mode' => 'planning_only',
                'warehouse_module_active' => false,
                'stock_reserved' => false,
                'stock_issued' => false,
                'accounting_posted' => false,
                'materials' => $materials,
            ];
        }

        return [
            'mode' => 'planning_only',
            'warehouse_module_active' => true,
            'stock_reserved' => false,
            'stock_issued' => false,
            'accounting_posted' => false,
            'materials' => $materials,
        ];
    }

    /** Jadwalkan pemasangan (prospek harus sudah bayar biaya pemasangan). */
    public function schedule(CustomerProspect $prospect, string $scheduledDate, int $technicianId): InstallationSchedule
    {
        if ($prospect->status !== 'payment_paid') {
            throw new RuntimeException('Prospek belum melunasi biaya pemasangan.');
        }

        return DB::transaction(function () use ($prospect, $scheduledDate, $technicianId) {
            $schedule = InstallationSchedule::create([
                'pdam_org_id' => $prospect->pdam_org_id,
                'prospect_id' => $prospect->id,
                'scheduled_date' => $scheduledDate,
                'technician_id' => $technicianId,
                'status' => 'scheduled',
            ]);

            $prospect->update(['status' => 'installation_scheduled']);

            return $schedule;
        });
    }

    /** Teknisi menandai pemasangan selesai (dengan foto hasil). */
    public function complete(InstallationSchedule $schedule, array $resultPhotoUrls = []): InstallationSchedule
    {
        if ($schedule->status !== 'scheduled') {
            throw new RuntimeException('Jadwal pemasangan tidak dalam status terjadwal.');
        }

        return DB::transaction(function () use ($schedule, $resultPhotoUrls) {
            $schedule->update([
                'status' => 'installed',
                'result_photo_urls' => $resultPhotoUrls,
                'installed_at' => now(),
            ]);

            CustomerProspect::where('id', $schedule->prospect_id)
                ->update(['status' => 'installed']);

            return $schedule->fresh();
        });
    }

    /**
     * Aktivasi: buat record Customer aktif dari prospek yang sudah terpasang.
     * Menetapkan nomor pelanggan, meter awal 0 m³, dan menautkan rute (opsional).
     */
    public function activate(CustomerProspect $prospect, array $attributes = []): Customer
    {
        if ($prospect->status !== 'installed') {
            throw new RuntimeException('Prospek belum berstatus terpasang.');
        }

        return DB::transaction(function () use ($prospect, $attributes) {
            $customer = Customer::create([
                'pdam_org_id' => $prospect->pdam_org_id,
                'user_id' => $prospect->user_id,
                'prospect_id' => $prospect->id,
                'customer_number' => $attributes['customer_number'] ?? $this->generateCustomerNumber(),
                'full_name' => $prospect->full_name,
                'phone' => $prospect->phone,
                'email' => $prospect->email,
                'zone_id' => $prospect->zone_id,
                'tariff_category_id' => $attributes['tariff_category_id'] ?? $prospect->tariff_category_id,
                'meter_serial_number' => $attributes['meter_serial_number'] ?? null,
                'meter_route_id' => $attributes['meter_route_id'] ?? null,
                'installation_date' => now()->toDateString(),
                'initial_reading' => 0, // meter awal 0 m³ (PRD 3.1)
                'status' => 'active',
            ]);

            $prospect->update(['status' => 'active']);

            $customer->statusHistory()->create([
                'pdam_org_id' => $customer->pdam_org_id,
                'from_status' => null,
                'to_status' => 'active',
                'reason' => 'Aktivasi pelanggan baru',
            ]);

            return $customer;
        });
    }

    protected function generateCustomerNumber(): string
    {
        $org = TenantContext::id();

        return sprintf('CUST-%s-%s', $org, strtoupper(uniqid()));
    }
}
