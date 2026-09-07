<?php

namespace App\Console\Commands;

use App\Models\PdamOrganization;
use App\Models\Subscription;
use App\Models\SubscriptionModule;
use App\Services\NotificationChannelService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class SubscriptionCheck extends Command
{
    protected $signature = 'pdam:subscription-check';

    protected $description = 'Auto-lock tenant module saat subscription expired + grace period';

    public function handle(NotificationChannelService $notif): int
    {
        $organizations = PdamOrganization::all();
        $now = now();

        foreach ($organizations as $org) {
            $subscription = Subscription::where('pdam_org_id', $org->id)
                ->where('status', 'active')
                ->first();

            if (! $subscription) {
                continue;
            }

            $graceDays = (int) config('business.billing.subscription_grace_days', 0);

            if ($subscription->end_date && $subscription->end_date->addDays($graceDays)->lt($now)) {
                $subscription->update(['status' => 'expired']);
                $this->info("Subscription expired: org {$org->id}");

                TenantContext::set($org->id);
                SubscriptionModule::where('pdam_org_id', $org->id)
                    ->where('status', 'active')
                    ->whereNotIn('module_code', ['CORE'])
                    ->update(['status' => 'expired', 'locked_at' => now()]);
                TenantContext::clear();
            }
        }

        SubscriptionModule::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->where('module_code', '!=', 'CORE')
            ->update(['status' => 'expired', 'locked_at' => $now]);

        $this->info('Subscription check selesai.');

        return self::SUCCESS;
    }
}
