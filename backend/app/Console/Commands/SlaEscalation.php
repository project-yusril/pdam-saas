<?php

namespace App\Console\Commands;

use App\Models\Complaint;
use App\Models\PdamOrganization;
use App\Services\NotificationService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class SlaEscalation extends Command
{
    protected $signature = 'pdam:sla-escalation';

    protected $description = 'Eskalasi tiket pengaduan lewat SLA';

    public function handle(NotificationService $notif): int
    {
        $organizations = PdamOrganization::all();

        foreach ($organizations as $org) {
            TenantContext::set($org->id);

            $overdue = Complaint::whereIn('status', ['open', 'assigned'])
                ->where('sla_due_at', '<', now())
                ->whereNull('repair_order_id')
                ->get();

            foreach ($overdue as $complaint) {
                $complaint->update(['priority' => 'urgent']);

                if ($complaint->assigned_to) {
                    $notif->send($complaint->assigned_to, 'Tiket SLA Terlampaui', "Tiket #{$complaint->ticket_number} sudah melewati SLA. Prioritas dinaikkan ke URGENT.");
                }
            }

            TenantContext::clear();
        }

        $this->info('SLA escalation selesai.');

        return self::SUCCESS;
    }
}
