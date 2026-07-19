<?php

namespace App\Console\Commands;

use App\Models\CustomerProspect;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

/**
 * EscalateInstallationPayments — cron tiap jam (PRD 3.1 tahap 5 & 10.5).
 * Calon pelanggan yang sudah di-ACC survey tapi belum bayar biaya pemasangan
 * melewati batas timer (payment_due_at) → eskalasi ke Kepala Hublang untuk
 * dihubungi manual. Berjalan lintas tenant.
 *
 * Batas expired (jauh melewati due) → tandai payment_expired.
 */
class EscalateInstallationPayments extends Command
{
    protected $signature = 'pdam:escalate-installation {--expire-after-hours=24 : Jam sejak jatuh tempo untuk menandai expired}';

    protected $description = 'Eskalasi calon pelanggan yang telat bayar biaya pemasangan ke Kepala Hublang';

    public function handle(NotificationService $notifier): int
    {
        $now = now();
        $expireThreshold = (int) $this->option('expire-after-hours');

        $prospects = CustomerProspect::query()
            ->withoutGlobalScope('tenant')
            ->where('status', 'payment_pending')
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<', $now)
            ->get();

        $escalated = 0;
        $expired = 0;

        foreach ($prospects as $prospect) {
            TenantContext::set($prospect->pdam_org_id);

            $hoursLate = $prospect->payment_due_at->diffInHours($now);

            if ($hoursLate >= $expireThreshold) {
                // Tidak bisa dihubungi dalam ambang waktu → expired
                $prospect->update(['status' => 'payment_expired']);
                $expired++;
            } else {
                // Eskalasi ke semua Kepala Hublang di tenant untuk hubungi manual
                $this->notifyHublang($notifier, $prospect);
                $escalated++;
            }

            TenantContext::clear();
        }

        $this->info("Eskalasi: {$escalated} prospek ke Hublang, {$expired} ditandai expired.");

        return self::SUCCESS;
    }

    private function notifyHublang(NotificationService $notifier, CustomerProspect $prospect): void
    {
        $heads = User::withoutGlobalScope('tenant')
            ->where('pdam_org_id', $prospect->pdam_org_id)
            ->whereHas('roles', fn ($q) => $q->where('code', 'hublang_head'))
            ->get();

        foreach ($heads as $user) {
            $notifier->notify(
                orgId: $prospect->pdam_org_id,
                userId: $user->id,
                type: 'installation_payment_escalation',
                title: 'Calon pelanggan belum bayar pemasangan',
                body: "{$prospect->full_name} belum membayar biaya pemasangan. Hubungi manual.",
                data: ['prospect_id' => $prospect->id],
            );
        }
    }
}
