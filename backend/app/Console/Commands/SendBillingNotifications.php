<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\Customer;
use App\Services\NotificationService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

/**
 * SendBillingNotifications — cron pengingat tagihan (PRD 10.5).
 * Dijadwalkan tgl 23, 24, 25 jam 08.00 untuk tagihan yang belum dibayar.
 * Berjalan lintas tenant (tanpa scope), memproses per tenant.
 */
class SendBillingNotifications extends Command
{
    protected $signature = 'pdam:billing-notify {--period= : Periode YYYY-MM, default bulan berjalan}';

    protected $description = 'Kirim notifikasi pengingat tagihan ke pelanggan yang belum bayar';

    public function handle(NotificationService $notifier): int
    {
        $period = $this->option('period') ?: now()->format('Y-m');
        $day = now()->day;

        // Nomor pengingat berdasarkan tanggal (23→1, 24→2, 25→3/jatuh tempo)
        $reminderNo = match ($day) {
            23 => 1,
            24 => 2,
            25 => 3,
            default => 0,
        };

        $bills = Bill::query()
            ->withoutGlobalScope('tenant')
            ->where('period', $period)
            ->whereIn('status', ['unpaid', 'overdue'])
            ->get();

        $count = 0;
        foreach ($bills as $bill) {
            TenantContext::set($bill->pdam_org_id);

            $customer = Customer::withoutGlobalScope('tenant')->find($bill->customer_id);
            if (! $customer || ! $customer->user_id) {
                TenantContext::clear();

                continue;
            }

            $suffix = $reminderNo === 3 ? ' (JATUH TEMPO HARI INI)' : '';
            $notifier->notify(
                orgId: $bill->pdam_org_id,
                userId: $customer->user_id,
                type: 'bill_reminder',
                title: "Pengingat Tagihan {$period}{$suffix}",
                body: 'Tagihan Rp '.number_format((float) $bill->amount_due, 0, ',', '.').' menunggu pembayaran.',
                data: ['bill_id' => $bill->id, 'reminder_no' => $reminderNo],
            );
            $count++;

            TenantContext::clear();
        }

        $this->info("Terkirim {$count} notifikasi tagihan periode {$period} (pengingat #{$reminderNo}).");

        return self::SUCCESS;
    }
}
