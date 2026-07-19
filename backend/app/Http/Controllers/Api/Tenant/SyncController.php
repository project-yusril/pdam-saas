<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerProspect;
use App\Models\MeterReading;
use App\Models\MeterRouteAssignment;
use App\Models\ReadingPeriod;
use App\Models\SubscriptionModule;
use App\Models\SurveyReport;
use App\Services\FileUploadService;
use App\Support\ApiResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SyncController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:meter_readings,survey_reports'],
            'payloads' => ['required', 'array', 'min:1', 'max:100'],
        ]);

        $moduleCode = $data['type'] === 'meter_readings' ? 'MTR' : 'SRV';
        if (! $this->moduleActive($request->user()->pdam_org_id, $moduleCode)) {
            return ApiResponse::error('MODULE_LOCKED', "Modul {$moduleCode} tidak aktif.", null, 403);
        }

        $results = collect($data['payloads'])->map(fn ($payload, $index) => $data['type'] === 'meter_readings'
                ? $this->syncMeterReading($request, $payload, $index)
                : $this->syncSurvey($request, $payload, $index)
        )->values();

        return ApiResponse::success([
            'type' => $data['type'],
            'synced' => $results->whereIn('status', ['created', 'duplicate'])->count(),
            'failed' => $results->where('status', 'failed')->count(),
            'results' => $results,
        ]);
    }

    public function download(Request $request): JsonResponse
    {
        $since = $request->date('since');
        $surveys = CustomerProspect::query()
            ->where('assigned_surveyor_id', $request->user()->id)
            ->whereIn('status', ['surveying', 're_survey_needed'])
            ->when($since, fn ($query) => $query->where('updated_at', '>', $since))
            ->orderBy('updated_at')
            ->limit(100)
            ->get();

        return ApiResponse::success([
            'pending_survey_tasks' => $surveys,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'open_reading_periods' => ReadingPeriod::where('status', 'open')->pluck('period'),
            'pending_survey_tasks' => CustomerProspect::where('assigned_surveyor_id', $request->user()->id)
                ->whereIn('status', ['surveying', 're_survey_needed'])->count(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    private function syncMeterReading(Request $request, array $payload, int $index): array
    {
        $tenantFile = $this->tenantFileRule($request, 'meter');
        $validator = Validator::make($payload, [
            'client_uuid' => ['required', 'uuid'],
            'customer_id' => ['required', 'integer'],
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'reading_value' => ['required', 'integer', 'min:0'],
            'reading_date' => ['required', 'date'],
            'reading_type' => ['required', 'in:ocr_confirmed,manual_corrected,estimated,offline_sync'],
            'unreadable_reason' => ['nullable', 'required_if:reading_type,estimated', 'string'],
            'photo_meter_url' => ['nullable', 'string', $tenantFile],
            'photo_house_url' => ['nullable', 'string', $tenantFile],
        ]);
        if ($validator->fails()) {
            return $this->failed($index, $payload, 'VALIDATION_ERROR', $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $existing = MeterReading::where('client_uuid', $data['client_uuid'])->first();
        if ($existing) {
            return $this->success($index, $data['client_uuid'], 'duplicate', $existing->id);
        }

        $period = ReadingPeriod::where('period', $data['period'])->where('status', 'open')->first();
        if (! $period) {
            return $this->failed($index, $data, 'PERIOD_CLOSED', 'Periode baca tidak terbuka.');
        }
        $customer = Customer::find($data['customer_id']);
        if (! $customer) {
            return $this->failed($index, $data, 'CUSTOMER_NOT_FOUND', 'Pelanggan tidak ditemukan.');
        }
        $assigned = MeterRouteAssignment::where('meter_route_id', $customer->meter_route_id)
            ->where('officer_id', $request->user()->id)
            ->where('is_active', true)
            ->exists();
        if (! $assigned && ! $request->user()->is_tenant_admin) {
            return $this->failed($index, $data, 'ROUTE_NOT_ASSIGNED', 'Pelanggan bukan tugas rute petugas.');
        }

        try {
            $reading = DB::transaction(function () use ($data, $customer, $request) {
                $last = MeterReading::where('customer_id', $customer->id)->orderByDesc('period')->lockForUpdate()->first();
                $previous = $last?->reading_value ?? $customer->initial_reading ?? 0;
                $rollover = $data['reading_value'] < $previous;

                return MeterReading::create([
                    ...$data,
                    'customer_id' => $customer->id,
                    'reading_type' => $data['reading_type'] === 'offline_sync' ? 'manual_corrected' : $data['reading_type'],
                    'is_rollover' => $rollover,
                    'is_flagged' => $rollover || $data['reading_type'] === 'estimated',
                    'flag_reason' => $rollover ? 'possible_rollover_or_meter_change' : ($data['reading_type'] === 'estimated' ? 'estimated_reading' : null),
                    'read_by' => $request->user()->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $reading = MeterReading::where('client_uuid', $data['client_uuid'])->first();

            return $this->success($index, $data['client_uuid'], 'duplicate', $reading?->id);
        } catch (\Throwable $exception) {
            return $this->failed($index, $data, 'SYNC_FAILED', $exception->getMessage());
        }

        return $this->success($index, $data['client_uuid'], 'created', $reading->id);
    }

    private function syncSurvey(Request $request, array $payload, int $index): array
    {
        $tenantFile = $this->tenantFileRule($request, 'survey');
        $validator = Validator::make($payload, [
            'client_uuid' => ['required', 'uuid'],
            'prospect_id' => ['required', 'integer'],
            'photo_house_urls' => ['required', 'array', 'min:2'],
            'photo_house_urls.*' => ['string', $tenantFile],
            'distance_to_main_pipe' => ['nullable', 'numeric', 'min:0'],
            'building_condition' => ['nullable', 'string', 'max:100'],
            'accessibility' => ['nullable', 'string', 'max:100'],
            'land_status' => ['nullable', 'in:milik_sendiri,sewa,lainnya'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
            'estimated_materials' => ['nullable', 'array'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'recommendation' => ['required', 'in:feasible,not_feasible'],
            'surveyor_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($validator->fails()) {
            return $this->failed($index, $payload, 'VALIDATION_ERROR', $validator->errors()->toArray());
        }
        $data = $validator->validated();
        $existing = SurveyReport::where('client_uuid', $data['client_uuid'])->first();
        if ($existing) {
            return $this->success($index, $data['client_uuid'], 'duplicate', $existing->id);
        }
        $prospect = CustomerProspect::find($data['prospect_id']);
        if (! $prospect) {
            return $this->failed($index, $data, 'PROSPECT_NOT_FOUND', 'Prospek tidak ditemukan.');
        }
        if ($prospect->assigned_surveyor_id !== $request->user()->id && ! $request->user()->is_tenant_admin) {
            return $this->failed($index, $data, 'SURVEY_NOT_ASSIGNED', 'Prospek tidak ditugaskan ke surveyor ini.');
        }
        if (! in_array($prospect->status, ['surveying', 're_survey_needed'], true)) {
            return $this->failed($index, $data, 'INVALID_STATE', 'Prospek belum siap disurvey.');
        }

        try {
            $report = DB::transaction(function () use ($data, $prospect, $request) {
                $report = SurveyReport::create([
                    ...$data,
                    'prospect_id' => $prospect->id,
                    'surveyor_id' => $request->user()->id,
                    'location_source' => 'surveyor_verified',
                ]);
                $prospect->update([
                    'status' => 'survey_submitted',
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'location_source' => 'surveyor_verified',
                    'location_accuracy' => $data['location_accuracy'] ?? null,
                ]);

                return $report;
            });
        } catch (UniqueConstraintViolationException) {
            $report = SurveyReport::where('client_uuid', $data['client_uuid'])->first();

            return $this->success($index, $data['client_uuid'], 'duplicate', $report?->id);
        } catch (\Throwable $exception) {
            return $this->failed($index, $data, 'SYNC_FAILED', $exception->getMessage());
        }

        return $this->success($index, $data['client_uuid'], 'created', $report->id);
    }

    private function moduleActive(int $orgId, string $moduleCode): bool
    {
        $module = SubscriptionModule::where('pdam_org_id', $orgId)->where('module_code', $moduleCode)->first();

        return $module?->isActiveNow() ?? false;
    }

    private function success(int $index, string $uuid, string $status, ?int $id): array
    {
        return ['index' => $index, 'client_uuid' => $uuid, 'status' => $status, 'server_id' => $id];
    }

    private function failed(int $index, array $payload, string $code, mixed $error): array
    {
        return ['index' => $index, 'client_uuid' => $payload['client_uuid'] ?? null, 'status' => 'failed', 'error_code' => $code, 'error' => $error];
    }

    private function tenantFileRule(Request $request, string $purpose): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request, $purpose): void {
            try {
                app(FileUploadService::class)->assertTenantPath((string) $value, $request->user()->pdam_org_id, $purpose);
            } catch (\InvalidArgumentException $exception) {
                $fail($exception->getMessage());
            }
        };
    }
}
