<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * pdam:ml-export-training-data — ekspor dataset latih REPRESENTATIF untuk
 * gate kalibrasi ML production (temuan2.md §13 #6). Skema kolom MENYAMAI
 * ml/src/data_loader.py sehingga ml/scripts/calibrate_production.py bisa
 * mengonsumsinya tanpa akses DB langsung (satu arah, read-only).
 *
 * Hasil (per dataset, 0600): exports/{billing_history,meter_readings,
 * customer_features,churn_labels,payment_history,anomaly_history,meters_failure}.csv
 */
class MlExportTrainingData extends Command
{
    protected $signature = 'pdam:ml-export-training-data
                            {--org= : PDAM organization id (WAJIB)}
                            {--months=24 : jendela histori (gate menerima >=12)}
                            {--out=ml-dataset : direktori tujuan (dibuat 0700, PII)}';

    protected $description = 'Ekspor CSV dataset latih ML (representativeness gate di sisi ml/)';

    public function handle(): int
    {
        $org = (int) $this->option('org');
        if ($org <= 0) {
            $this->error('--org wajib diisi');

            return self::FAILURE;
        }

        $months = max(1, (int) $this->option('months'));
        $since = now()->subMonths($months)->format('Y-m');
        $dir = (string) $this->option('out');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        @chmod($dir, 0700); // PII: hanya owner (review — export tidak 0777)

        foreach ([
            'billing_history' => [$this->billingHeaders(), fn () => $this->billingRows($org, $since)],
            'meter_readings' => [$this->readingHeaders(), fn () => $this->readingRows($org, $since)],
            'customer_features' => [$this->customerHeaders(), fn () => $this->customerRows($org)],
            'churn_labels' => [$this->churnHeaders(), fn () => $this->churnRows($org)],
            'payment_history' => [$this->paymentHeaders(), fn () => $this->paymentRows($org, $since)],
            'anomaly_history' => [$this->anomalyHeaders(), fn () => $this->anomalyRows($org, $since)],
            'meters_failure' => [$this->meterHeaders(), fn () => $this->meterRows($org)],
        ] as $name => [$headers, $producer]) {
            $this->writeDataset($dir, $name, $headers, $producer);
        }

        $this->info('Selesai. Selanjutnya: python ml/scripts/calibrate_production.py --export-dir '.escapeshellarg($dir).' --org '.$org);

        return self::SUCCESS;
    }

    /**
     * Stream CSV satu dataset per panggilan dengan cursor() → memori O(1 baris).
     * Menghindari ->get() penuh (review: 7 dataset x 500k eager = OOM).
     */
    private function writeDataset(string $dir, string $name, array $headers, callable $producer): void
    {
        $path = $dir.DIRECTORY_SEPARATOR.$name.'.csv';
        $fh = fopen($path, 'wb');
        @chmod($path, 0600);
        try {
            fputcsv($fh, $headers);
            $n = 0;
            foreach ($producer() as $row) {
                fputcsv($fh, $row);
                $n++;
            }
            $this->info(sprintf('%-20s %6d baris → %s', $name, $n, $path));
        } finally {
            fclose($fh);
        }
    }

    /** Kontrak loader: DATEDIFF(NOW(), col) → int positif untuk tanggal lampau. */
    private function daysSince(?string $date, int $nowTs): ?int
    {
        if (! $date) {
            return null;
        }
        $ts = strtotime($date);

        return $ts === false ? null : (int) floor(($nowTs - $ts) / 86400);
    }

    private function billingHeaders(): array
    {
        return ['customer_id', 'period', 'consumption', 'water_charge', 'amount_due', 'status',
            'due_date', 'zone_id', 'tariff_category_id', 'installation_date', 'tariff_group'];
    }

    private function billingRows(int $org, string $since): iterable
    {
        return DB::table('bills as b')
            ->join('customers as c', 'b.customer_id', '=', 'c.id')
            ->join('tariff_categories as tc', 'c.tariff_category_id', '=', 'tc.id')
            ->where('b.pdam_org_id', $org)
            ->where('c.status', 'active')
            ->where('b.period', '>=', $since)
            ->orderBy('b.customer_id')->orderBy('b.period')
            ->cursor(['b.customer_id', 'b.period', 'b.consumption', 'b.water_charge', 'b.amount_due',
                'b.status', 'b.due_date', 'c.zone_id', 'c.tariff_category_id', 'c.installation_date',
                'tc.group_type'])
            ->map(fn ($r) => array_values((array) $r));
    }

    private function readingHeaders(): array
    {
        return ['customer_id', 'period', 'reading_value', 'reading_type', 'reading_date',
            'is_rollover', 'zone_id', 'tariff_category_id', 'initial_reading'];
    }

    private function readingRows(int $org, string $since): iterable
    {
        return DB::table('meter_readings as mr')
            ->join('customers as c', 'mr.customer_id', '=', 'c.id')
            ->where('mr.pdam_org_id', $org)
            ->where('c.status', 'active')
            ->where('mr.period', '>=', $since)
            ->orderBy('mr.customer_id')->orderBy('mr.period')
            ->cursor(['mr.customer_id', 'mr.period', 'mr.reading_value', 'mr.reading_type',
                'mr.reading_date', 'mr.is_rollover', 'c.zone_id', 'c.tariff_category_id', 'c.initial_reading'])
            ->map(fn ($r) => array_values((array) $r));
    }

    private function customerHeaders(): array
    {
        return ['customer_id', 'customer_number', 'zone_id', 'zone_name', 'tariff_category_id',
            'tariff_code', 'tariff_group', 'installation_date', 'initial_reading',
            'meter_serial_number', 'days_since_install', 'has_meter', 'days_since_calibration',
            'meter_diameter', 'meter_brand', 'meter_condition', 'meter_status', 'tamper_status'];
    }

    private function customerRows(int $org): iterable
    {
        $nowTs = time();

        return DB::table('customers as c')
            ->leftJoin('zones as z', 'c.zone_id', '=', 'z.id')
            ->leftJoin('tariff_categories as tc', 'c.tariff_category_id', '=', 'tc.id')
            ->leftJoin('meters as m', 'c.meter_serial_number', '=', 'm.serial_number')
            ->where('c.pdam_org_id', $org)
            ->where('c.status', 'active')
            ->orderBy('c.id')
            ->cursor(['c.id as customer_id', 'c.customer_number', 'c.zone_id', 'z.name as zone_name',
                'c.tariff_category_id', 'tc.code as tariff_code', 'tc.group_type as tariff_group',
                'c.installation_date', 'c.initial_reading', 'c.meter_serial_number', 'm.id as meter_id',
                'm.diameter as meter_diameter', 'm.brand as meter_brand', 'm.condition as meter_condition',
                'm.status as meter_status', 'm.tamper_status', 'm.last_calibration_date'])
            ->map(function ($r) use ($nowTs) {
                $a = (array) $r;

                return [
                    $a['customer_id'], $a['customer_number'], $a['zone_id'], $a['zone_name'],
                    $a['tariff_category_id'], $a['tariff_code'], $a['tariff_group'], $a['installation_date'],
                    $a['initial_reading'], $a['meter_serial_number'],
                    $this->daysSince($a['installation_date'], $nowTs),
                    $a['meter_id'] ? 1 : 0,
                    $this->daysSince($a['last_calibration_date'], $nowTs),
                    $a['meter_diameter'], $a['meter_brand'], $a['meter_condition'],
                    $a['meter_status'], $a['tamper_status'],
                ];
            });
    }

    private function churnHeaders(): array
    {
        return ['customer_id', 'disconnection_date', 'churned'];
    }

    /**
     * MENYAMAI data_loader.get_customer_churn_labels: HANYA status final
     * (isolir REVERSIBEL → dikecualikan) + jendela recency 180 hari.
     */
    private function churnRows(int $org): iterable
    {
        return DB::table('customer_status_history as csh')
            ->join('customers as c', 'csh.customer_id', '=', 'c.id')
            ->where('c.pdam_org_id', $org)
            ->whereIn('csh.to_status', ['disconnected', 'terminated', 'inactive'])
            ->where('csh.created_at', '>=', now()->subDays(180))
            ->groupBy('csh.customer_id')
            ->orderBy('csh.customer_id')
            ->cursor(['csh.customer_id', DB::raw('MAX(csh.created_at) as disconnection_date'), DB::raw('1 as churned')])
            ->map(fn ($r) => array_values((array) $r));
    }

    private function paymentHeaders(): array
    {
        return ['bill_id', 'customer_id', 'amount', 'payment_date', 'payment_method',
            'period', 'due_date', 'days_late'];
    }

    private function paymentRows(int $org, string $since): iterable
    {
        return DB::table('payments as p')
            ->join('bills as b', 'p.bill_id', '=', 'b.id')
            ->where('p.pdam_org_id', $org)
            ->whereNotNull('p.bill_id')
            ->where('b.period', '>=', $since)
            ->orderBy('p.customer_id')->orderBy('b.period')
            ->cursor(['p.bill_id', 'p.customer_id', 'p.amount', 'p.paid_at as payment_date',
                'p.payment_method', 'b.period', 'b.due_date'])
            ->map(function ($r) {
                $a = (array) $r;
                $days = 0;
                if ($a['payment_date'] && $a['due_date']) {
                    // DATEDIFF(paid, due) — signed, sesuai loader.
                    $days = (int) floor((strtotime($a['payment_date']) - strtotime($a['due_date'])) / 86400);
                }

                return [$a['bill_id'], $a['customer_id'], $a['amount'], $a['payment_date'],
                    $a['payment_method'], $a['period'], $a['due_date'], $days];
            });
    }

    private function anomalyHeaders(): array
    {
        return ['customer_id', 'period', 'rule_code', 'severity', 'actual_value',
            'expected_value', 'anomaly_status'];
    }

    private function anomalyRows(int $org, string $since): iterable
    {
        return DB::table('meter_anomalies')
            ->where('pdam_org_id', $org)
            ->where('period', '>=', $since)
            ->orderBy('customer_id')->orderBy('period')
            ->cursor(['customer_id', 'period', 'rule_code', 'severity', 'actual_value',
                'expected_value', 'status as anomaly_status'])
            ->map(fn ($r) => array_values((array) $r));
    }

    private function meterHeaders(): array
    {
        return ['meter_id', 'serial_number', 'customer_id', 'diameter', 'brand', 'condition',
            'install_date', 'status', 'tamper_status', 'last_calibration_date', 'tariff_category_id',
            'tariff_group', 'install_year', 'days_since_install', 'days_since_calibration'];
    }

    private function meterRows(int $org): iterable
    {
        $nowTs = time();

        return DB::table('meters as m')
            ->leftJoin('customers as c', 'm.customer_id', '=', 'c.id')
            ->leftJoin('tariff_categories as tc', 'c.tariff_category_id', '=', 'tc.id')
            ->where('m.pdam_org_id', $org)
            ->whereIn('m.status', ['installed', 'terpasang', 'active'])
            ->orderBy('m.id')
            ->cursor(['m.id as meter_id', 'm.serial_number', 'm.customer_id', 'm.diameter', 'm.brand',
                'm.condition', 'm.install_date', 'm.status', 'm.tamper_status', 'm.last_calibration_date',
                'c.tariff_category_id', 'tc.group_type as tariff_group'])
            ->map(function ($r) use ($nowTs) {
                $a = (array) $r;
                $year = $a['install_date'] ? (int) substr($a['install_date'], 0, 4) : null;

                return [
                    $a['meter_id'], $a['serial_number'], $a['customer_id'], $a['diameter'], $a['brand'],
                    $a['condition'], $a['install_date'], $a['status'], $a['tamper_status'],
                    $a['last_calibration_date'], $a['tariff_category_id'], $a['tariff_group'], $year,
                    $this->daysSince($a['install_date'], $nowTs),
                    $this->daysSince($a['last_calibration_date'], $nowTs),
                ];
            });
    }
}
