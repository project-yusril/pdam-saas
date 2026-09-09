<?php

namespace App\Services\Geo;

use App\Models\GisFeature;
use App\Models\WorkOrder;

/**
 * PipeRiskService — PETA RISIKO RUAS PIPA (0..100) untuk prioritas penggantian:
 *  - bahan: Besi Tuang/Galvanis/Asbes = rawan; HDPE/PE = tahan (bobot config),
 *  - umur: 2026 − install_year (bobot per tahun, cap),
 *  - riwayat: jumlah WorkOrder repair/leakage yang PERNAH menimpa ruas ini
 *    (source_type = gis_feature — relasi WO↔GIS memang ada),
 *  - status fitur 'rusak' → bonus bobot.
 *
 * Semua komponen bisa disetel di config business.gis.risk (satu tempat, .env
 * untuk ambang). Tidak ada angka karangan: skor = rumus terbuka.
 */
class PipeRiskService
{
    /** Kontribusi maksimum per kelompok (0..100 total). */
    private const WEIGHTS = [
        'material' => 35,
        'age' => 30,
        'repairs' => 25,
        'broken' => 10,
    ];

    /** @var array<string,float> skor bahan 0..1 (1 = paling rawan) */
    private const MATERIAL_RISK = [
        'besi tuang' => 1.0, 'cast iron' => 1.0, 'cast-iron' => 1.0, 'cast' => 0.95,
        'galvanis' => 0.9, 'galvanized' => 0.9,
        'asbes' => 0.85, 'asbestos' => 0.85,
        'pvc' => 0.55,
        'steel' => 0.5, 'baja' => 0.5,
        'stainless' => 0.4,
        'hdpe' => 0.2, 'pe' => 0.25, 'mdpe' => 0.25, 'mpp' => 0.2,
    ];

    public function __construct(private NetworkGraphService $graph) {}

    /**
     * Skor semua pipa akti+f. Return per pipa:
     *   {pipe_id, name, score, level, factors{material,age,repairs,broken},
     *    material, install_year, age_years, repairs}
     * level: ≥70 kritis · ≥45 tinggi · ≥25 sedang · sisanya rendah.
     *
     * @return array{generated_at:string, config:array, pipes:array}
     */
    public function report(): array
    {
        $maxAge = (int) config('business.gis.risk_max_age_years', 60);
        $repairHalfLife = (int) config('business.gis.risk_repair_half_life', 3);
        $thresholds = (array) config('business.gis.risk_thresholds', ['tinggi' => 45, 'kritis' => 70]);

        $pipes = GisFeature::where('feature_type', 'pipe')
            ->get(['id', 'name', 'properties', 'status', 'geometry']);

        // Riwayat WO per ruas GIS (repair/leakage/emergency) — agregasi sekali query.
        $repairs = WorkOrder::query()
            ->where('source_type', 'gis_feature')
            ->whereIn('type', ['repair', 'leakage', 'emergency', 'complaint'])
            ->selectRaw('source_id, COUNT(*) AS n')
            ->groupBy('source_id')
            ->pluck('n', 'source_id');

        $now = (int) now()->year;

        $out = $pipes->map(function (GisFeature $p) use ($repairs, $maxAge, $repairHalfLife, $thresholds, $now) {
            $props = $p->properties ?? [];
            $material = mb_strtolower((string) ($props['material'] ?? ''));
            $matScore = 0.35; // bahan tidak dikenal → asumsi sedang (jangan 100% tebak)
            foreach (self::MATERIAL_RISK as $key => $val) {
                if ($material !== '' && str_contains($material, $key)) {
                    $matScore = $val;
                    break;
                }
            }

            $installYear = isset($props['install_year']) ? (int) $props['install_year'] : null;
            $age = $installYear ? max(0, min($maxAge, $now - $installYear)) : null;
            $ageScore = $age === null ? 0.5 : min(1.0, $age / max(1, $maxAge));

            $repairCount = (int) ($repairs[$p->id] ?? 0);
            $repairScore = 1 - exp(-$repairCount / max(1, $repairHalfLife)); // 0..1 saturasi

            $broken = $p->status === 'rusak' ? 1 : ($age !== null && $age > $maxAge * 0.8 ? 0.4 : 0);

            $factors = [
                'material' => round($matScore * self::WEIGHTS['material'], 1),
                'age' => round($ageScore * self::WEIGHTS['age'], 1),
                'repairs' => round($repairScore * self::WEIGHTS['repairs'], 1),
                'broken' => round($broken * self::WEIGHTS['broken'], 1),
            ];
            $score = min(100, (int) round(array_sum($factors)));
            $level = match (true) {
                $score >= $thresholds['kritis'] => 'kritis',
                $score >= $thresholds['tinggi'] => 'tinggi',
                $score >= 25 => 'sedang',
                default => 'rendah',
            };

            return [
                'pipe_id' => (int) $p->id,
                'name' => $p->name ?? ('#'.$p->id),
                'length_m' => $props['length_meters'] ?? ($props['segment_length_m'] ?? null),
                'material' => (string) ($props['material'] ?? ''),
                'install_year' => $installYear,
                'age_years' => $age,
                'repairs' => $repairCount,
                'factors' => $factors,
                'score' => $score,
                'level' => $level,
            ];
        })->values();

        $kritis = $out->filter(fn ($i) => $i['level'] === 'kritis')->count();
        $tinggi = $out->filter(fn ($i) => $i['level'] === 'tinggi')->count();

        return [
            'generated_at' => now()->toIso8601String(),
            'counts' => ['total' => $out->count(), 'kritis' => $kritis, 'tinggi' => $tinggi,
                'sedang' => $out->filter(fn ($i) => $i['level'] === 'sedang')->count()],
            'weights' => self::WEIGHTS,
            'config' => ['max_age_years' => $maxAge, 'thresholds' => $thresholds],
            'pipes' => $out->all(),
        ];
    }

    /** ID pipa berlevel kritis/tinggi — bahan pewarnaan garis di peta. */
    public function hotPipeIds(): array
    {
        $rep = $this->report();
        $ids = [];
        foreach ($rep['pipes'] as $i) {
            if (in_array($i['level'], ['kritis', 'tinggi'], true)) {
                $ids[$i['level']][] = (int) $i['pipe_id'];
            }
        }

        return $ids;
    }
}
