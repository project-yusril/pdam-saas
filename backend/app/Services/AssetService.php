<?php

namespace App\Services;

use App\Models\AssetCategory;
use App\Models\AssetDisposal;
use App\Models\DepreciationEntry;
use App\Models\FixedAsset;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * AssetService — logika aset tetap PDAM (Fase 7).
 *
 * Tanggung jawab:
 *  - register() : catat aset + kode otomatis + nilai buku awal
 *  - runDepreciation() : hitung penyusutan 1 periode (garis lurus / saldo menurun),
 *    idempoten per (asset, period), + auto-jurnal Beban ↔ Akumulasi
 *  - capitalize() : material pemasangan (WH) → Aset Jaringan (bukan beban)
 *  - dispose() : pelepasan aset + jurnal laba/rugi
 *
 * Semua jurnal lewat JournalService (dijamin balance). Akun COA default:
 *  Beban Penyusutan 5-101, Akumulasi Penyusutan 1-190,
 *  Aset Jaringan 1-004 (kapitalisasi), Kas 1-001, Pendapatan Pelepasan 4-005,
 *  Rugi Pelepasan 5-102. Bisa dioverride per kategori.
 */
class AssetService
{
    public function __construct(private JournalService $journal) {}

    private const ACC_ACCUMULATION = '1-190';

    private const ACC_DEPR_EXPENSE = '5-101';

    private const ACC_NETWORK_ASSET = '1-004';

    private const ACC_INVENTORY = '1-003';

    private const ACC_CASH = '1-001';

    private const ACC_DISPOSAL_INCOME = '4-005';

    private const ACC_DISPOSAL_LOSS = '5-102';

    /**
     * Register aset baru. Kode otomatis: AST-{kategori}-{urut}.
     *
     * @param  array<string,mixed>  $data
     */
    public function register(array $data): FixedAsset
    {
        $category = AssetCategory::findOrFail($data['asset_category_id']);

        $cost = round((float) $data['acquisition_cost'], 2);
        $residual = round((float) ($data['residual_value'] ?? 0), 2);
        if ($residual > $cost) {
            throw new RuntimeException('Nilai residu tidak boleh melebihi nilai perolehan.');
        }

        // Warisi default kategori bila tak dispesifikkan.
        $method = $data['depreciation_method'] ?? $category->depreciation_method;
        $life = (int) ($data['useful_life_months'] ?? $category->useful_life_months);
        $decliningRate = (float) ($data['declining_rate'] ?? $category->declining_rate);
        $isDepreciable = $data['is_depreciable'] ?? $category->is_depreciable;

        return DB::transaction(function () use ($data, $category, $cost, $residual, $method, $life, $decliningRate, $isDepreciable) {
            $asset = FixedAsset::create([
                'pdam_org_id' => TenantContext::id(),
                'asset_category_id' => $category->id,
                'zone_id' => $data['zone_id'] ?? null,
                'code' => $this->generateCode($category),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'location_note' => $data['location_note'] ?? null,
                'acquisition_date' => $data['acquisition_date'],
                'acquisition_cost' => $cost,
                'residual_value' => $residual,
                'useful_life_months' => $life,
                'depreciation_method' => $method,
                'declining_rate' => $decliningRate,
                'is_depreciable' => $isDepreciable,
                'accumulated_depreciation' => 0,
                'book_value' => $cost,
                'source' => $data['source'] ?? 'beli',
                'status' => 'aktif',
                'document_number' => $data['document_number'] ?? null,
                'photo_url' => $data['photo_url'] ?? null,
            ]);

            return $asset;
        });
    }

    /**
     * Hitung penyusutan satu periode (YYYY-MM) untuk satu aset.
     * Return DepreciationEntry, atau null bila tidak disusutkan periode ini.
     */
    public function depreciateAsset(FixedAsset $asset, string $period): ?DepreciationEntry
    {
        // Guard: tanah/non-depreciable, non-aktif, sudah lunas, atau sudah diproses.
        if (! $asset->is_depreciable || $asset->status !== 'aktif') {
            return null;
        }
        if ($asset->isFullyDepreciated()) {
            return null;
        }
        if ($asset->last_depreciated_period !== null && $asset->last_depreciated_period >= $period) {
            return null; // sudah disusutkan s/d periode ini
        }
        // Jangan menyusut sebelum bulan perolehan.
        if ($period < $asset->acquisition_date->format('Y-m')) {
            return null;
        }

        $amount = $this->periodDepreciation($asset);
        // Jangan lewati residu — potong sisa.
        $maxRemaining = (float) $asset->book_value - (float) $asset->residual_value;
        $amount = min($amount, $maxRemaining);
        $amount = round($amount, 2);

        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($asset, $period, $amount) {
            $newAccumulated = round((float) $asset->accumulated_depreciation + $amount, 2);
            $newBookValue = round((float) $asset->acquisition_cost - $newAccumulated, 2);

            $category = $asset->category;
            $expenseAcc = $category->expense_account_code ?: self::ACC_DEPR_EXPENSE;
            $accumAcc = $category->accumulation_account_code ?: self::ACC_ACCUMULATION;

            // Auto-jurnal: DEBIT Beban Penyusutan | KREDIT Akumulasi Penyusutan.
            $entry = $this->journal->record(
                "Penyusutan {$period} - {$asset->code}",
                [
                    ['account_code' => $expenseAcc, 'type' => 'DEBIT', 'amount' => $amount, 'memo' => 'Beban penyusutan'],
                    ['account_code' => $accumAcc, 'type' => 'KREDIT', 'amount' => $amount, 'memo' => 'Akumulasi penyusutan'],
                ],
                'asset_depreciation',
                $asset->id,
                $period.'-01',
            );

            $depr = DepreciationEntry::create([
                'pdam_org_id' => $asset->pdam_org_id,
                'fixed_asset_id' => $asset->id,
                'period' => $period,
                'depreciation_amount' => $amount,
                'accumulated_after' => $newAccumulated,
                'book_value_after' => $newBookValue,
                'journal_entry_id' => $entry->id,
            ]);

            $asset->update([
                'accumulated_depreciation' => $newAccumulated,
                'book_value' => $newBookValue,
                'last_depreciated_period' => $period,
            ]);

            return $depr;
        });
    }

    /** Nilai penyusutan 1 bulan sesuai metode. */
    private function periodDepreciation(FixedAsset $asset): float
    {
        if ($asset->depreciation_method === 'declining_balance') {
            // Saldo menurun: rate% per tahun × nilai buku, dibagi 12.
            $annual = (float) $asset->book_value * ((float) $asset->declining_rate / 100);

            return $annual / 12;
        }

        // Garis lurus: (perolehan − residu) / masa manfaat (bulan).
        $life = max(1, (int) $asset->useful_life_months);

        return $asset->depreciableBase() / $life;
    }

    /**
     * Jalankan penyusutan seluruh aset aktif tenant untuk 1 periode.
     *
     * @return array{count:int, total:float}
     */
    public function runDepreciationForPeriod(string $period): array
    {
        $count = 0;
        $total = 0.0;

        FixedAsset::where('status', 'aktif')->where('is_depreciable', true)
            ->with('category')
            ->chunkById(200, function ($assets) use ($period, &$count, &$total) {
                foreach ($assets as $asset) {
                    $entry = $this->depreciateAsset($asset, $period);
                    if ($entry) {
                        $count++;
                        $total += (float) $entry->depreciation_amount;
                    }
                }
            });

        return ['count' => $count, 'total' => round($total, 2)];
    }

    /**
     * Kapitalisasi: ubah nilai material pemasangan jadi Aset Jaringan.
     * Jurnal: DEBIT Aset Jaringan (1-004) | KREDIT Persediaan Material (1-003).
     *
     * @param  array<string,mixed>  $data
     */
    public function capitalize(array $data): FixedAsset
    {
        $category = AssetCategory::findOrFail($data['asset_category_id']);
        $cost = round((float) $data['acquisition_cost'], 2);

        return DB::transaction(function () use ($data, $category, $cost) {
            $asset = $this->register(array_merge($data, [
                'source' => 'kapitalisasi',
                'residual_value' => $data['residual_value'] ?? 0,
            ]));

            $assetAcc = $category->asset_account_code ?: self::ACC_NETWORK_ASSET;
            $sourceAcc = $data['source_account_code'] ?? self::ACC_INVENTORY;

            $this->journal->record(
                "Kapitalisasi aset - {$asset->code}",
                [
                    ['account_code' => $assetAcc, 'type' => 'DEBIT', 'amount' => $cost, 'memo' => 'Kapitalisasi aset jaringan'],
                    ['account_code' => $sourceAcc, 'type' => 'KREDIT', 'amount' => $cost, 'memo' => 'Pengurangan persediaan/CIP'],
                ],
                'asset_capitalization',
                $asset->id,
                $data['acquisition_date'],
            );

            return $asset->fresh();
        });
    }

    /**
     * Pelepasan aset. Jurnal:
     *  DEBIT Akumulasi Penyusutan (sebesar akumulasi)
     *  DEBIT Kas (bila dijual)
     *  DEBIT Rugi Pelepasan (bila rugi)
     *  KREDIT Aset (sebesar perolehan)
     *  KREDIT Pendapatan Pelepasan (bila laba)
     *
     * @param  array<string,mixed>  $data
     */
    public function dispose(FixedAsset $asset, array $data): AssetDisposal
    {
        if (in_array($asset->status, ['dijual', 'dihapus'], true)) {
            throw new RuntimeException('Aset sudah dilepas sebelumnya.');
        }

        $saleValue = round((float) ($data['sale_value'] ?? 0), 2);
        $bookValue = round((float) $asset->book_value, 2);
        $accumulated = round((float) $asset->accumulated_depreciation, 2);
        $cost = round((float) $asset->acquisition_cost, 2);
        $gainLoss = round($saleValue - $bookValue, 2);
        $disposalType = $data['disposal_type'];

        return DB::transaction(function () use ($asset, $data, $saleValue, $bookValue, $accumulated, $cost, $gainLoss, $disposalType) {
            $category = $asset->category;
            $assetAcc = $category->asset_account_code ?: self::ACC_NETWORK_ASSET;
            $accumAcc = $category->accumulation_account_code ?: self::ACC_ACCUMULATION;

            // Susun baris jurnal seimbang.
            $lines = [];
            if ($accumulated > 0) {
                $lines[] = ['account_code' => $accumAcc, 'type' => 'DEBIT', 'amount' => $accumulated, 'memo' => 'Hapus akumulasi penyusutan'];
            }
            if ($saleValue > 0) {
                $lines[] = ['account_code' => self::ACC_CASH, 'type' => 'DEBIT', 'amount' => $saleValue, 'memo' => 'Kas hasil penjualan'];
            }
            if ($gainLoss < 0) {
                $lines[] = ['account_code' => self::ACC_DISPOSAL_LOSS, 'type' => 'DEBIT', 'amount' => abs($gainLoss), 'memo' => 'Rugi pelepasan aset'];
            }
            $lines[] = ['account_code' => $assetAcc, 'type' => 'KREDIT', 'amount' => $cost, 'memo' => 'Hapus nilai perolehan aset'];
            if ($gainLoss > 0) {
                $lines[] = ['account_code' => self::ACC_DISPOSAL_INCOME, 'type' => 'KREDIT', 'amount' => $gainLoss, 'memo' => 'Laba pelepasan aset'];
            }

            $entry = null;
            // Hanya buat jurnal bila ada nilai (aset nilai 0 tanpa kas → skip).
            if ($cost > 0) {
                $entry = $this->journal->record(
                    "Pelepasan aset ({$disposalType}) - {$asset->code}",
                    $lines,
                    'asset_disposal',
                    $asset->id,
                    $data['disposal_date'],
                );
            }

            $disposal = AssetDisposal::create([
                'pdam_org_id' => $asset->pdam_org_id,
                'fixed_asset_id' => $asset->id,
                'disposal_type' => $disposalType,
                'disposal_date' => $data['disposal_date'],
                'sale_value' => $saleValue,
                'book_value_at_disposal' => $bookValue,
                'gain_loss' => $gainLoss,
                'reason' => $data['reason'] ?? null,
                'journal_entry_id' => $entry?->id,
                'performed_by' => $data['performed_by'] ?? null,
            ]);

            $asset->update([
                'status' => $disposalType === 'dijual' ? 'dijual' : ($disposalType === 'rusak' ? 'rusak' : 'dihapus'),
            ]);

            return $disposal;
        });
    }

    private function generateCode(AssetCategory $category): string
    {
        $seq = FixedAsset::where('asset_category_id', $category->id)->count() + 1;

        return sprintf('AST-%s-%04d', strtoupper($category->code), $seq);
    }
}
