<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\MeterAnomaly;
use App\Services\AnomalyDetectionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * MeterAnomalyController — kelola hasil deteksi anomali + tindak lanjut. Fase 6.2/6.3.
 * Alur: open → reviewing → confirmed/dismissed. Confirmed + resolusi memicu
 * rekomendasi (ganti meter/tagihan susulan/sanksi) & update tamper_status meter.
 */
class MeterAnomalyController extends Controller
{
    public function __construct(private AnomalyDetectionService $detector) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 25), 100);
        $query = MeterAnomaly::with('customer:id,customer_number,full_name', 'meter:id,serial_number')
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($rule = $request->query('rule_code')) {
            $query->where('rule_code', $rule);
        }
        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }
        if ($period = $request->query('period')) {
            $query->where('period', $period);
        }

        return ApiResponse::paginated($query->paginate($perPage));
    }

    /** Pindai periode secara manual (selain via cron). */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $count = $this->detector->scanPeriod($data['period']);

        return ApiResponse::message("Pemindaian selesai: {$count} anomali baru.", [
            'period' => $data['period'],
            'new_anomalies' => $count,
        ]);
    }

    /** Tandai anomali sedang ditinjau. */
    public function review(Request $request, MeterAnomaly $anomaly): JsonResponse
    {
        $anomaly->update([
            'status' => 'reviewing',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return ApiResponse::message('Anomali sedang ditinjau.', $anomaly->fresh());
    }

    /**
     * Konfirmasi anomali + tetapkan resolusi tindak lanjut.
     * resolution: replace_meter | back_bill | sanction | no_action.
     * Bila indikasi manipulasi (rule tampering) → set tamper_status meter.
     */
    public function confirm(Request $request, MeterAnomaly $anomaly): JsonResponse
    {
        $data = $request->validate([
            'resolution' => ['required', 'in:replace_meter,back_bill,sanction,no_action'],
            'tamper_status' => ['nullable', 'in:normal,suspect,tampered,broken'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($anomaly, $data, $request) {
            $anomaly->update([
                'status' => 'confirmed',
                'resolution' => $data['resolution'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'description' => $data['note'] ? $anomaly->description.' | '.$data['note'] : $anomaly->description,
            ]);

            // Update status tamper meter bila diberikan (indikasi pencurian air).
            if (! empty($data['tamper_status']) && $anomaly->meter_id) {
                Meter::where('id', $anomaly->meter_id)->update(['tamper_status' => $data['tamper_status']]);
            }
        });

        return ApiResponse::message('Anomali dikonfirmasi + resolusi ditetapkan.', $anomaly->fresh('meter'));
    }

    /** Tolak/abaikan anomali (false positive). */
    public function dismiss(Request $request, MeterAnomaly $anomaly): JsonResponse
    {
        $anomaly->update([
            'status' => 'dismissed',
            'resolution' => 'no_action',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return ApiResponse::message('Anomali diabaikan.', $anomaly->fresh());
    }

    /**
     * Dashboard analytics METX (6.4): distribusi umur & kondisi meter, anomali
     * per rule/severity, tamper, + rekomendasi ganti meter (umur > ambang / rusak).
     */
    public function dashboard(Request $request): JsonResponse
    {
        $ageThreshold = (int) $request->query('age_threshold', 7); // tahun

        // Distribusi kondisi & status meter.
        $byCondition = Meter::select('condition', DB::raw('COUNT(*) as total'))
            ->groupBy('condition')->pluck('total', 'condition');
        $byStatus = Meter::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->pluck('total', 'status');
        $byTamper = Meter::where('tamper_status', '!=', 'normal')
            ->select('tamper_status', DB::raw('COUNT(*) as total'))
            ->groupBy('tamper_status')->pluck('total', 'tamper_status');

        // Anomali agregat.
        $anomaliesByRule = MeterAnomaly::select('rule_code', DB::raw('COUNT(*) as total'))
            ->groupBy('rule_code')->pluck('total', 'rule_code');
        $anomaliesByStatus = MeterAnomaly::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->pluck('total', 'status');
        $openHigh = MeterAnomaly::where('status', 'open')->where('severity', 'high')->count();

        // Rekomendasi ganti meter: terpasang + (umur > ambang ATAU kondisi buruk ATAU tampered).
        $cutoffYear = (int) now()->year - $ageThreshold;
        $recommendations = Meter::with('customer:id,customer_number,full_name')
            ->where('status', 'terpasang')
            ->where(function ($q) use ($cutoffYear) {
                $q->whereNotNull('install_year')->where('install_year', '<=', $cutoffYear)
                    ->orWhereIn('condition', ['buram', 'macet', 'rusak'])
                    ->orWhere('tamper_status', 'tampered');
            })
            ->orderBy('install_year')
            ->limit(100)
            ->get()
            ->map(fn (Meter $m) => [
                'meter_id' => $m->id,
                'serial_number' => $m->serial_number,
                'customer' => $m->customer?->customer_number,
                'age_years' => $m->ageInYears(),
                'condition' => $m->condition,
                'tamper_status' => $m->tamper_status,
                'reason' => $this->replaceReason($m, $cutoffYear),
            ]);

        return ApiResponse::success([
            'meters' => [
                'by_condition' => $byCondition,
                'by_status' => $byStatus,
                'tampered' => $byTamper,
                'total' => Meter::count(),
            ],
            'anomalies' => [
                'by_rule' => $anomaliesByRule,
                'by_status' => $anomaliesByStatus,
                'open_high_severity' => $openHigh,
            ],
            'replacement_recommendations' => $recommendations,
            'estimated_commercial_nrw_meters' => $recommendations->count(),
        ]);
    }

    private function replaceReason(Meter $meter, int $cutoffYear): string
    {
        $reasons = [];
        if ($meter->install_year && $meter->install_year <= $cutoffYear) {
            $reasons[] = 'umur tua';
        }
        if (in_array($meter->condition, ['buram', 'macet', 'rusak'], true)) {
            $reasons[] = "kondisi {$meter->condition}";
        }
        if ($meter->tamper_status === 'tampered') {
            $reasons[] = 'indikasi manipulasi';
        }

        return implode(', ', $reasons) ?: 'perlu diperiksa';
    }
}
