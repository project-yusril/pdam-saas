<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MeterRoute;
use App\Models\Zone;
use App\Services\CustomerMapStatusService;
use App\Services\Geo\NominatimService;
use App\Services\Geo\OsrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GisWebController — dashboard peta pelanggan (OSM + Leaflet) untuk
 * direktur/admin: ikon rumah per status pembayaran, filter zona/rute,
 * pencarian alamat (Nominatim), routing & rute baca meter (OSRM), dan
 * pengisian koordinat (otomatis/manual).
 */
class GisWebController extends Controller
{
    public function __construct(
        private CustomerMapStatusService $mapService,
        private NominatimService $nominatim,
        private OsrmService $osrm,
    ) {}

    public function index(): View
    {
        $zones = Zone::orderBy('name')
            ->get(['id', 'code', 'name']);

        $routes = MeterRoute::where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('admin.gis.peta', [
            'zones' => $zones,
            'routes' => $routes,
            'maxRoutePoints' => $this->osrm->maxPoints(),
        ]);
    }

    /** GeoJSON pelanggan terfilter (idem API gis/customers + pencarian nama). */
    public function customers(Request $request): JsonResponse
    {
        $query = Customer::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotIn('status', CustomerMapStatusService::EXCLUDED_STATUSES);

        $this->applyCommonFilters($query, $request);

        if ($q = trim((string) $request->input('q'))) {
            $query->where(function ($w) use ($q) {
                $w->where('customer_number', 'like', "%{$q}%")
                    ->orWhere('full_name', 'like', "%{$q}%");
            });
        }

        $customers = $query->limit(5000)->get();

        if ($request->filled('colors')) {
            $wanted = array_filter(array_map('trim', explode(',', (string) $request->input('colors'))));
            $rows = $this->mapService->classifyBatch($customers);
            $customers = $customers->filter(
                fn (Customer $c) => in_array($rows[$c->id]['status_color'] ?? '', $wanted, true)
            );
        }

        return response()->json($this->mapService->featureCollection($customers));
    }

    /** Ringkasan jumlah status untuk legenda + pelanggan tanpa koordinat. */
    public function summary(Request $request): JsonResponse
    {
        $onMap = Customer::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotIn('status', CustomerMapStatusService::EXCLUDED_STATUSES);
        $this->applyCommonFilters($onMap, $request);

        $missing = Customer::query()
            ->whereNull('latitude')
            ->whereNull('longitude')
            ->whereNotIn('status', CustomerMapStatusService::EXCLUDED_STATUSES);
        $this->applyCommonFilters($missing, $request);

        return response()->json([
            'counts' => $this->mapService->statusCounts($onMap->get()),
            'missing_coords' => $missing->count(),
        ]);
    }

    /** Daftar pelanggan tanpa koordinat (panel "isi koordinat"). */
    public function noCoords(Request $request): JsonResponse
    {
        $query = Customer::query()
            ->whereNull('latitude')
            ->whereNull('longitude')
            ->whereNotIn('status', CustomerMapStatusService::EXCLUDED_STATUSES)
            ->with('street');
        $this->applyCommonFilters($query, $request);

        $rows = $query
            ->orderBy('customer_number')
            ->limit(500)
            ->get(['id', 'customer_number', 'full_name', 'address_detail', 'street_id', 'zone_id'])
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'customer_number' => $c->customer_number,
                'name' => $c->full_name,
                'address' => $this->addressText($c),
            ])
            ->values();

        return response()->json(['items' => $rows]);
    }

    /** Geocoding box pencarian alamat (proxy Nominatim). */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q'));
        if (mb_strlen($q) < 3) {
            return response()->json(['items' => []], 422);
        }

        // viewbox area peta terlihat biar hasil diprioritaskan ke area itu.
        $viewbox = null;
        if ($bbox = $request->input('bbox')) {
            [$minLon, $minLat, $maxLon, $maxLat] = array_map('floatval', explode(',', (string) $bbox));
            $viewbox = "{$minLon},{$minLat},{$maxLon},{$maxLat}";
        }

        return response()->json(['items' => $this->nominatim->search($q, 6, $viewbox)]);
    }

    /** Routing antar titik pilihan (proxy OSRM). */
    public function route(Request $request): JsonResponse
    {
        $points = $this->parsePoints($request->input('points'));
        if (count($points) < 2) {
            return response()->json(['error' => 'Minimal 2 titik.'], 422);
        }

        $profile = (string) $request->input('profile', 'driving');
        $result = $this->osrm->route($points, $profile);

        return $result === null
            ? response()->json(['error' => 'Routing gagal (server OSRM tidak menjawab).'], 502)
            : response()->json($result);
    }

    /** Rute baca meter optimal sederhana untuk sebuah meter_route. */
    public function tour(Request $request): JsonResponse
    {
        $routeId = (int) $request->input('route_id');
        if ($routeId <= 0) {
            return response()->json(['error' => 'route_id wajib.'], 422);
        }

        $points = Customer::query()
            ->where('meter_route_id', $routeId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotIn('status', CustomerMapStatusService::EXCLUDED_STATUSES)
            ->limit($this->osrm->maxPoints())
            ->get(['id', 'full_name', 'customer_number', 'latitude', 'longitude']);

        if ($points->count() < 2) {
            return response()->json(['error' => 'Rute ini belum punya cukup titik berkoordinat.'], 422);
        }

        $coords = $points->map(fn ($c) => [(float) $c->latitude, (float) $c->longitude])->all();
        $tour = $this->osrm->tour($coords, (string) $request->input('profile', 'driving'));

        if ($tour === null) {
            return response()->json(['error' => 'Routing gagal (server OSRM tidak menjawab).'], 502);
        }

        $stops = array_map(fn (int $idx) => [
            'seq' => array_search($idx, $tour['order'], true) + 1,
            'id' => $points[$idx]->id,
            'name' => $points[$idx]->full_name,
            'customer_number' => $points[$idx]->customer_number,
            'lat' => (float) $points[$idx]->latitude,
            'lng' => (float) $points[$idx]->longitude,
        ], array_keys($points->all()));

        usort($stops, fn ($a, $b) => $a['seq'] <=> $b['seq']);

        return response()->json([
            'stops' => $stops,
            'geometry' => $tour['geometry'],
            'distance' => $tour['distance'],
            'duration' => $tour['duration'],
        ]);
    }

    /** Geocode 1 pelanggan dari alamatnya (Nominatim). */
    public function geocode(Request $request, Customer $customer): JsonResponse
    {
        $address = $this->addressText($customer);
        $hit = $this->nominatim->bestMatch($address);

        if ($hit === null) {
            return response()->json(['error' => 'Alamat tidak ditemukan di OpenStreetMap.'], 422);
        }

        $customer->update([
            'latitude' => $hit['lat'],
            'longitude' => $hit['lon'],
        ]);

        return response()->json([
            'id' => $customer->id,
            'lat' => $hit['lat'],
            'lon' => $hit['lon'],
            'matched' => $hit['label'],
        ]);
    }

    /** Batch isi koordinat kosong (patuh kebijakan Nominatim: 1 req/s). */
    public function geocodeMissing(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->input('limit', 10), 1), 15);

        $candidates = Customer::query()
            ->whereNull('latitude')
            ->whereNull('longitude')
            ->whereNotIn('status', CustomerMapStatusService::EXCLUDED_STATUSES)
            ->orderBy('customer_number')
            ->limit($limit)
            ->get();

        $processed = 0;
        $found = 0;
        $failed = 0;
        $results = [];

        foreach ($candidates as $customer) {
            $address = $this->addressText($customer);
            $hit = $address === '' ? null : $this->nominatim->bestMatch($address);

            if ($hit !== null) {
                $customer->update(['latitude' => $hit['lat'], 'longitude' => $hit['lon']]);
                $found++;
                $results[] = ['id' => $customer->id, 'status' => 'ok', 'name' => $customer->full_name];
            } else {
                $failed++;
                $results[] = ['id' => $customer->id, 'status' => 'not_found', 'name' => $customer->full_name];
            }

            $processed++;
            if ($processed < $limit) {
                usleep(1_050_000); // ≈ 1.05 detik antar request (fair-use Nominatim)
            }
        }

        $remaining = Customer::query()
            ->whereNull('latitude')
            ->whereNull('longitude')
            ->whereNotIn('status', CustomerMapStatusService::EXCLUDED_STATUSES)
            ->count();

        return response()->json([
            'processed' => $processed,
            'found' => $found,
            'failed' => $failed,
            'remaining' => $remaining,
            'results' => $results,
        ]);
    }

    /** Set koordinat manual (direktur/admin klik titik di peta). */
    public function setCoordinates(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-7,7'],
            'longitude' => ['required', 'numeric', 'between:105,115'],
        ]);

        $customer->update([
            'latitude' => round((float) $data['latitude'], 7),
            'longitude' => round((float) $data['longitude'], 7),
        ]);

        return response()->json(['id' => $customer->id, 'latitude' => $customer->latitude, 'longitude' => $customer->longitude]);
    }

    // ── internal ───────────────────────────────────────────────────────────

    private function applyCommonFilters($query, Request $request): void
    {
        if ($request->input('zone_id')) {
            $query->where('zone_id', $request->input('zone_id'));
        }
        if ($request->input('meter_route_id')) {
            $query->where('meter_route_id', $request->input('meter_route_id'));
        }
    }

    /** "No. 5, RT 01/RW 02, Jl. Merdeka, Desa X, Kec. Y, Kab. Z". */
    private function addressText(Customer $customer): string
    {
        $customer->loadMissing('street.village.district.city');

        $parts = array_values(array_filter([
            $customer->address_detail,
            $customer->street?->name,
            $customer->street?->village?->name,
            $customer->street?->village?->district?->name,
            $customer->street?->village?->district?->city?->name,
        ]));

        return trim(implode(', ', $parts));
    }

    /** "lat,lng|lat,lng|..." → [[lat,lng], ...] (max sesuai server OSRM). */
    private function parsePoints(?string $raw): array
    {
        $points = [];
        if ($raw === null) {
            return $points;
        }
        foreach (array_filter(explode('|', $raw)) as $token) {
            $pair = explode(',', $token);
            if (count($pair) !== 2) {
                continue;
            }
            $points[] = [(float) $pair[0], (float) $pair[1]];
        }

        return array_slice($points, 0, $this->osrm->maxPoints());
    }
}
