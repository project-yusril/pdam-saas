<?php

namespace App\Console\Commands;

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\PdamOrganization;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class AutoIsolirFlag extends Command
{
    protected $signature = 'pdam:auto-isolir';

    protected $description = 'Auto-isolir pelanggan menunggak > isolir_after_months (tgl 1 tiap bulan)';

    public function handle(): int
    {
        $organizations = PdamOrganization::all();

        foreach ($organizations as $org) {
            TenantContext::set($org->id);

            $setting = BillingSetting::where('pdam_org_id', $org->id)->first();
            $threshold = $setting?->isolir_after_months ?? 3;

            $overdueCustomers = Customer::where('status', 'active')
                ->whereHas('bills', function ($q) use ($threshold) {
                    $q->whereIn('status', ['overdue'])
                        ->where('due_date', '<', now()->subMonths($threshold));
                })
                ->get();

            foreach ($overdueCustomers as $customer) {
                $customer->changeStatus('disconnected', "Auto-isolir: menunggak > {$threshold} bulan");
            }

            TenantContext::clear();
        }

        $this->info('Auto-isolir selesai.');

        return self::SUCCESS;
    }
}
