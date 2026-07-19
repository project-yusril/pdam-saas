<?php

namespace App\Console\Commands;

use App\Models\PdamOrganization;
use App\Models\Role;
use App\Models\User;
use App\Services\AnomalyDetectionService;
use App\Services\NotificationService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

/**
 * ScanMeterAnomalies — cron deteksi anomali meter (Fase 6.2).
 * Dijalankan setelah verifikasi periode baca. Berjalan lintas tenant:
 * untuk tiap PDAM aktif, set TenantContext lalu pindai periode.
 * Notifikasi dikirim ke user meter_office bila ada anomali baru.
 *
 * Contoh: php artisan pdam:scan-anomalies --period=2026-07
 */
class ScanMeterAnomalies extends Command
{
    protected $signature = 'pdam:scan-anomalies {--period= : Periode YYYY-MM (default: bulan lalu)}';

    protected $description = 'Pindai anomali konsumsi/manipulasi meter per periode (rule-based)';

    public function handle(AnomalyDetectionService $detector, NotificationService $notifier): int
    {
        $period = $this->option('period') ?: now()->subMonth()->format('Y-m');
        $this->info("Memindai anomali meter periode {$period}...");

        $orgs = PdamOrganization::query()->pluck('id');
        $grandTotal = 0;

        foreach ($orgs as $orgId) {
            TenantContext::set($orgId);

            $count = $detector->scanPeriod($period);
            $grandTotal += $count;

            if ($count > 0) {
                $this->notifyMeterOffice($notifier, $orgId, $period, $count);
                $this->line("  Tenant {$orgId}: {$count} anomali baru");
            }

            TenantContext::clear();
        }

        $this->info("Selesai. Total {$grandTotal} anomali baru lintas tenant.");

        return self::SUCCESS;
    }

    /** Kirim notif in-app ke seluruh user ber-role meter_office pada tenant. */
    private function notifyMeterOffice(NotificationService $notifier, int $orgId, string $period, int $count): void
    {
        $role = Role::where('name', 'meter_office')->first();
        if (! $role) {
            return;
        }

        $userIds = User::whereHas('roles', fn ($q) => $q->where('roles.id', $role->id))->pluck('id');

        foreach ($userIds as $userId) {
            $notifier->notify(
                $orgId,
                $userId,
                'meter_anomaly',
                'Anomali meter terdeteksi',
                "{$count} anomali baru pada periode {$period}. Segera tinjau.",
                ['period' => $period, 'count' => $count],
            );
        }
    }
}
