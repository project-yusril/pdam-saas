<?php

namespace Tests\Feature;

use App\Console\Commands\MlExportTrainingData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Kontrak header CSV exporter ML (temuan2 §13 #6). Loader ml/ mengasumsikan
 * kolom = data_loader.php; test ini mengunci file terbentuk + hari positif.
 */
class MlExportTrainingDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_exporter_writes_all_contract_csv_headers_on_empty_data(): void
    {
        $dir = sys_get_temp_dir().'/pdam_mlexp_'.uniqid();

        $this->artisan(MlExportTrainingData::class, ['--org' => 1, '--months' => 24, '--out' => $dir])
            ->expectsOutputToContain('billing_history')
            ->assertExitCode(0);

        $expect = [
            'billing_history.csv' => ['customer_id', 'period', 'consumption', 'water_charge', 'amount_due', 'status', 'due_date', 'zone_id', 'tariff_category_id', 'installation_date', 'tariff_group'],
            'meter_readings.csv' => ['customer_id', 'period', 'reading_value'],
            'customer_features.csv' => ['customer_id', 'days_since_install', 'has_meter'],
            'churn_labels.csv' => ['customer_id', 'disconnection_date', 'churned'],
            'payment_history.csv' => ['bill_id', 'payment_date', 'days_late'],
            'anomaly_history.csv' => ['customer_id', 'rule_code', 'anomaly_status'],
            'meters_failure.csv' => ['meter_id', 'install_date', 'days_since_calibration'],
        ];

        foreach ($expect as $file => $headers) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            $this->assertFileExists($path);
            $first = fgetcsv(fopen($path, 'rb'));
            foreach ($headers as $h) {
                $this->assertContains($h, $first, "header {$h} hilang di {$file}");
            }
        }
    }

    public function test_days_since_helper_is_positive_for_past_dates(): void
    {
        $cmd = app(MlExportTrainingData::class);
        $method = new ReflectionMethod($cmd, 'daysSince');
        $method->setAccessible(true);
        $nowTs = time();

        // 30 hari lampau → sekitar 30 positif (bukan negatif — kontrak DATEDIFF).
        $past = date('Y-m-d', $nowTs - 30 * 86400);
        $days = $method->invoke($cmd, $past, $nowTs);
        $this->assertNotNull($days);
        $this->assertGreaterThanOrEqual(29, $days);
        $this->assertLessThanOrEqual(31, $days);

        // Tanggal masa depan boleh negatif (sesuai DATEDIFF MySQL), bukan crash/null.
        $future = date('Y-m-d', $nowTs + 5 * 86400);
        $this->assertEquals(-5, $method->invoke($cmd, $future, $nowTs));

        $this->assertNull($method->invoke($cmd, null, $nowTs));
        $this->assertNull($method->invoke($cmd, 'nope-not-date', $nowTs));
    }
}
