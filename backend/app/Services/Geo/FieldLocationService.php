<?php

namespace App\Services\Geo;

use App\Models\TechnicianLocation;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * FieldLocationService — posisi LIVE petugas lapangan utk peta GIS Jaringan.
 *
 * Sumber data (semua GPS ASLI dari aplikasi mobile):
 *  - POST /api/v1/field/location (tombol "Lapor Lokasi"),
 *  - piggyback submit laporan survey (latitude/longitude/location_accuracy),
 *  - piggyback submit baca meter (latitude/longitude).
 *
 * Upsert per user → technicians.json menampilkan titik per petugas field
 * (role daftar config business.gis.officer_roles); online = updated_at
 * segar (< business.gis.officer_stale_minutes).
 */
class FieldLocationService
{
    public function __construct(private NetworkGraphService $graph) {}

    /** Catat posisi terkini satu user (satu baris per user, upsert). */
    public function report(User $user, float $lat, float $lng, ?float $accuracy = null): TechnicianLocation
    {
        $row = TechnicianLocation::firstOrNew(['user_id' => $user->id]);
        $row->fill([
            'latitude' => round($lat, 7),
            'longitude' => round($lng, 7),
            'accuracy' => $accuracy !== null ? round($accuracy, 2) : null,
        ]);
        $row->save();

        return $row;
    }

    /** @return array<int,array> petugas field + lokasi terkini + status online */
    public function activeOfficers(int $orgId): array
    {
        $staleMinutes = $this->staleMinutes();
        $roles = $this->officerRoles();

        return User::query()
            ->withoutGlobalScopes()
            ->where('pdam_org_id', $orgId)
            ->where('is_active', true)
            ->with(['roles:id,code', 'latestLocation'])
            ->get()
            ->filter(fn (User $u) => $u->roles->whereIn('code', $roles)->isNotEmpty())
            ->map(function (User $u) use ($staleMinutes) {
                $loc = $u->latestLocation;
                if (! $loc) {
                    return null;
                }
                $ageSeconds = (int) now()->diffInSeconds($loc->updated_at, true);

                return [
                    'user_id' => $u->id,
                    'name' => $u->name,
                    'roles' => $u->roles->pluck('code')->all(),
                    'lat' => (float) $loc->latitude,
                    'lng' => (float) $loc->longitude,
                    'accuracy_m' => $loc->accuracy !== null ? (float) $loc->accuracy : null,
                    'age_seconds' => $ageSeconds,
                    'updated_at' => $loc->updated_at?->toIso8601String(),
                    'online' => $ageSeconds <= $staleMinutes * 60,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Petugas ONLINE terdekat dari titik insiden (garis lurus haversine —
     * rute OSRM opsional ditambahkan pemanggil).
     */
    public function nearestOfficer(int $orgId, float $lat, float $lng): ?array
    {
        $candidates = collect($this->activeOfficers($orgId))
            ->where('online', true)
            ->map(function (array $o) use ($lat, $lng) {
                $o['distance_m'] = round($this->graph->distanceMeters($lat, $lng, $o['lat'], $o['lng']), 1);

                return $o;
            })
            ->sortBy('distance_m')
            ->values();

        return $candidates->isEmpty() ? null : $candidates->first();
    }

    /** Semua kandidat terurut jarak ( utk panel " siapa saja di sekitar"). */
    public function rankedOfficers(int $orgId, float $lat, float $lng): Collection
    {
        return collect($this->activeOfficers($orgId))->map(function (array $o) use ($lat, $lng) {
            $o['distance_m'] = round($this->graph->distanceMeters($lat, $lng, $o['lat'], $o['lng']), 1);

            return $o;
        })->sortBy('distance_m')->values();
    }

    public function staleMinutes(): int
    {
        return max(1, (int) config('business.gis.officer_stale_minutes', 30));
    }

    /** @return array<int,string> */
    public function officerRoles(): array
    {
        return (array) config('business.gis.officer_roles', ['field_technician']);
    }
}
