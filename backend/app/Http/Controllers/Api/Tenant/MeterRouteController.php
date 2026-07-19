<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MeterRoute;
use App\Models\MeterRouteAssignment;
use App\Models\Street;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * MeterRouteController — CRUD rute baca meter + assign jalan & petugas + auto-map
 * pelanggan ke rute via street_id. Fase 4.1.
 *
 * Rute = kumpulan jalan (streets) yang dibaca oleh petugas tetap. Pelanggan
 * dipetakan ke rute berdasarkan street_id-nya (jalan tempat sambungan berada).
 */
class MeterRouteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MeterRoute::withCount('streets', 'assignments')
            ->with([
                'zone:id,code,name',
                'streets:id,name',
                'assignments' => fn ($q) => $q->where('is_active', true),
            ])
            ->orderBy('code');

        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->integer('zone_id'));
        }
        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        return ApiResponse::success($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $request->user()->pdam_org_id;

        $data = $request->validate([
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'code' => ['required', 'string', 'max:20', Rule::unique('meter_routes')->where('pdam_org_id', $orgId)],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $route = MeterRoute::create($data);

        return ApiResponse::message('Rute baca dibuat.', $route, 201);
    }

    public function show(MeterRoute $route): JsonResponse
    {
        return ApiResponse::success(
            $route->load('zone:id,code,name', 'streets:id,name', 'assignments')
                ->loadCount('streets', 'assignments')
        );
    }

    public function update(Request $request, MeterRoute $route): JsonResponse
    {
        $orgId = $request->user()->pdam_org_id;

        $data = $request->validate([
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('meter_routes')->where('pdam_org_id', $orgId)->ignore($route->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $route->update($data);

        return ApiResponse::success($route->fresh());
    }

    /**
     * Assign daftar jalan (street_id) ke rute (sync). Setelah sinkron, pelanggan
     * di jalan-jalan tsb otomatis dipetakan ke rute ini (auto-map).
     */
    public function syncStreets(Request $request, MeterRoute $route): JsonResponse
    {
        $data = $request->validate([
            'street_ids' => ['required', 'array'],
            'street_ids.*' => ['integer', 'exists:streets,id'],
        ]);

        // Sertakan pdam_org_id pada pivot (kolom NOT NULL).
        $syncData = collect($data['street_ids'])
            ->mapWithKeys(fn ($id) => [$id => ['pdam_org_id' => $route->pdam_org_id]])
            ->all();
        $route->streets()->sync($syncData);

        // Auto-map: pelanggan pada jalan tsb → set meter_route_id
        $mapped = Customer::whereIn('street_id', $data['street_ids'])
            ->update(['meter_route_id' => $route->id]);

        return ApiResponse::message('Jalan disinkronkan ke rute.', [
            'route_id' => $route->id,
            'streets_count' => count($data['street_ids']),
            'customers_mapped' => $mapped,
        ]);
    }

    /** Assign petugas tetap ke rute (satu petugas aktif; sebelumnya dinonaktifkan). */
    public function assignOfficer(Request $request, MeterRoute $route): JsonResponse
    {
        $data = $request->validate([
            'officer_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        // Nonaktifkan assignment aktif sebelumnya (petugas tetap = 1 aktif).
        $route->assignments()->where('is_active', true)->update(['is_active' => false]);

        $assignment = MeterRouteAssignment::create([
            'meter_route_id' => $route->id,
            'officer_id' => $data['officer_id'],
            'is_active' => true,
        ]);

        return ApiResponse::message('Petugas ditugaskan ke rute.', $assignment, 201);
    }

    /**
     * Auto-map seluruh pelanggan ke rute berdasarkan street_id (batch).
     * Berguna setelah import pelanggan atau perubahan pemetaan jalan-rute.
     *
     * @return JsonResponse ringkasan: berapa terpetakan & daftar jalan tanpa rute.
     */
    public function autoMap(): JsonResponse
    {
        // Peta street_id → route_id dari tabel pivot meter_route_streets.
        $streetToRoute = [];
        MeterRoute::with('streets:id')->get()->each(function (MeterRoute $route) use (&$streetToRoute) {
            foreach ($route->streets as $street) {
                $streetToRoute[$street->id] = $route->id;
            }
        });

        $totalMapped = 0;
        foreach ($streetToRoute as $streetId => $routeId) {
            $totalMapped += Customer::where('street_id', $streetId)
                ->where(function ($q) use ($routeId) {
                    $q->whereNull('meter_route_id')->orWhere('meter_route_id', '!=', $routeId);
                })
                ->update(['meter_route_id' => $routeId]);
        }

        // Deteksi jalan tanpa rute: jalan yang punya pelanggan tapi tak ada di pivot.
        $streetsWithCustomers = Customer::whereNotNull('street_id')
            ->distinct()
            ->pluck('street_id')
            ->all();
        $unroutedStreetIds = array_values(array_diff($streetsWithCustomers, array_keys($streetToRoute)));

        $unroutedStreets = Street::whereIn('id', $unroutedStreetIds)->get(['id', 'name']);

        return ApiResponse::success([
            'customers_mapped' => $totalMapped,
            'unrouted_streets' => $unroutedStreets,
            'unrouted_count' => $unroutedStreets->count(),
        ]);
    }
}
