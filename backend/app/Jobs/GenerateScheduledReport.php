<?php

namespace App\Jobs;

use App\Models\ScheduledReport;
use App\Models\ScheduledReportRun;
use App\Models\SubscriptionModule;
use App\Models\User;
use App\Services\ReportExportService;
use App\Support\ReportDatasetGate;
use App\Support\ReportDatasetRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GenerateScheduledReport implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $runId) {}

    public function handle(ReportExportService $exports): void
    {
        $run = ScheduledReportRun::query()->withoutGlobalScopes()->findOrFail($this->runId);
        if ($run->status === 'completed') {
            return;
        }
        $run->update(['status' => 'running', 'started_at' => now(), 'error' => null]);

        try {
            $report = ScheduledReport::query()->withoutGlobalScopes()->findOrFail($run->scheduled_report_id);
            $user = User::query()->withoutGlobalScopes()->findOrFail($report->created_by);
            $biActive = SubscriptionModule::query()->withoutGlobalScopes()->where('pdam_org_id', $report->pdam_org_id)->where('module_code', 'BI')->first()?->isActiveNow() ?? false;
            if (! $biActive) {
                throw new RuntimeException('BI module is locked.');
            }
            $dataset = ReportDatasetRegistry::get($report->dataset);
            if (! $dataset || ReportDatasetGate::authorizeUser($user, $dataset, true)) {
                throw new RuntimeException('Dataset access is no longer permitted.');
            }

            $result = $exports->generate($report->pdam_org_id, $report->dataset, $report->format, $report->columns, $report->filters ?? [], $report->sort, $report->name);
            $filename = $report->dataset.'_'.$run->scheduled_for->format('Ymd_His').'.'.$report->format;
            $path = "scheduled-reports/{$report->pdam_org_id}/{$report->id}/{$run->id}/{$filename}";
            if (! Storage::disk('private')->put($path, $result['content'])) {
                throw new RuntimeException('Unable to store report artifact.');
            }
            $run->update(['status' => 'completed', 'artifact_path' => $path, 'filename' => $filename, 'content_type' => $result['content_type'], 'row_count' => $result['row_count'], 'completed_at' => now()]);
        } catch (\Throwable $exception) {
            $run->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 2000), 'completed_at' => now()]);
        }
    }
}
