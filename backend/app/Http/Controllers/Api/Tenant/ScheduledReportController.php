<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateScheduledReport;
use App\Models\ScheduledReport;
use App\Models\ScheduledReportRun;
use App\Services\ReportExportService;
use App\Services\ScheduledReportService;
use App\Support\ApiResponse;
use App\Support\ReportDatasetGate;
use App\Support\ReportDatasetRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScheduledReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(ScheduledReport::query()->withCount('runs')->latest()->get());
    }

    public function store(Request $request, ScheduledReportService $schedules): JsonResponse
    {
        $data = $this->validated($request);
        if ($denial = $this->authorizeDataset($request, $data)) {
            return $denial;
        }
        $report = ScheduledReport::create([...$schedules->normalize($data), 'pdam_org_id' => $request->user()->pdam_org_id, 'created_by' => $request->user()->id]);

        return ApiResponse::success($report, status: 201);
    }

    public function update(Request $request, ScheduledReport $scheduledReport, ScheduledReportService $schedules): JsonResponse
    {
        $this->assertOwned($request, $scheduledReport);
        $data = $this->validated($request);
        if ($denial = $this->authorizeDataset($request, $data)) {
            return $denial;
        }
        $scheduledReport->update($schedules->normalize($data));

        return ApiResponse::success($scheduledReport->fresh());
    }

    public function destroy(Request $request, ScheduledReport $scheduledReport): JsonResponse
    {
        $this->assertOwned($request, $scheduledReport);
        $scheduledReport->delete();

        return ApiResponse::message('Jadwal laporan dihapus.');
    }

    public function runNow(Request $request, ScheduledReport $scheduledReport): JsonResponse
    {
        $this->assertOwned($request, $scheduledReport);
        $dataset = ReportDatasetRegistry::get($scheduledReport->dataset);
        if (! $dataset || ($denial = ReportDatasetGate::authorize($request, $dataset, true))) {
            return $denial ?? ApiResponse::error('INVALID_TABLE', 'Dataset tidak tersedia.', null, 422);
        }
        $slot = now()->startOfSecond();
        $run = ScheduledReportRun::firstOrCreate(
            ['scheduled_report_id' => $scheduledReport->id, 'scheduled_for' => $slot],
            ['pdam_org_id' => $request->user()->pdam_org_id, 'status' => 'queued'],
        );
        if ($run->wasRecentlyCreated) {
            GenerateScheduledReport::dispatch($run->id);
        }

        return ApiResponse::success($run, status: $run->wasRecentlyCreated ? 202 : 200);
    }

    public function history(Request $request, ScheduledReport $scheduledReport): JsonResponse
    {
        $this->assertOwned($request, $scheduledReport);

        return ApiResponse::success($scheduledReport->runs()->latest('scheduled_for')->limit(100)->get());
    }

    public function download(Request $request, ScheduledReport $scheduledReport, ScheduledReportRun $run): StreamedResponse|JsonResponse
    {
        $this->assertOwned($request, $scheduledReport);
        if ($run->pdam_org_id !== $request->user()->pdam_org_id || $run->scheduled_report_id !== $scheduledReport->id || $run->status !== 'completed' || ! $run->artifact_path || ! Storage::disk('private')->exists($run->artifact_path)) {
            return ApiResponse::error('ARTIFACT_NOT_FOUND', 'Artifact laporan tidak tersedia.', null, 404);
        }

        return Storage::disk('private')->download($run->artifact_path, $run->filename, ['Content-Type' => $run->content_type]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'dataset' => ['required', Rule::in(ReportDatasetRegistry::names())],
            'format' => ['required', Rule::in(['csv', 'html'])],
            'columns' => ['required', 'array', 'min:1', 'max:30'],
            'columns.*' => ['required', 'string', 'distinct'],
            'filters' => ['nullable', 'array', 'max:20'],
            'sort' => ['nullable', 'string'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'timezone' => ['required', 'timezone:all'],
            'local_time' => ['required', 'date_format:H:i'],
            'day_of_week' => ['required_if:frequency,weekly', 'nullable', 'integer', 'between:0,6'],
            'day_of_month' => ['required_if:frequency,monthly', 'nullable', 'integer', 'between:1,31'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizeDataset(Request $request, array $data): ?JsonResponse
    {
        $dataset = ReportDatasetRegistry::get($data['dataset']);
        if ($denial = ReportDatasetGate::authorize($request, $dataset, true)) {
            return $denial;
        }
        try {
            app(ReportExportService::class)->generate($request->user()->pdam_org_id, $data['dataset'], $data['format'], $data['columns'], $data['filters'] ?? [], $data['sort'] ?? null, $data['name']);
        } catch (MarketplaceException $exception) {
            return ApiResponse::error($exception->errorCode, $exception->getMessage(), $exception->details, 422);
        }

        return null;
    }

    private function assertOwned(Request $request, ScheduledReport $report): void
    {
        abort_unless($report->pdam_org_id === $request->user()->pdam_org_id, 404);
    }
}
