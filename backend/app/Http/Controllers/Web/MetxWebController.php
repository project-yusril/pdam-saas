<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\MeterAnomaly;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * MetxWebController — UI web METX (Fase 6.4).
 * - meters: daftar master meter fisik + kondisi/status
 * - anomalies: daftar anomali (filter status/rule)
 * - dashboard: ringkasan meter, anomali, rekomendasi ganti meter
 */
class MetxWebController extends Controller
{
    public function meters(Request $request): View
    {
        $query = Meter::with('customer:id,customer_number,full_name')->orderByDesc('created_at');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($condition = $request->query('condition')) {
            $query->where('condition', $condition);
        }

        $meters = $query->paginate(25)->withQueryString();

        return view('admin.metx.meters', compact('meters'));
    }

    public function anomalies(Request $request): View
    {
        $query = MeterAnomaly::with('customer:id,customer_number,full_name', 'meter:id,serial_number')
            ->orderByDesc('created_at');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($rule = $request->query('rule_code')) {
            $query->where('rule_code', $rule);
        }

        $anomalies = $query->paginate(25)->withQueryString();

        return view('admin.metx.anomalies', compact('anomalies'));
    }

    public function dashboard(Request $request): View
    {
        $ageThreshold = (int) $request->query('age_threshold', 7);
        $cutoffYear = (int) now()->year - $ageThreshold;

        $byCondition = Meter::select('condition', DB::raw('COUNT(*) as total'))
            ->groupBy('condition')->pluck('total', 'condition');
        $byStatus = Meter::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->pluck('total', 'status');
        $tampered = Meter::where('tamper_status', '!=', 'normal')->count();

        $anomaliesByRule = MeterAnomaly::select('rule_code', DB::raw('COUNT(*) as total'))
            ->groupBy('rule_code')->pluck('total', 'rule_code');
        $openHigh = MeterAnomaly::where('status', 'open')->where('severity', 'high')->count();

        $recommendations = Meter::with('customer:id,customer_number')
            ->where('status', 'terpasang')
            ->where(function ($q) use ($cutoffYear) {
                $q->whereNotNull('install_year')->where('install_year', '<=', $cutoffYear)
                    ->orWhereIn('condition', ['buram', 'macet', 'rusak'])
                    ->orWhere('tamper_status', 'tampered');
            })
            ->orderBy('install_year')
            ->limit(50)
            ->get();

        $totalMeters = Meter::count();

        return view('admin.metx.dashboard', compact(
            'byCondition', 'byStatus', 'tampered', 'anomaliesByRule',
            'openHigh', 'recommendations', 'totalMeters', 'ageThreshold'
        ));
    }
}
