<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NominatimService — proxy geocoding OpenStreetMap (GRATIS).
 *
 * Kebijakan publik nominatim.openstreetmap.org: maks 1 req/detik + header
 * User-Agent wajib berisi kontak. Karena itu SEMUA panggilan harus lewat
 * server-side (endpoint admin/gis/*) dengan throttle + jeda, bukan dari
 * browser. Untuk beban besar: self-host (docker) lalu ganti
 * config('services.nominatim.base_url').
 */
class NominatimService
{
    /**
     * @return array<int, array{label:string,lat:float,lon:float}> kandidat alamat (kosong jika tidak ditemukan)
     */
    public function search(string $query, int $limit = 5, ?string $viewbox = null): array
    {
        $params = [
            'q' => $query,
            'format' => 'jsonv2',
            'limit' => max(1, min($limit, 10)),
        ];
        if ($cc = config('services.nominatim.countrycodes')) {
            $params['countrycodes'] = $cc;
        }
        if ($viewbox !== null) {
            // Bias hasil ke area peta yang terlihat (tidak membatasi ketat).
            $params['viewbox'] = $viewbox;
            $params['bounded'] = 'false';
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('services.nominatim.user_agent'),
                'Accept-Language' => 'id',
            ])
                ->timeout(10)
                ->retry(1, 200, throw: false)
                ->get(rtrim((string) config('services.nominatim.base_url'), '/').'/search', $params);
        } catch (\Throwable $e) {
            Log::warning('Nominatim search gagal: '.$e->getMessage());

            return [];
        }

        if (! $response->successful()) {
            Log::warning('Nominatim search HTTP '.$response->status());

            return [];
        }

        return collect($response->json() ?: [])
            ->filter(fn ($r) => isset($r['lat'], $r['lon'], $r['display_name']))
            ->map(fn ($r) => [
                'label' => $r['display_name'],
                'lat' => (float) $r['lat'],
                'lon' => (float) $r['lon'],
            ])
            ->values()
            ->all();
    }

    /** Cari 1 hasil terbaik untuk query alamat (untuk geocode pelanggan). */
    public function bestMatch(string $query): ?array
    {
        return $this->search($query, limit: 3)[0] ?? null;
    }
}
