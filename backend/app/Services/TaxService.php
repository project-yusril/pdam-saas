<?php

namespace App\Services;

use App\Models\TaxRecord;
use App\Support\TenantContext;

/**
 * TaxService — kalkulasi PPN/PPh + ekspor e-Faktur (PRD 8.3).
 */
class TaxService
{
    public function __construct(private JournalService $journal) {}

    /**
     * Buat record pajak + auto-jurnal.
     */
    public function record(string $taxType, string $refType, int $refId, float $dpp, float $taxAmount, ?string $dueDate = null): TaxRecord
    {
        $record = TaxRecord::create([
            'pdam_org_id' => TenantContext::id(),
            'tax_type' => $taxType,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'tax_date' => now()->toDateString(),
            'dpp' => $dpp,
            'tax_amount' => $taxAmount,
            'status' => 'unpaid',
            'due_date' => $dueDate ?? now()->addMonth()->toDateString(),
        ]);

        $this->journal->record(
            "Pajak {$taxType} - #{$refId}",
            [
                ['account_code' => '4-001', 'type' => 'DEBIT', 'amount' => $taxAmount, 'memo' => "Beban pajak {$taxType}"],
                ['account_code' => '2-006', 'type' => 'KREDIT', 'amount' => $taxAmount, 'memo' => "Utang pajak {$taxType}"],
            ],
            'tax',
            $record->id,
        );

        return $record;
    }

    public function calculatePpn(float $dpp, float $ratePercent = 11): array
    {
        $rate = $ratePercent / 100;
        $ppn = round($dpp * $rate, 2);

        return ['dpp' => $dpp, 'rate_percent' => $ratePercent, 'ppn' => $ppn, 'total' => round($dpp + $ppn, 2)];
    }

    public function calculatePph21(float $grossSalary, string $taxStatus = 'TK', int $dependents = 0): array
    {
        $ptkp = $this->getPtkp($taxStatus, $dependents);
        $pkp = max(0, $grossSalary * 12 - $ptkp);
        $annualTax = $this->calcProgressive($pkp);
        $monthlyTax = round($annualTax / 12, 0);

        return [
            'gross_monthly' => $grossSalary,
            'gross_annual' => $grossSalary * 12,
            'ptkp' => $ptkp,
            'pkp' => $pkp,
            'tax_annual' => $annualTax,
            'tax_monthly' => $monthlyTax,
            'tax_status' => $taxStatus,
            'dependents' => $dependents,
        ];
    }

    public function calculatePph23(float $dpp, string $type = 'service'): float
    {
        $rate = $type === 'service' ? 0.02 : 0.15;

        return round($dpp * $rate, 2);
    }

    public function exportEfakturCsv(array $taxRecords): string
    {
        $csv = "FK;KD_JENIS_TRANSAKSI;FG_PENGGANTI;NOMOR_FAKTUR;MASA_PAJAK;TAHUN_PAJAK;TANGGAL_FAKTUR;NPWP;NAMA;ALAMAT_LENGKAP;JUMLAH_DPP;JUMLAH_PPN;JUMLAH_PPNBM;ID_KETERANGAN_TAMBAHAN;FG_UANG_MUKA;UANG_MUKA_DPP;UANG_MUKA_PPN;UANG_MUKA_PPNBM;REFERENSI\n";

        foreach ($taxRecords as $r) {
            $csv .= implode(';', [
                'FK', '01', '0', '012'.now()->format('Ym').str_pad($r->id, 6, '0', STR_PAD_LEFT),
                now()->format('m'), now()->format('Y'),
                $r->tax_date, '', $r->reference_type ?? '-', '-',
                number_format($r->dpp, 2, '.', ''),
                number_format($r->tax_amount, 2, '.', ''),
                '0', '', '0', '0', '0', '0', $r->id,
            ])."\n";
        }

        return $csv;
    }

    private function getPtkp(string $status, int $dependents): float
    {
        $base = match ($status) {
            'K' => 58500000,    // Kawin
            'TK' => 54000000,   // Tidak Kawin
            default => 54000000,
        };

        $maxDependents = min($dependents, 3);

        return $base + ($maxDependents * 4500000);
    }

    private function calcProgressive(float $pkp): float
    {
        $brackets = [
            [60000000, 0.05],
            [250000000, 0.15],
            [500000000, 0.25],
            [5000000000, 0.30],
            [PHP_FLOAT_MAX, 0.35],
        ];

        $tax = 0;
        $prevLimit = 0;

        foreach ($brackets as [$limit, $rate]) {
            if ($pkp <= 0) {
                break;
            }
            $taxable = min($pkp, $limit - $prevLimit);
            $tax += $taxable * $rate;
            $pkp -= $taxable;
            $prevLimit = $limit;
        }

        return round($tax, 0);
    }
}
