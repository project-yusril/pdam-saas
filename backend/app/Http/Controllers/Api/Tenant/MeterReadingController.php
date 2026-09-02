<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\MeterReading;
use App\Models\MeterRoute;
use App\Models\ReadingPeriod;
use App\Services\FileUploadService;
use App\Services\MeterOcrService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * MeterReadingController — baca meter digital + periode (PRD 3.2, Fase 3).
 * Menangani buka/tutup periode, input pembacaan (OCR/manual/estimasi),
 * deteksi rollover (meter putar balik), flag anomali, dan verifikasi kantor.
 */
class MeterReadingController extends Controller
{
    /**
     * Parse angka meter dari teks OCR (foto register meter).
     * Hasil dikembalikan untuk dikonfirmasi/dikoreksi petugas sebelum `store`.
     */
    public function parseMeter(Request $request, MeterOcrService $ocr): JsonResponse
    {
        $data = $request->validate([
            'raw_text' => ['required', 'string'],
            'black_digits' => ['sometimes', 'integer', 'min:3', 'max:8'],
        ]);

        $parsed = $ocr->parse($data['raw_text'], $data['black_digits'] ?? 5);

        return ApiResponse::success($parsed);
    }

    /** Buka periode baca (Koordinator Baca Meter). */
    public function openPeriod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $period = ReadingPeriod::firstOrNew(['period' => $data['period']]);
        if ($period->exists && $period->status === 'open') {
            return ApiResponse::error('ALREADY_OPEN', 'Periode sudah dibuka.', null, 422);
        }

        $period->fill(['status' => 'open', 'opened_at' => now(), 'opened_by' => $request->user()->id])->save();

        return ApiResponse::message('Periode dibuka.', $period, 201);
    }

    /** Tutup periode baca → siap generate tagihan. */
    public function closePeriod(Request $request, ReadingPeriod $period): JsonResponse
    {
        if ($period->status !== 'open') {
            return ApiResponse::error('NOT_OPEN', 'Periode tidak dalam status terbuka.', null, 422);
        }

        $period->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $request->user()->id]);

        return ApiResponse::message('Periode ditutup.', $period);
    }

    /**
     * Input pembacaan meter (petugas lapangan).
     * Deteksi rollover: jika reading_value < pembacaan sebelumnya, tandai
     * is_rollover & flag agar diverifikasi kantor (konsumsi tak boleh negatif).
     */
    public function store(Request $request): JsonResponse
    {
        $tenantFile = function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
            try {
                app(FileUploadService::class)->assertTenantPath((string) $value, $request->user()->pdam_org_id, 'meter');
            } catch (\InvalidArgumentException $exception) {
                $fail($exception->getMessage());
            }
        };
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'reading_value' => ['required', 'integer', 'min:0'],
            'reading_date' => ['required', 'date'],
            'photo_house_url' => ['nullable', 'string', $tenantFile],
            'photo_meter_url' => ['nullable', 'string', $tenantFile],
            'reading_type' => ['required', 'in:ocr_confirmed,manual_corrected,estimated'],
            'unreadable_reason' => ['nullable', 'required_if:reading_type,estimated', 'string'],
        ]);

        $period = ReadingPeriod::where('period', $data['period'])->first();
        if (! $period || $period->status !== 'open') {
            return ApiResponse::error('PERIOD_CLOSED', 'Periode baca belum dibuka.', null, 422);
        }

        $customer = Customer::findOrFail($data['customer_id']);

        // Pembacaan terakhir untuk deteksi rollover & anomali
        $last = MeterReading::where('customer_id', $customer->id)
            ->orderByDesc('period')
            ->first();
        $previousValue = $last?->reading_value ?? $customer->initial_reading ?? 0;

        $isRollover = $data['reading_value'] < $previousValue;
        $flagReason = null;
        if ($isRollover) {
            $flagReason = 'possible_rollover_or_meter_change';
        }

        $reading = MeterReading::create([
            'customer_id' => $customer->id,
            'period' => $data['period'],
            'reading_value' => $data['reading_value'],
            'reading_date' => $data['reading_date'],
            'photo_house_url' => $data['photo_house_url'] ?? null,
            'photo_meter_url' => $data['photo_meter_url'] ?? null,
            'reading_type' => $data['reading_type'],
            'unreadable_reason' => $data['unreadable_reason'] ?? null,
            'is_rollover' => $isRollover,
            'is_flagged' => $isRollover || $data['reading_type'] === 'estimated',
            'flag_reason' => $flagReason ?? ($data['reading_type'] === 'estimated' ? 'estimated_reading' : null),
            'read_by' => $request->user()->id,
        ]);

        return ApiResponse::message('Pembacaan tersimpan.', $reading, 201);
    }

    /** Verifikasi kantor: konfirmasi/koreksi pembacaan (hapus flag). */
    public function verify(Request $request, MeterReading $reading): JsonResponse
    {
        $data = $request->validate([
            'reading_value' => ['sometimes', 'integer', 'min:0'],
            'clear_flag' => ['sometimes', 'boolean'],
        ]);

        $clearFlag = $data['clear_flag'] ?? false;
        $reading->update([
            'reading_value' => $data['reading_value'] ?? $reading->reading_value,
            'is_flagged' => $clearFlag ? false : $reading->is_flagged,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        return ApiResponse::message('Pembacaan diverifikasi.', $reading->fresh());
    }

    /**
     * Dashboard progress baca meter per rute untuk sebuah periode.
     * Menghitung: total pelanggan aktif per rute, sudah terbaca, sisa,
     * jumlah ter-flag (perlu verifikasi), dan persentase progress.
     */
    public function routeProgress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        $period = $data['period'];

        // Jumlah pelanggan aktif per rute.
        $customersByRoute = Customer::query()
            ->where('status', 'active')
            ->whereNotNull('meter_route_id')
            ->select('meter_route_id', DB::raw('COUNT(*) as total'))
            ->groupBy('meter_route_id')
            ->pluck('total', 'meter_route_id');

        // Pembacaan periode ini per rute (join lewat customer.meter_route_id).
        $readByRoute = MeterReading::query()
            ->where('meter_readings.period', $period)
            ->join('customers', 'customers.id', '=', 'meter_readings.customer_id')
            ->whereNotNull('customers.meter_route_id')
            ->select(
                'customers.meter_route_id as route_id',
                DB::raw('COUNT(*) as read_count'),
                DB::raw('SUM(CASE WHEN meter_readings.is_flagged = 1 THEN 1 ELSE 0 END) as flagged_count'),
            )
            ->groupBy('customers.meter_route_id')
            ->get()
            ->keyBy('route_id');

        $routes = MeterRoute::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        $progress = $routes->map(function (MeterRoute $route) use ($customersByRoute, $readByRoute) {
            $total = (int) ($customersByRoute[$route->id] ?? 0);
            $row = $readByRoute[$route->id] ?? null;
            $read = (int) ($row->read_count ?? 0);
            $flagged = (int) ($row->flagged_count ?? 0);

            return [
                'route_id' => $route->id,
                'code' => $route->code,
                'name' => $route->name,
                'total_customers' => $total,
                'read' => $read,
                'remaining' => max(0, $total - $read),
                'flagged' => $flagged,
                'progress_percent' => $total > 0 ? round($read / $total * 100, 1) : 0.0,
            ];
        });

        return ApiResponse::success([
            'period' => $period,
            'routes' => $progress,
            'summary' => [
                'total_customers' => (int) $progress->sum('total_customers'),
                'total_read' => (int) $progress->sum('read'),
                'total_flagged' => (int) $progress->sum('flagged'),
            ],
        ]);
    }

    /**
     * LAPORAN BACA METER per rute + periode.
     *
     * Untuk setiap pelanggan dalam rute: tampilkan angka baca bulan lalu vs bulan ini
     * (nilai akumulatif meter), pemakaian (m³) = baca kini − baca lalu, golongan tarif,
     * biaya tagihan, foto meter & rumah, serta petugas (read_by) & verifikator (verified_by)
     * agar dapat dipertanggungjawabkan.
     */
    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'route_id' => ['nullable', 'integer', 'exists:meter_routes,id'],
        ]);
        $period = $data['period'];
        $prevPeriod = date('Y-m', strtotime($period.'-01 -1 month'));

        $route = $request->filled('route_id')
            ? MeterRoute::with(['zone:id,name', 'assignments' => fn ($q) => $q->where('is_active', true), 'assignments.officer:id,name'])
                ->find($request->integer('route_id'))
            : null;

        $customers = Customer::query()
            ->with(['tariffCategory:id,code,name', 'street:id,name', 'zone:id,name'])
            ->whereNotNull('meter_route_id')
            ->when($route, fn ($q, $r) => $q->where('meter_route_id', $r->id))
            ->orderBy('customer_number')
            ->get();

        $ids = $customers->pluck('id');

        $current = MeterReading::with(['reader:id,name', 'verifier:id,name'])
            ->where('period', $period)
            ->whereIn('customer_id', $ids)
            ->get()
            ->keyBy('customer_id');

        $previous = MeterReading::where('period', $prevPeriod)
            ->whereIn('customer_id', $ids)
            ->get()
            ->keyBy('customer_id');

        $bills = Bill::where('period', $period)
            ->whereIn('customer_id', $ids)
            ->get()
            ->keyBy('customer_id');

        $rows = $customers->map(function (Customer $c) use ($current, $previous, $bills, $prevPeriod, $period) {
            $curr = $current[$c->id] ?? null;
            $prev = $previous[$c->id]?->reading_value ?? $c->initial_reading ?? 0;

            $usage = null;
            if ($curr) {
                $usage = $curr->reading_value - $prev;
                if ($usage < 0) {
                    $usage = ($curr->reading_value + 100000) - $prev; // rollover / ganti meter
                }
            }

            return [
                'customer_id' => $c->id,
                'customer_number' => $c->customer_number,
                'full_name' => $c->full_name,
                'zone' => $c->zone?->name,
                'street' => $c->street?->name,
                'address_detail' => $c->address_detail,
                'status' => $c->status,
                'tariff_code' => $c->tariffCategory?->code,
                'tariff_name' => $c->tariffCategory?->name,
                'previous_period' => $prevPeriod,
                'previous_reading' => $prev,
                'current_period' => $period,
                'current_reading' => $curr?->reading_value,
                'reading_date' => $curr?->reading_date?->toDateString(),
                'reading_type' => $curr?->reading_type,
                'is_flagged' => (bool) ($curr?->is_flagged ?? false),
                'flag_reason' => $curr?->flag_reason,
                'is_rollover' => (bool) ($curr?->is_rollover ?? false),
                'usage_m3' => $usage,
                'consumption' => $bills[$c->id]?->consumption,
                'amount_due' => $bills[$c->id]?->amount_due !== null ? (float) $bills[$c->id]->amount_due : null,
                'bill_status' => $bills[$c->id]?->status,
                'photo_house_url' => $curr?->photo_house_url,
                'photo_meter_url' => $curr?->photo_meter_url,
                'reader' => $curr?->reader?->name,
                'verifier' => $curr?->verifier?->name,
            ];
        });

        return ApiResponse::success([
            'period' => $period,
            'previous_period' => $prevPeriod,
            'route' => $route ? [
                'id' => $route->id,
                'code' => $route->code,
                'name' => $route->name,
                'zone' => $route->zone?->name,
                'officer' => $route->assignments->first()?->officer?->name,
            ] : null,
            'rows' => $rows,
            'summary' => [
                'total_customers' => $rows->count(),
                'read' => $rows->whereNotNull('current_reading')->count(),
                'unread' => $rows->whereNull('current_reading')->count(),
                'total_usage_m3' => (int) $rows->sum('usage_m3'),
                'total_amount' => (float) $rows->sum('amount_due'),
            ],
        ]);
    }
}
