<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AssetMovement;
use App\Models\FixedAsset;
use App\Services\AssetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FixedAssetController — register aset, penyusutan, mutasi, disposal, dashboard. Fase 7.
 */
class FixedAssetController extends Controller
{
    public function __construct(private AssetService $assets) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 25), 100);
        $query = FixedAsset::with('category:id,code,name', 'zone:id,code,name')->orderBy('code');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($categoryId = $request->query('asset_category_id')) {
            $query->where('asset_category_id', $categoryId);
        }
        if ($zoneId = $request->query('zone_id')) {
            $query->where('zone_id', $zoneId);
        }
        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
        }

        return ApiResponse::paginated($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_category_id' => ['required', 'integer', 'exists:asset_categories,id'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'location_note' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'min:0'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'depreciation_method' => ['nullable', 'in:straight_line,declining_balance'],
            'declining_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_depreciable' => ['nullable', 'boolean'],
            'source' => ['nullable', 'in:beli,hibah,kapitalisasi'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'photo_url' => ['nullable', 'string', 'max:255'],
        ]);

        $asset = $this->assets->register($data);

        return ApiResponse::message('Aset terdaftar.', $asset->load('category'), 201);
    }

    public function show(FixedAsset $fixedAsset): JsonResponse
    {
        return ApiResponse::success(
            $fixedAsset->load('category', 'zone', 'depreciationEntries', 'movements')
        );
    }

    /** Kartu aset (KIB-like): detail + histori penyusutan & mutasi. */
    public function card(FixedAsset $fixedAsset): JsonResponse
    {
        $fixedAsset->load('category', 'zone');

        return ApiResponse::success([
            'asset' => $fixedAsset,
            'depreciation_history' => $fixedAsset->depreciationEntries()->orderBy('period')->get(),
            'movements' => $fixedAsset->movements()->orderBy('moved_at')->get(),
        ]);
    }

    /** Kapitalisasi material pemasangan → aset jaringan (jurnal Aset ↔ Persediaan/CIP). */
    public function capitalize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_category_id' => ['required', 'integer', 'exists:asset_categories,id'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'name' => ['required', 'string', 'max:150'],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'min:0.01'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'source_account_code' => ['nullable', 'string', 'max:20'],
        ]);

        $asset = $this->assets->capitalize($data);

        return ApiResponse::message('Aset dikapitalisasi + jurnal dibukukan.', $asset->load('category'), 201);
    }

    /** Mutasi aset antar lokasi/cabang. */
    public function move(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $data = $request->validate([
            'to_zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'to_location' => ['nullable', 'string', 'max:255'],
            'moved_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $movement = DB::transaction(function () use ($fixedAsset, $data, $request) {
            $mv = AssetMovement::create([
                'pdam_org_id' => $fixedAsset->pdam_org_id,
                'fixed_asset_id' => $fixedAsset->id,
                'from_zone_id' => $fixedAsset->zone_id,
                'to_zone_id' => $data['to_zone_id'] ?? null,
                'from_location' => $fixedAsset->location_note,
                'to_location' => $data['to_location'] ?? null,
                'moved_at' => $data['moved_at'] ?? now()->toDateString(),
                'reason' => $data['reason'] ?? null,
                'performed_by' => $request->user()->id,
            ]);

            $fixedAsset->update([
                'zone_id' => $data['to_zone_id'] ?? $fixedAsset->zone_id,
                'location_note' => $data['to_location'] ?? $fixedAsset->location_note,
            ]);

            return $mv;
        });

        return ApiResponse::message('Aset dimutasi.', $movement);
    }

    /** Pelepasan aset (jual/hapus/rusak) + jurnal laba-rugi. */
    public function dispose(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $data = $request->validate([
            'disposal_type' => ['required', 'in:dijual,dihapus,rusak'],
            'disposal_date' => ['required', 'date'],
            'sale_value' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $data['performed_by'] = $request->user()->id;

        $disposal = $this->assets->dispose($fixedAsset, $data);

        return ApiResponse::message('Aset dilepas + jurnal dibukukan.', $disposal->load('asset'));
    }

    /** Jalankan penyusutan periode secara manual (selain cron). */
    public function runDepreciation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $result = $this->assets->runDepreciationForPeriod($data['period']);

        return ApiResponse::message(
            "Penyusutan periode {$data['period']} selesai: {$result['count']} aset.",
            $result
        );
    }

    /** Dashboard aset: nilai perolehan vs buku, per kategori/status, akan lunas susut. */
    public function dashboard(): JsonResponse
    {
        $totalCost = FixedAsset::whereIn('status', ['aktif', 'rusak'])->sum('acquisition_cost');
        $totalBook = FixedAsset::whereIn('status', ['aktif', 'rusak'])->sum('book_value');
        $totalAccum = FixedAsset::whereIn('status', ['aktif', 'rusak'])->sum('accumulated_depreciation');

        $byCategory = FixedAsset::select('asset_category_id', DB::raw('COUNT(*) as total'), DB::raw('SUM(acquisition_cost) as cost'), DB::raw('SUM(book_value) as book'))
            ->with('category:id,code,name')
            ->groupBy('asset_category_id')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category?->name,
                'total' => (int) $row->total,
                'acquisition_cost' => (float) $row->cost,
                'book_value' => (float) $row->book,
            ]);

        $byStatus = FixedAsset::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->pluck('total', 'status');

        // Aset akan lunas susut: nilai buku mendekati residu (<10% base tersisa).
        $nearlyDepreciated = FixedAsset::where('status', 'aktif')->where('is_depreciable', true)
            ->get()
            ->filter(function (FixedAsset $a) {
                $base = $a->depreciableBase();
                if ($base <= 0) {
                    return false;
                }
                $remaining = (float) $a->book_value - (float) $a->residual_value;

                return $remaining > 0 && $remaining <= 0.1 * $base;
            })
            ->map(fn (FixedAsset $a) => [
                'code' => $a->code,
                'name' => $a->name,
                'book_value' => (float) $a->book_value,
                'residual_value' => (float) $a->residual_value,
            ])
            ->values();

        return ApiResponse::success([
            'summary' => [
                'total_acquisition_cost' => (float) $totalCost,
                'total_book_value' => (float) $totalBook,
                'total_accumulated_depreciation' => (float) $totalAccum,
                'asset_count' => FixedAsset::count(),
            ],
            'by_category' => $byCategory,
            'by_status' => $byStatus,
            'nearly_fully_depreciated' => $nearlyDepreciated,
        ]);
    }
}
