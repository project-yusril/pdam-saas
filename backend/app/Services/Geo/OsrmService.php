<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OsrmService — routing OpenStreetMap gratis (server demo OSRM).
 *
 * route()/matrix() diproksikan dari server (bukan browser) supaya base URL
 * bisa dipindah ke instance self-host tanpa ubah frontend dan tetap mudah
 * di-throttle. Profile: driving|cycling|foot.
 */
class OsrmService
{
    public const PROFILES = ['driving', 'cycling', 'foot'];

    /**
     * @param  array<int, array{0:float,1:float}>  $points  [[lat,lng], ...]
     * @return array{geometry:array, distance:float, duration:float}|null
     */
    public function route(array $points, string $profile = 'driving'): ?array
    {
        if (count($points) < 2) {
            return null;
        }
        $profile = $this->safeProfile($profile);

        $response = Http::timeout((int) config('services.osrm.timeout', 15))
            ->get($this->url("route/v1/{$profile}/".$this->coordPath($points)), [
                'overview' => 'full',
                'geometries' => 'geojson',
            ]);

        if (! $response->successful() || $response->json('code') !== 'Ok') {
            Log::warning('OSRM route gagal: '.$response->status().' '.($response->json('code') ?? ''));

            return null;
        }

        $leg = collect($response->json('routes.0.legs', []))->first();
        $geometry = $response->json('routes.0.geometry');
        if (! $geometry) {
            return null;
        }

        return [
            'geometry' => $geometry,
            'distance' => (float) ($leg['distance'] ?? $response->json('routes.0.distance', 0)),
            'duration' => (float) ($leg['duration'] ?? $response->json('routes.0.duration', 0)),
        ];
    }

    /**
     * Matriks durasi detik antar titik.
     *
     * @param  array<int, array{0:float,1:float}>  $points
     * @return array<int, array<int, float>>|null
     */
    public function matrix(array $points, string $profile = 'driving'): ?array
    {
        if (count($points) < 2) {
            return null;
        }
        $profile = $this->safeProfile($profile);

        $response = Http::timeout((int) config('services.osrm.timeout', 15))
            ->get($this->url("table/v1/{$profile}/".$this->coordPath($points)), [
                'annotations' => 'duration',
            ]);

        if (! $response->successful() || $response->json('code') !== 'Ok') {
            Log::warning('OSRM table gagal: '.$response->status().' '.($response->json('code') ?? ''));

            return null;
        }

        return $response->json('durations');
    }

    /**
     * Urutan kunjungan nearest-neighbor (hemat 1x matrix + 1x route),
     * untuk rute baca meter dari titik pertama sebagai start.
     *
     * @param  array<int, array{0:float,1:float}>  $points  min 2
     * @return array{order:array<int,int>, distance:float, duration:float, geometry:array}|null
     */
    public function tour(array $points, string $profile = 'driving'): ?array
    {
        if (count($points) < 2) {
            return null;
        }

        $order = array_map('intval', array_keys($points));
        $durations = $this->matrix($points, $profile);
        if ($durations !== null) {
            $order = $this->nearestNeighbor($durations);
        }

        $ordered = array_map(fn (int $idx) => $points[$idx], $order);
        $route = $this->route($ordered, $profile);
        if ($route === null) {
            return null;
        }

        return [
            'order' => $order,
            'distance' => $route['distance'],
            'duration' => $route['duration'],
            'geometry' => $route['geometry'],
        ];
    }

    public function maxPoints(): int
    {
        return (int) config('services.osrm.max_points', 16);
    }

    // ── internal ───────────────────────────────────────────────────────────

    private function safeProfile(string $profile): string
    {
        return in_array($profile, self::PROFILES, true) ? $profile : 'driving';
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.osrm.base_url'), '/').'/'.$path;
    }

    /** [lat,lng] pairs → "lon,lat;lon,lat" (format OSRM). */
    private function coordPath(array $points): string
    {
        $max = $this->maxPoints();
        if (count($points) > $max) {
            $points = array_slice($points, 0, $max);
        }

        return implode(';', array_map(
            fn ($p) => round((float) $p[1], 6).','.round((float) $p[0], 6),
            $points
        ));
    }

    /** @param array<int,array<int,float>> $durations */
    private function nearestNeighbor(array $durations): array
    {
        $n = count($durations);
        $visited = [0 => true];
        $order = [0];
        $current = 0;

        for ($step = 1; $step < $n; $step++) {
            $best = null;
            $bestDur = INF;
            foreach ($durations[$current] as $j => $dur) {
                if (! isset($visited[$j]) && $dur !== null && $dur > 0 && $dur < $bestDur) {
                    $best = (int) $j;
                    $bestDur = $dur;
                }
            }
            if ($best === null) {
                break; // titik tak terjangkau — lewatkan
            }
            $visited[$best] = true;
            $order[] = $best;
            $current = $best;
        }

        return $order;
    }
}
