<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * ZoneController — CRUD wilayah/cabang PDAM. Fase 2.
 * Aturan: hanya 1 zone boleh is_main=true per tenant.
 * Saat zone baru dibuat, otomatis dibuat 1 gudang buffer.
 */
class ZoneController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Zone::withCount('customers', 'employees')
            ->with('warehouses:id,zone_id,code,name,warehouse_type,is_active')
            ->orderBy('is_main', 'desc')
            ->orderBy('code');

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        return ApiResponse::success($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'office_address' => ['nullable', 'string', 'max:500'],
            'office_phone' => ['nullable', 'string', 'max:30'],
            'is_main' => ['boolean'],
        ]);

        // Hanya 1 kantor utama per tenant
        if (! empty($data['is_main']) && $data['is_main']) {
            $existing = Zone::where('is_main', true)->exists();
            if ($existing) {
                return ApiResponse::error(
                    'MAIN_ZONE_EXISTS',
                    'Sudah ada kantor utama. Nonaktifkan is_main pada zone yang ada terlebih dahulu.',
                    null,
                    422
                );
            }
        }

        // Code unik per tenant (BelongsToTenant scope belum berlaku pada unique check, tambahkan pdam_org_id)
        $orgId = $request->user()->pdam_org_id;
        $request->validate([
            'code' => [Rule::unique('zones')->where('pdam_org_id', $orgId)],
        ]);

        $zone = DB::transaction(function () use ($data) {
            $zone = Zone::create($data);

            // Auto-buat gudang buffer untuk zone baru
            Warehouse::create([
                'zone_id' => $zone->id,
                'code' => 'WH-'.strtoupper($zone->code),
                'name' => 'Gudang Buffer '.$zone->name,
                'warehouse_type' => 'buffer',
                'is_active' => true,
            ]);

            return $zone;
        });

        return ApiResponse::message('Wilayah berhasil dibuat.', $zone->load('warehouses'), 201);
    }

    public function show(Zone $zone): JsonResponse
    {
        return ApiResponse::success(
            $zone->load('warehouses', 'employees:id,zone_id,name,email,phone,is_active')
                ->loadCount('customers', 'employees')
        );
    }

    public function update(Request $request, Zone $zone): JsonResponse
    {
        $orgId = $request->user()->pdam_org_id;

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('zones')->where('pdam_org_id', $orgId)->ignore($zone->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'office_address' => ['nullable', 'string', 'max:500'],
            'office_phone' => ['nullable', 'string', 'max:30'],
            'is_main' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        // Validasi: jika set is_main=true, cek tidak ada zone lain yang sudah is_main
        if (isset($data['is_main']) && $data['is_main'] && ! $zone->is_main) {
            $existing = Zone::where('is_main', true)->where('id', '!=', $zone->id)->exists();
            if ($existing) {
                return ApiResponse::error(
                    'MAIN_ZONE_EXISTS',
                    'Sudah ada kantor utama. Nonaktifkan is_main pada zone lain terlebih dahulu.',
                    null,
                    422
                );
            }
        }

        $zone->update($data);

        return ApiResponse::success($zone->fresh()->load('warehouses'));
    }

    /** Nonaktifkan zone (soft-deactivate, bukan hapus). */
    public function deactivate(Zone $zone): JsonResponse
    {
        if ($zone->is_main) {
            return ApiResponse::error(
                'CANNOT_DEACTIVATE_MAIN',
                'Kantor utama tidak dapat dinonaktifkan.',
                null,
                422
            );
        }

        $zone->update(['is_active' => false]);

        return ApiResponse::message('Wilayah dinonaktifkan.');
    }
}
