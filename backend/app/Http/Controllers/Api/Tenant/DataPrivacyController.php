<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\HrEmployee;
use App\Models\Payment;
use App\Models\PrivacyPurgeRequest;
use App\Services\PrivacyAuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DataPrivacyController extends Controller
{
    /** Dapatkan semua metadata yang disimpan tentang user. */
    public function exportMyData(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->pdam_org_id;

        $customer = Customer::where('pdam_org_id', $orgId)->where('user_id', $user->id)->first();
        $employee = HrEmployee::where('pdam_org_id', $orgId)->where('user_id', $user->id)->first();

        $data = [
            'user' => $user->only(['id', 'name', 'email', 'created_at']),
            'customer' => $customer ? $customer->only(['id', 'customer_number', 'full_name', 'phone', 'status']) : null,
            'employee' => $employee ? $employee->only(['id', 'nip', 'name', 'employment_status']) : null,
            'bills' => $customer ? Bill::where('customer_id', $customer->id)->limit(100)->get(['id', 'bill_number', 'period', 'amount_due', 'status']) : [],
            'payments' => $customer ? Payment::where('customer_id', $customer->id)->limit(50)->get(['id', 'payment_number', 'amount', 'status', 'paid_at']) : [],
            'activities' => ActivityLog::where('user_id', $user->id)->limit(200)->get(['id', 'action', 'created_at']),
        ];

        return ApiResponse::success($data);
    }

    /** Hapus data pengguna sesuai UU PDP — right to be forgotten. */
    public function deleteMyData(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->pdam_org_id;

        // Anonimkan user (jangan hard-delete biar audit trail utuh)
        $user->update([
            'name' => 'ANON_'.substr(md5($user->id), 0, 8),
            'email' => 'anon_'.$user->id.'@deleted.local',
            'phone' => null,
            'is_active' => false,
        ]);

        // Hapus data pelanggan
        $customer = Customer::where('pdam_org_id', $orgId)->where('user_id', $user->id)->first();
        if ($customer) {
            $customer->update([
                'full_name' => 'ANON_'.$customer->id,
                'phone' => null,
                'email' => null,
                'status' => 'inactive',
            ]);
        }

        // Hapus notifikasi
        AppNotification::where('user_id', $user->id)->delete();

        // Hapus semua token Sanctum
        $user->tokens()->delete();

        ActivityLog::create([
            'pdam_org_id' => $orgId,
            'user_id' => $user->id,
            'actor_type' => 'user',
            'action' => 'gdpr_delete_request',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return ApiResponse::message('Data Anda telah dianonimkan sesuai UU PDP.');
    }

    /** Cek status retensi per jenis data. Data keuangan disimpan 10 tahun. */
    public function retentionStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->pdam_org_id;

        $oldBills = Bill::where('pdam_org_id', $orgId)
            ->where('created_at', '<', now()->subYears(config('privacy.financial_retention_years')))
            ->count();

        $oldPayments = Payment::where('pdam_org_id', $orgId)
            ->where('created_at', '<', now()->subYears(config('privacy.financial_retention_years')))
            ->count();

        $oldLogs = ActivityLog::where('pdam_org_id', $orgId)
            ->where('created_at', '<', now()->subYears(config('privacy.activity_log_retention_years')))
            ->count();

        return ApiResponse::success([
            'retention_policy' => [
                'financial_records' => '10 tahun',
                'activity_logs' => '2 tahun',
            ],
            'old_bills_count' => $oldBills,
            'old_payments_count' => $oldPayments,
            'old_activity_logs_count' => $oldLogs,
            'can_purge' => $oldBills > 0 || $oldPayments > 0 || $oldLogs > 0,
        ]);
    }

    /** Preview atau buat permintaan purge. Eksekusi memerlukan admin kedua. */
    public function purgeOldData(Request $request): JsonResponse
    {
        $this->authorizePurge($request);
        $data = $request->validate([
            'dry_run' => ['sometimes', 'boolean'],
            'confirmation' => ['required_if:dry_run,false', 'string'],
            'reason' => ['required_if:dry_run,false', 'string', 'max:1000'],
        ]);

        $orgId = $request->user()->pdam_org_id;
        $dryRun = $data['dry_run'] ?? true;
        $cutoff = now()->subYears(config('privacy.activity_log_retention_years'));
        $query = ActivityLog::where('pdam_org_id', $orgId)->where('created_at', '<', $cutoff);
        $candidateCount = (clone $query)->count();

        if ($dryRun) {
            return ApiResponse::success([
                'dry_run' => true,
                'activity_logs_to_purge' => $candidateCount,
                'cutoff' => $cutoff->toIso8601String(),
                'financial_records_affected' => 0,
            ]);
        }

        if (($data['confirmation'] ?? '') !== 'PURGE_OLD_ACTIVITY_LOGS') {
            return ApiResponse::error(
                'CONFIRMATION_MISMATCH',
                'Frasa konfirmasi purge tidak sesuai.',
                null,
                422,
            );
        }

        $purgeRequest = DB::transaction(function () use ($data, $request, $orgId, $candidateCount, $cutoff) {
            $purgeRequest = PrivacyPurgeRequest::create([
                'public_id' => (string) Str::uuid(),
                'pdam_org_id' => $orgId,
                'requested_by' => $request->user()->id,
                'status' => 'pending',
                'target' => 'activity_logs',
                'policy_version' => config('privacy.policy_version'),
                'reason' => $data['reason'],
                'cutoff_at' => $cutoff,
                'candidate_count' => $candidateCount,
                'expires_at' => now()->addMinutes(config('privacy.purge_request_ttl_minutes')),
            ]);
            app(PrivacyAuditService::class)->append($purgeRequest, $request->user()->id, 'requested', [
                'candidate_count' => $candidateCount,
                'cutoff' => $cutoff->toIso8601String(),
                'ip_address' => $request->ip(),
                'policy_version' => config('privacy.policy_version'),
                'reason' => $data['reason'],
            ]);

            return $purgeRequest;
        });

        return ApiResponse::success([
            'dry_run' => false,
            'financial_records_affected' => 0,
            'status' => 'pending_approval',
            'purge_request_id' => $purgeRequest->public_id,
            'activity_logs_to_purge' => $candidateCount,
            'expires_at' => $purgeRequest->expires_at->toIso8601String(),
            'approval_confirmation' => 'APPROVE PRIVACY PURGE '.$purgeRequest->public_id,
        ], status: 202);
    }

    public function approvePurge(Request $request, string $purgeRequest): JsonResponse
    {
        $this->authorizePurge($request);
        $data = $request->validate(['confirmation' => ['required', 'string']]);
        $orgId = $request->user()->pdam_org_id;

        $result = DB::transaction(function () use ($request, $data, $purgeRequest, $orgId) {
            $pending = PrivacyPurgeRequest::where('pdam_org_id', $orgId)
                ->where('public_id', $purgeRequest)
                ->lockForUpdate()
                ->firstOrFail();

            if ($pending->requested_by === $request->user()->id) {
                abort(403, 'Pembuat permintaan tidak boleh menyetujui purge sendiri.');
            }
            if ($pending->status !== 'pending' || $pending->expires_at->isPast()) {
                return ['error' => ApiResponse::error('PURGE_REQUEST_INACTIVE', 'Permintaan purge tidak aktif atau sudah kedaluwarsa.', null, 409)];
            }
            if ($data['confirmation'] !== 'APPROVE PRIVACY PURGE '.$pending->public_id) {
                return ['error' => ApiResponse::error('CONFIRMATION_MISMATCH', 'Frasa persetujuan purge tidak sesuai.', null, 422)];
            }

            $query = ActivityLog::where('pdam_org_id', $orgId)->where('created_at', '<', $pending->cutoff_at);
            $executionCount = (clone $query)->count();
            if ($executionCount !== $pending->candidate_count) {
                return ['error' => ApiResponse::error('PURGE_SCOPE_CHANGED', 'Jumlah data berubah; buat permintaan purge baru.', [
                    'expected' => $pending->candidate_count,
                    'actual' => $executionCount,
                ], 409)];
            }

            $deleted = $query->delete();
            $pending->update([
                'status' => 'executed',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'executed_at' => now(),
                'deleted_count' => $deleted,
            ]);
            app(PrivacyAuditService::class)->append($pending, $request->user()->id, 'approved_executed', [
                'approved_by' => $request->user()->id,
                'deleted_count' => $deleted,
                'ip_address' => $request->ip(),
                'requested_by' => $pending->requested_by,
            ]);

            return ['deleted' => $deleted];
        });

        if (isset($result['error'])) {
            return $result['error'];
        }

        return ApiResponse::success([
            'status' => 'executed',
            'purged_activity_logs' => $result['deleted'],
            'financial_records_affected' => 0,
        ]);
    }

    private function authorizePurge(Request $request): void
    {
        if (! $request->user()->is_tenant_admin && ! $request->user()->hasRole('compliance_officer')) {
            abort(403, 'Purge hanya dapat dilakukan admin tenant atau petugas kepatuhan.');
        }
    }
}
