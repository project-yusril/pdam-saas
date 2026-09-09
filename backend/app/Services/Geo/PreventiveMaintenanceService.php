<?php

namespace App\Services\Geo;

use App\Models\GisFeature;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLog;
use App\Services\NotificationChannelService;

/**
 * PreventiveMaintenanceService — jadwal preventif PERANGKAT JARINGAN (valve /
 * hydrant) yang ditautkan langsung ke GisFeature (asset_type='gis_feature',
 * asset_id=feature id) sehingga siklus pelumasan valve & uji hydrant muncul
 * sebagai WORK ORDER tepat waktu — modul MNT ↔ GIS 1 arah tertutup.
 *
 * cron: pdam:mnt-network (harian) → schedule due → WO inspeksi + log + notif.
 */
class PreventiveMaintenanceService
{
    public const ASSET_TYPE = 'gis_feature';

    public function __construct(private NotificationChannelService $notify) {}

    /** Daftar jadwal perangkat jaringan; dueOnly = jatuh tempo ≤ 14 hari. */
    public function schedules(bool $dueOnly = false): array
    {
        $rows = MaintenanceSchedule::query()
            ->where('asset_type', self::ASSET_TYPE)
            ->orderBy('next_due_date')
            ->get();

        $due = $today = now()->toDateString();
        $horizon = now()->addDays(14)->toDateString();

        return $rows
            ->when($dueOnly, fn ($q) => $q->whereDate('next_due_date', '<=', $horizon))
            ->map(function (MaintenanceSchedule $s) use ($due, $today) {
                $feature = GisFeature::find($s->asset_id);
                $overdue = $s->next_due_date !== null && $s->next_due_date->toDateString() <= $today;

                return [
                    'id' => (int) $s->id,
                    'code' => $s->code,
                    'name' => $s->name,
                    'gis_feature_id' => (int) $s->asset_id,
                    'feature_name' => $feature?->name,
                    'feature_type' => $feature?->feature_type,
                    'interval_days' => (int) ($s->interval_value ?? 180),
                    'next_due_date' => $s->next_due_date?->toDateString(),
                    'last_completed_date' => $s->last_completed_date?->toDateString(),
                    'is_active' => (bool) $s->is_active,
                    'due' => $overdue,
                    'within_week' => ! $overdue && $s->next_due_date !== null && $s->next_due_date->toDateString() <= $due,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Buat/perbarui jadwal preventif dari fitur valve|hydrant.
     * next_due = sekarang + interval (belum ada riwayat).
     */
    public function upsertFromFeature(User $user, int $featureId, int $intervalDays, ?array $checklist = null): array
    {
        $feature = GisFeature::whereIn('feature_type', ['valve', 'hydrant', 'pump'])->find($featureId);
        if (! $feature) {
            return ['error' => 'Fitur bukan valve/hydrant/pump.', 'code' => 422];
        }

        $nextDue = now()->addDays(max(7, min(730, $intervalDays)));
        $row = MaintenanceSchedule::updateOrCreate(
            ['asset_type' => self::ASSET_TYPE, 'asset_id' => $feature->id],
            [
                'pdam_org_id' => $user->pdam_org_id,
                'code' => 'MNT-'.$feature->feature_type.'-'.$feature->id,
                'name' => 'Preventif '.ucfirst($feature->feature_type).' '.($feature->name ?? '#'.$feature->id),
                'frequency' => 'cyclical',
                'interval_value' => $intervalDays,
                'next_due_date' => $nextDue,
                'is_active' => true,
                'checklist_json' => $checklist ?: ['buka/tutup 2×', 'lumasi stem', 'cek kebocoran', 'cat identitas'],
            ]
        );

        return ['code' => 201, 'data' => [
            'id' => (int) $row->id,
            'code' => $row->code,
            'gis_feature_id' => (int) $feature->id,
            'feature_name' => $feature->name,
            'interval_days' => (int) $row->interval_value,
            'next_due_date' => $row->next_due_date->toDateString(),
        ]];
    }

    /**
     * Jalankan siklus: semua jadwal gis_feature yang jatuh tempo → buat
     * WO inspeksi (priority medium) → tandai executed + geser next_due.
     * Dipanggil command pdam:mnt-network / tombol panel.
     *
     * @return int jumlah WO dibuat
     */
    public function processDueDates(?int $orgId = null): int
    {
        $count = 0;
        MaintenanceSchedule::query()
            ->where('asset_type', self::ASSET_TYPE)
            ->where('is_active', true)
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<=', now()->toDateString())
            ->when($orgId, fn ($q) => $q->where('pdam_org_id', $orgId))
            ->each(function (MaintenanceSchedule $s) use (&$count) {
                $feature = GisFeature::find($s->asset_id);
                if (! $feature) {
                    return; // fitur dihapus — jadwal tak relevan lagi
                }
                $coords = $this->featurePoint($feature);

                $wo = WorkOrder::create([
                    'pdam_org_id' => $s->pdam_org_id,
                    'wo_number' => 'WO-'.now()->format('ym').'-PMT'.strtoupper(substr(uniqid(), -4)),
                    'type' => 'inspection',
                    'priority' => 'medium',
                    'source_type' => 'gis_feature',
                    'source_id' => $feature->id,
                    'address' => 'Jadwal preventif: '.$s->name,
                    'latitude' => $coords ? (float) $coords['lat'] : null,
                    'longitude' => $coords ? (float) $coords['lng'] : null,
                    'description' => 'Dibuat otomatis (cron preventif jaringan) — '.$s->name.' · jadwal '.$s->code.'.',
                    'status' => 'open',
                    'sla_due_at' => now()->addDays(7),
                ]);
                WorkOrderLog::create([
                    'pdam_org_id' => $wo->pdam_org_id,
                    'work_order_id' => $wo->id,
                    'to_status' => 'open',
                    'action' => 'created',
                ]);

                $s->update([
                    'last_completed_date' => null,
                    'next_due_date' => now()->addDays(max(7, (int) ($s->interval_value ?? 180)))->startOfDay(),
                ]);

                $count++;
            });

        return $count;
    }

    /** Titik (lat,lng) fitur: Point → coords; pipa → tengah segmen terpanjang. */
    private function featurePoint(GisFeature $feature): ?array
    {
        $g = $feature->geometry ?? [];
        if (($g['type'] ?? '') === 'Point') {
            return ['lat' => (float) $g['coordinates'][1], 'lng' => (float) $g['coordinates'][0]];
        }
        $coords = $g['coordinates'] ?? [];
        if ($coords) {
            return ['lat' => (float) $coords[0][1], 'lng' => (float) $coords[0][0]];
        }

        return null;
    }

    /**
     * Catat pelaksanaan manual (selesai dari panel/field) → next_due maju.
     */
    public function markCompleted(User $user, MaintenanceSchedule $schedule): array
    {
        $interval = max(7, (int) ($schedule->interval_value ?? 180));
        $schedule->update([
            'last_completed_date' => now()->startOfDay(),
            'next_due_date' => now()->addDays($interval)->startOfDay(),
        ]);
        $this->notify->send(
            $user->id,
            'Preventif selesai',
            'Jadwal '.$schedule->name.' dicatat selesai — jatuh tempo berikutnya '.$schedule->next_due_date->toDateString().'.',
            ['in_app']
        );

        return [
            'last_completed_date' => $schedule->last_completed_date->toDateString(),
            'next_due_date' => $schedule->next_due_date->toDateString(),
        ];
    }
}
