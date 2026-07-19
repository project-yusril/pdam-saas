<?php

namespace App\Console\Commands;

use App\Models\InstallmentSchedule;
use App\Models\PdamOrganization;
use App\Services\NotificationService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class InstallmentReminder extends Command
{
    protected $signature = 'pdam:installment-reminder';

    protected $description = 'Kirim pengingat termin cicilan yang akan jatuh tempo';

    public function handle(NotificationService $notif): int
    {
        $organizations = PdamOrganization::all();

        foreach ($organizations as $org) {
            TenantContext::set($org->id);

            $upcoming = InstallmentSchedule::where('status', 'pending')
                ->whereDate('due_date', now()->addDays(3)->toDateString())
                ->with('plan.customer')
                ->get();

            foreach ($upcoming as $schedule) {
                $customer = $schedule->plan->customer;
                if ($customer?->user_id) {
                    $notif->send($customer->user_id, 'Pengingat Cicilan', "Termin cicilan #{$schedule->id} senilai Rp ".number_format($schedule->amount).' jatuh tempo 3 hari lagi.');
                }
            }

            TenantContext::clear();
        }

        $this->info('Pengingat cicilan terkirim.');

        return self::SUCCESS;
    }
}
