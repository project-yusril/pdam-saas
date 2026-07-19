<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\MeterAnomaly;
use App\Models\MeterReading;
use Illuminate\Support\Collection;

/**
 * AnomalyDetectionService — engine deteksi anomali konsumsi & indikasi
 * manipulasi meter, berbasis rule (bukan ML). Fase 6.2.
 *
 * Dijalankan setelah periode baca diverifikasi. Untuk tiap pelanggan aktif,
 * konsumsi periode berjalan dibandingkan histori 3 periode sebelumnya.
 * Anomali disimpan idempoten (unique customer+period+rule_code).
 *
 * Rule (PRD 6.2):
 *  - spike            : pemakaian > 2× rata-rata 3 bulan (lonjakan)
 *  - drop             : pemakaian < 50% rata-rata 3 bulan (turun tajam)
 *  - zero_streak      : 0 m³ beruntun ≥2 periode (meter mati/bypass/kosong)
 *  - repeated_estimate: reading_type estimated ≥3 periode (tak pernah terbaca)
 *  - permanent_drop   : konsumsi turun mendadak & bertahan (indikasi meter dibalik)
 *  - category_mismatch: pemakaian tak wajar utk golongan (mis. rumah tangga → niaga)
 */
class AnomalyDetectionService
{
    /** Ambang golongan rumah tangga dianggap tak wajar bila > N m³/bulan. */
    private const HOUSEHOLD_UNUSUAL_M3 = 100;

    /**
     * Pindai satu periode untuk seluruh pelanggan aktif tenant aktif.
     *
     * @return int jumlah anomali baru yang tercatat
     */
    public function scanPeriod(string $period): int
    {
        $created = 0;

        Customer::query()
            ->where('status', 'active')
            ->with('tariffCategory:id,group_type')
            ->chunkById(200, function (Collection $customers) use ($period, &$created) {
                foreach ($customers as $customer) {
                    $created += $this->scanCustomer($customer, $period);
                }
            });

        return $created;
    }

    /**
     * Pindai satu pelanggan pada satu periode. Mengembalikan jumlah anomali baru.
     */
    public function scanCustomer(Customer $customer, string $period): int
    {
        // Ambil pembacaan s/d periode (urut lama→baru), maksimal 6 terakhir.
        $readings = MeterReading::where('customer_id', $customer->id)
            ->where('period', '<=', $period)
            ->orderBy('period')
            ->get(['period', 'reading_value', 'reading_type']);

        if ($readings->isEmpty()) {
            return 0;
        }

        // Hitung konsumsi tiap periode = selisih reading berurutan.
        $consumptions = $this->buildConsumptions($readings, $customer->initial_reading ?? 0);
        if (! isset($consumptions[$period])) {
            return 0; // periode target belum punya pembacaan
        }

        $current = $consumptions[$period];
        $history = $this->previousValues($consumptions, $period, 3);

        $anomalies = [];

        // Rule berbasis rata-rata (butuh histori).
        if (count($history) >= 1) {
            $avg = array_sum($history) / count($history);

            if ($avg > 0 && $current > 2 * $avg) {
                $anomalies[] = ['spike', 'high', $avg, $current, "Lonjakan pemakaian {$current} m³ (>2× rata-rata ".round($avg, 1).' m³)'];
            }
            if ($avg > 0 && $current < 0.5 * $avg) {
                $anomalies[] = ['drop', 'medium', $avg, $current, "Pemakaian turun tajam {$current} m³ (<50% rata-rata ".round($avg, 1).' m³)'];
            }
        }

        // Rule 0 m³ beruntun ≥2 periode.
        if ($this->trailingZeroStreak($consumptions, $period) >= 2) {
            $anomalies[] = ['zero_streak', 'high', 0, 0, 'Pemakaian 0 m³ beruntun ≥2 periode (meter mati/bypass/rumah kosong)'];
        }

        // Rule estimasi berulang ≥3 periode.
        if ($this->trailingEstimateStreak($readings, $period) >= 3) {
            $anomalies[] = ['repeated_estimate', 'high', null, null, 'Estimasi berulang ≥3 periode (meter tak pernah terbaca)'];
        }

        // Rule penurunan permanen: rata-rata 2 periode terakhir < 40% rata-rata 3 periode sebelumnya.
        if ($this->isPermanentDrop($consumptions, $period)) {
            $anomalies[] = ['permanent_drop', 'high', null, $current, 'Konsumsi turun mendadak & bertahan (indikasi meter dimanipulasi/dibalik)'];
        }

        // Rule pemakaian tak wajar per golongan (rumah tangga konsumsi niaga).
        $group = $customer->tariffCategory?->group_type;
        if ($group === 'rumah_tangga' && $current > self::HOUSEHOLD_UNUSUAL_M3) {
            $anomalies[] = ['category_mismatch', 'medium', self::HOUSEHOLD_UNUSUAL_M3, $current, "Pemakaian {$current} m³ tak wajar untuk golongan rumah tangga"];
        }

        $count = 0;
        foreach ($anomalies as [$rule, $severity, $expected, $actual, $desc]) {
            $anomaly = MeterAnomaly::updateOrCreate(
                [
                    'pdam_org_id' => $customer->pdam_org_id,
                    'customer_id' => $customer->id,
                    'period' => $period,
                    'rule_code' => $rule,
                ],
                [
                    'severity' => $severity,
                    'expected_value' => $expected,
                    'actual_value' => $actual,
                    'description' => $desc,
                    // Jangan timpa status bila sudah ditinjau petugas.
                    'status' => 'open',
                ]
            );
            if ($anomaly->wasRecentlyCreated) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Bangun map period → konsumsi (selisih reading berurutan).
     *
     * @return array<string,int>
     */
    private function buildConsumptions(Collection $readings, int $initialReading): array
    {
        $result = [];
        $prev = $initialReading;
        foreach ($readings as $r) {
            $consumption = (int) $r->reading_value - $prev;
            if ($consumption < 0) {
                // Rollover meter 5 digit — koreksi agar tidak negatif.
                $consumption = ((int) $r->reading_value + 100000) - $prev;
            }
            $result[$r->period] = $consumption;
            $prev = (int) $r->reading_value;
        }

        return $result;
    }

    /** Ambil hingga $n nilai konsumsi SEBELUM $period (urut lama→baru). */
    private function previousValues(array $consumptions, string $period, int $n): array
    {
        $periods = array_keys($consumptions);
        $idx = array_search($period, $periods, true);
        if ($idx === false || $idx === 0) {
            return [];
        }

        $start = max(0, $idx - $n);

        return array_slice(array_values($consumptions), $start, $idx - $start);
    }

    /** Hitung berapa periode berturut-turut 0 m³ berakhir di $period (inklusif). */
    private function trailingZeroStreak(array $consumptions, string $period): int
    {
        $periods = array_keys($consumptions);
        $idx = array_search($period, $periods, true);
        if ($idx === false) {
            return 0;
        }

        $streak = 0;
        for ($i = $idx; $i >= 0; $i--) {
            if ((int) $consumptions[$periods[$i]] === 0) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    /** Hitung streak reading_type estimated berakhir di $period. */
    private function trailingEstimateStreak(Collection $readings, string $period): int
    {
        $ordered = $readings->where('period', '<=', $period)->sortBy('period')->values();
        $streak = 0;
        for ($i = $ordered->count() - 1; $i >= 0; $i--) {
            if ($ordered[$i]->reading_type === 'estimated') {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Deteksi penurunan permanen: rata-rata 2 periode terakhir (termasuk target)
     * < 40% rata-rata 3 periode sebelum itu, dan histori cukup.
     */
    private function isPermanentDrop(array $consumptions, string $period): bool
    {
        $values = array_values($consumptions);
        $periods = array_keys($consumptions);
        $idx = array_search($period, $periods, true);
        if ($idx === false || $idx < 4) {
            return false; // butuh minimal 5 periode (3 baseline + 2 terakhir)
        }

        $recent = array_slice($values, $idx - 1, 2);       // 2 periode terakhir
        $baseline = array_slice($values, $idx - 4, 3);      // 3 periode sebelumnya

        $recentAvg = array_sum($recent) / count($recent);
        $baselineAvg = array_sum($baseline) / count($baseline);

        return $baselineAvg > 0 && $recentAvg < 0.4 * $baselineAvg;
    }
}
