<?php

use App\Console\Commands\AutoIsolirFlag;
use App\Console\Commands\DispatchScheduledReports;
use App\Console\Commands\EscalateInstallationPayments;
use App\Console\Commands\HealthCheck;
use App\Console\Commands\InstallmentReminder;
use App\Console\Commands\MarkOverdueBills;
use App\Console\Commands\NrwMonthly;
use App\Console\Commands\PaymentReconciliation;
use App\Console\Commands\RecurringJournal;
use App\Console\Commands\SendBillingNotifications;
use App\Console\Commands\SlaEscalation;
use App\Console\Commands\SubscriptionCheck;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Penjadwalan cron PDAM (PRD 10.5).
 * - Pengingat tagihan tgl 23, 24, 25 jam 08.00
 * - Tandai overdue tgl 26 jam 06.00
 * - Eskalasi pembayaran pemasangan: tiap jam
 * - Auto-isolir pelanggan menunggak: tgl 1 jam 02.00
 * - Pengingat cicilan: harian jam 07.00
 * - Rekonsiliasi pembayaran: tiap 6 jam
 * - Hitung NRW bulanan utk tren DMA: tgl 1 jam 04.00 (pdam:nrw-monthly)
 */
Schedule::command(SendBillingNotifications::class)->cron('0 8 23-25 * *');
Schedule::command(MarkOverdueBills::class)->cron('0 6 26 * *');
Schedule::command(EscalateInstallationPayments::class)->hourly();
Schedule::command(AutoIsolirFlag::class)->cron('0 2 1 * *');
Schedule::command(InstallmentReminder::class)->dailyAt('07:00');
Schedule::command(PaymentReconciliation::class)->cron('0 */6 * * *');
Schedule::command(SlaEscalation::class)->hourly();
Schedule::command(SubscriptionCheck::class)->dailyAt('01:00');
Schedule::command(RecurringJournal::class)->dailyAt('03:00');
Schedule::command(HealthCheck::class)->everyFiveMinutes();
Schedule::command(DispatchScheduledReports::class)->everyMinute()->withoutOverlapping();
// NRW otomatis tiap awal bulan — isi nrw_balances utk tren di panel GIS.
Schedule::command(NrwMonthly::class)->monthlyOn(1, '04:00')->withoutOverlapping();
