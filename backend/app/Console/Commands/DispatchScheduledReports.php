<?php

namespace App\Console\Commands;

use App\Jobs\GenerateScheduledReport;
use App\Models\ScheduledReport;
use App\Models\ScheduledReportRun;
use App\Services\ScheduledReportService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DispatchScheduledReports extends Command
{
    protected $signature = 'reports:dispatch-scheduled';

    protected $description = 'Queue due tenant scheduled reports';

    public function handle(ScheduledReportService $schedules): int
    {
        ScheduledReport::query()->withoutGlobalScopes()->where('is_active', true)->where('next_run_at', '<=', now())
            ->orderBy('id')->chunkById(100, function ($reports) use ($schedules) {
                foreach ($reports as $report) {
                    $run = null;
                    DB::transaction(function () use ($report, $schedules, &$run) {
                        $locked = ScheduledReport::query()->withoutGlobalScopes()->lockForUpdate()->find($report->id);
                        if (! $locked || ! $locked->is_active || ! $locked->next_run_at || $locked->next_run_at->isFuture()) {
                            return;
                        }
                        $slot = $locked->next_run_at->copy();
                        try {
                            $run = ScheduledReportRun::query()->withoutGlobalScopes()->create(['pdam_org_id' => $locked->pdam_org_id, 'scheduled_report_id' => $locked->id, 'scheduled_for' => $slot, 'status' => 'queued']);
                        } catch (QueryException) {
                            $run = null;
                        }
                        $locked->update(['next_run_at' => $schedules->advance($locked, $slot)]);
                    });
                    if ($run) {
                        GenerateScheduledReport::dispatch($run->id);
                    }
                }
            });

        return self::SUCCESS;
    }
}
