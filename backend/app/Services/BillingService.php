<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\ReadingPeriod;
use App\Models\TariffCategory;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BillingService — kalkulasi tarif tiered ("ember bertingkat", PRD 10.1) +
 * komponen tetap (abonemen, pemeliharaan meter, admin) + generate tagihan.
 *
 * Prinsip tiered: setiap m³ dikenai harga sesuai tier tempatnya jatuh, BUKAN
 * harga tier tertinggi untuk seluruh pemakaian. Tier di-reset tiap bulan.
 */
class BillingService
{
    public function __construct(private JournalService $journal) {}

    /**
     * Hitung rincian tagihan air (tanpa denda) untuk sebuah golongan & pemakaian.
     *
     * @return array{consumption:int, water_charge:float, tiers:array<int,array>, components:array<int,array>, abonemen:float, meter_maintenance_fee:float, admin_fee:float, total:float}
     */
    public function calculate(TariffCategory $category, int $consumption): array
    {
        // Terapkan pemakaian minimum (banyak PDAM menagih minimal N m³)
        $billableUsage = max($consumption, $category->minimum_usage_m3);

        $tiers = $category->tiers()->orderBy('tier_order')->get();
        if ($tiers->isEmpty()) {
            throw new RuntimeException("Golongan {$category->code} belum punya tier tarif.");
        }

        $remaining = $billableUsage;
        $waterCharge = 0.0;
        $tierBreakdown = [];
        $components = [];

        foreach ($tiers as $tier) {
            if ($remaining <= 0) {
                break;
            }

            // Kapasitas tier ini = (max - min). min inklusif dari sisi bawah.
            // Tier disimpan sebagai batas: tier 1 (0..10], tier 2 (10..20], tier 3 (20..~).
            $lower = $tier->min_usage;
            $upper = $tier->max_usage; // null = tak terbatas
            $capacity = $upper === null ? $remaining : max(0, $upper - $lower);

            $unitsInTier = $upper === null ? $remaining : min($remaining, $capacity);
            if ($unitsInTier <= 0) {
                continue;
            }

            $lineAmount = round($unitsInTier * (float) $tier->price_per_m3, 2);
            $waterCharge += $lineAmount;
            $remaining -= $unitsInTier;

            $tierBreakdown[] = [
                'tier_order' => $tier->tier_order,
                'units' => $unitsInTier,
                'price_per_m3' => (float) $tier->price_per_m3,
                'amount' => $lineAmount,
            ];
            $components[] = [
                'component' => 'tier_'.$tier->tier_order,
                'label' => "Pemakaian tier {$tier->tier_order} ({$unitsInTier} m³ × Rp ".number_format((float) $tier->price_per_m3, 0, ',', '.').')',
                'quantity' => $unitsInTier,
                'unit_price' => (float) $tier->price_per_m3,
                'amount' => $lineAmount,
            ];
        }

        $abonemen = (float) $category->abonemen;
        $meterFee = (float) $category->meter_maintenance_fee;
        $adminFee = (float) $category->admin_fee;

        if ($abonemen > 0) {
            $components[] = ['component' => 'abonemen', 'label' => 'Biaya beban tetap (abonemen)', 'quantity' => 0, 'unit_price' => $abonemen, 'amount' => $abonemen];
        }
        if ($meterFee > 0) {
            $components[] = ['component' => 'meter_maintenance', 'label' => 'Pemeliharaan meter', 'quantity' => 0, 'unit_price' => $meterFee, 'amount' => $meterFee];
        }
        if ($adminFee > 0) {
            $components[] = ['component' => 'admin', 'label' => 'Biaya administrasi', 'quantity' => 0, 'unit_price' => $adminFee, 'amount' => $adminFee];
        }

        $total = round($waterCharge + $abonemen + $meterFee + $adminFee, 2);

        return [
            'consumption' => $consumption,
            'water_charge' => round($waterCharge, 2),
            'tiers' => $tierBreakdown,
            'components' => $components,
            'abonemen' => $abonemen,
            'meter_maintenance_fee' => $meterFee,
            'admin_fee' => $adminFee,
            'total' => $total,
        ];
    }

    /**
     * Generate tagihan untuk 1 pelanggan pada periode tertentu.
     * consumption dihitung dari selisih pembacaan; auto-jurnal Piutang↔Pendapatan.
     */
    public function generateForCustomer(Customer $customer, string $period, int $previousReading, int $currentReading, ?int $previousReadingId = null, ?int $currentReadingId = null): Bill
    {
        // Fase 4.4: tagihan hanya boleh digenerate bila periode baca sudah DITUTUP
        // (semua pembacaan selesai & terverifikasi). Cegah tagih dari data mentah.
        $readingPeriod = ReadingPeriod::where('period', $period)->first();
        if (! $readingPeriod || ! $readingPeriod->isClosed()) {
            throw new RuntimeException("Periode baca {$period} belum ditutup. Tutup periode sebelum generate tagihan.");
        }

        if (Bill::where('customer_id', $customer->id)->where('period', $period)->exists()) {
            throw new RuntimeException("Tagihan periode {$period} untuk pelanggan ini sudah ada.");
        }

        $category = $customer->tariffCategory;
        if (! $category) {
            throw new RuntimeException('Pelanggan belum punya golongan tarif.');
        }

        // Konsumsi: tangani rollover meter (current < previous)
        $consumption = $currentReading - $previousReading;
        if ($consumption < 0) {
            // Asumsikan rollover 5 digit; koreksi agar tidak negatif.
            $consumption = ($currentReading + 100000) - $previousReading;
        }

        $calc = $this->calculate($category, $consumption);
        $setting = BillingSetting::first();
        $dueDay = $setting?->due_day ?? 20;
        $dueDate = $this->buildDueDate($period, $dueDay);

        return DB::transaction(function () use ($customer, $period, $previousReading, $currentReading, $previousReadingId, $currentReadingId, $consumption, $calc, $dueDate) {
            $bill = Bill::create([
                'customer_id' => $customer->id,
                'bill_number' => $this->generateBillNumber($period),
                'period' => $period,
                'previous_reading_id' => $previousReadingId,
                'current_reading_id' => $currentReadingId,
                'previous_reading' => $previousReading,
                'current_reading' => $currentReading,
                'consumption' => $consumption,
                'water_charge' => $calc['water_charge'],
                'abonemen' => $calc['abonemen'],
                'meter_maintenance_fee' => $calc['meter_maintenance_fee'],
                'admin_fee' => $calc['admin_fee'],
                'penalty' => 0,
                'amount_due' => $calc['total'],
                'status' => 'unpaid',
                'due_date' => $dueDate,
            ]);

            foreach ($calc['components'] as $c) {
                $bill->items()->create([
                    'pdam_org_id' => $bill->pdam_org_id,
                    'component' => $c['component'],
                    'label' => $c['label'],
                    'quantity' => $c['quantity'],
                    'unit_price' => $c['unit_price'],
                    'amount' => $c['amount'],
                ]);
            }

            // Auto-jurnal: DEBIT Piutang | KREDIT Pendapatan Air
            $entry = $this->journal->record(
                "Tagihan {$period} - {$customer->customer_number}",
                [
                    ['account_code' => '1-002', 'type' => 'DEBIT', 'amount' => $calc['total'], 'memo' => 'Piutang pelanggan'],
                    ['account_code' => '4-001', 'type' => 'KREDIT', 'amount' => $calc['total'], 'memo' => 'Pendapatan air'],
                ],
                'BILL',
                $bill->id,
            );
            $bill->update(['journal_entry_id' => $entry->id]);

            return $bill->load('items');
        });
    }

    protected function buildDueDate(string $period, int $dueDay): string
    {
        [$y, $m] = explode('-', $period);
        $day = min($dueDay, (int) date('t', mktime(0, 0, 0, (int) $m, 1, (int) $y)));

        return sprintf('%04d-%02d-%02d', $y, $m, $day);
    }

    protected function generateBillNumber(string $period): string
    {
        $org = TenantContext::id();
        $count = Bill::where('period', $period)->count() + 1;

        return sprintf('INV-%s-%s-%05d', $org, str_replace('-', '', $period), $count);
    }
}
