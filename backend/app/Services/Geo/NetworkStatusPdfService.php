<?php

namespace App\Services\Geo;

use App\Models\DmaZone;
use App\Models\PdamOrganization;
use App\Support\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * NetworkStatusPdfService — lembar status jaringan server-side (dompdf) utk
 * lampiran laporan/surel direktur: ringkasan kesehatan, risiko prioritas,
 * NRW per DMA, titik petugas. Bukan SVG — dompdf render tabel murni.
 * (Peta SVG interaktif tetap lewat /admin/network/print browser print.)
 */
class NetworkStatusPdfService
{
    public function __construct(
        private NetworkGraphService $graph,
        private PipeRiskService $risk,
        private NrwAnalysisService $nrw,
        private FieldLocationService $officers,
    ) {}

    /** @return string bytes PDF */
    public function render(?string $period = null): string
    {
        $period = $period ?: now()->startOfMonth()->subMonthNoOverflow()->format('Y-m');
        $org = PdamOrganization::find(TenantContext::id());
        $health = $this->graph->healthAudit();
        $risk = $this->risk->report();
        $priorities = collect($risk['pipes'])->whereIn('level', ['kritis', 'tinggi'])
            ->sortByDesc('score')->take(12)->values();
        $nrw = $this->nrw->summary($period);
        $mnf = $this->nrw->nightFlowAnalysis();
        $officerRows = $this->officers->activeOfficers((int) TenantContext::id());

        $css = 'body{font:10px Arial;color:#0f1729}h1{font-size:15px;color:#1d4ed8;margin:0 0 2pt}h2{font-size:11px;color:#1d4ed8;margin:12px 0 2pt;border-bottom:1px solid #94a3b8}
        .muted{color:#64748b;font-size:9px}table{width:100%;border-collapse:collapse}th{background:#1d4ed8;color:#fff;font-size:8px;padding:4px 6px;text-align:left;text-transform:uppercase}
        td{border-bottom:1px solid #e2e8f0;padding:4px 6px;font-size:9px}thead{display:table-header-group}
        .ok{color:#15803d;font-weight:bold}.warn{color:#b45309;font-weight:bold}.crit{color:#b91c1c;font-weight:bold}
        .grid{margin-top:8px}.grid .b{display:inline-block;width:48%;margin:3px 0;background:#f8fafc;border:1px solid #e2e8f0;padding:5px 8px;border-radius:6px}
        .head{border-bottom:2px solid #1d4ed8;padding-bottom:6px;margin-bottom:8px}.head .nm{font-size:13px;font-weight:bold}.head .st{color:#64748b;font-size:9px;margin-top:3px}
        .scorechip{float:right;font-weight:bold;border-radius:8px;padding:2px 10px;font-size:13px}';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'.$css.'</style></head><body>';
        $html .= '<div class="head"><div class="nm">PDAM '.e(strtoupper((string) ($org?->name ?? 'PDAM'))).'</div>';
        $html .= '<div class="st">Lembar Status Jaringan &bull; Periode '.$period.' &bull; dicetak '.now()->format('d/m/Y H:i').'</div>';
        $hs = $health['score'];
        $cls = $hs >= 85 ? 'ok' : ($hs >= 60 ? 'warn' : 'crit');
        $html .= '<div class="scorechip '.$cls.'">Kesehatan '.$hs.'/100</div></div>';

        // ── ringkasan angka
        $html .= '<div class="grid">';
        foreach ($health['counts'] as $label => $v) {
            $html .= '<span class="b"><b>'.(int) $v.'</b> '.ucwords(str_replace('_', ' ', $label)).'</span>';
        }
        $html .= '</div>';

        // ── temuan
        $html .= '<h2>Temuan integritas jaringan</h2><table><thead><tr><th>Check</th><th>Jml</th><th>Level</th><th>Contoh item</th></tr></thead><tbody>';
        foreach ($health['issues'] as $iss) {
            if (! $iss['count']) {
                continue;
            }
            $sev = $iss['severity'] === 'crit' ? 'crit' : ($iss['severity'] === 'warn' ? 'warn' : '');
            $samples = collect($iss['items'])->take(4)
                ->map(fn ($i) => $i['name'] ?? $i['code'] ?? ('#'.($i['id'] ?? '?')))->implode(', ');
            $html .= '<tr><td>'.e($iss['label']).'</td><td><span class="'.$sev.'">'.(int) $iss['count'].'</span></td>'
                .'<td>'.e($iss['severity']).'</td><td>'.e($samples).'</td></tr>';
        }
        if (! array_filter(array_column($health['issues'], 'count'))) {
            $html .= '<tr><td colspan="4" class="ok">Tidak ada temuan ✔</td></tr>';
        }
        $html .= '</tbody></table>';

        // ── prioritas ganti
        $html .= '<h2>Prioritas penggantian pipa (risiko tertinggi)</h2>';
        if ($priorities->isEmpty()) {
            $html .= '<p class="ok">Belum ada pipa level kritis/tinggi ✔</p>';
        } else {
            $html .= '<table><thead><tr><th>#</th><th>Ruas</th><th>Skor</th><th>Bahan</th><th>Umur</th><th>WO repair</th></tr></thead><tbody>';
            foreach ($priorities as $i => $p) {
                $html .= '<tr><td>'.($i + 1).'</td><td>'.e($p['name']).'</td><td class="crit">'.(int) $p['score'].'</td>'
                    .'<td>'.e($p['material'] ?: '–').'</td><td>'.($p['install_year'] ? (now()->year - $p['install_year']).' thn' : '–').'</td>'
                    .'<td>'.($p['repairs'] ?: '–').'</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        // ── NRW per DMA
        $html .= '<h2>NRW per DMA (periode '.$period.')</h2>';
        $html .= '<table><thead><tr><th>DMA</th><th>Status</th><th>Suplai m³</th><th>Tertagih m³</th><th>Kehilangan m³</th><th>NRW%</th></tr></thead><tbody>';
        foreach ($nrw as $d) {
            $cls2 = ($d['status'] ?? null) === 'kritis' ? 'crit' : (($d['status'] ?? null) === 'waspada' ? 'warn' : (($d['status'] ?? null) === 'baik' ? 'ok' : ''));
            $html .= '<tr><td>'.e($d['code']).' '.e((string) ($d['name'] ?? '')).'</td><td class="'.$cls2.'">'.e((string) ($d['status'] ?? 'belum')).'</td><td>'.number_format((float) $d['system_input_m3']).'</td>'
                .'<td>'.number_format((float) $d['billed_metered_m3']).'</td><td>'.number_format((float) $d['water_losses_m3']).'</td><td>'.e((string) ($d['nrw_percentage'] ?? '–')).'</td></tr>';
        }
        $html .= '</tbody></table>';

        // ── MNF malam
        $html .= '<h2>MNF malam 02:00–04:00 vs baseline (deteksi bocor halus)</h2>';
        $html .= '<table><thead><tr><th>DMA</th><th>MNF m³/hari</th><th>Baseline m³/hari</th><th>% Baseline</th><th>Status</th></tr></thead><tbody>';
        foreach ($mnf as $m) {
            $cls3 = match ($m['status']) { 'merah' => 'crit', 'waspada' => 'warn', 'baik' => 'ok', default => '' };
            $html .= '<tr><td>'.e($m['code']).'</td><td>'.($m['mnf_m3day'] ?? '–').' <span class="muted">('.$m['n_samples'].' baca)</span></td>'
                .'<td>'.($m['base_m3day'] ?? '–').($m['base_source'] === 'estimasi_koneksi' ? ' <span class="muted">(est)</span>' : '').'</td>'
                .'<td>'.($m['pct_of_base'] ?? '–').'%</td><td class="'.$cls3.'">'.e($m['status']).'</td></tr>';
        }
        $html .= '</tbody></table>';

        // ── petugas
        $html .= '<h2>Petugas lapangan (terakhir lapor)</h2>';
        if ($officerRows) {
            $html .= '<table><thead><tr><th>Nama</th><th>Posisi</th><th>Usia</th><th>Status</th></tr></thead><tbody>';
            foreach ($officerRows as $o) {
                $html .= '<tr><td>'.e($o['name']).'</td><td>'.round($o['lat'], 5).', '.round($o['lng'], 5).' <span class="muted">(±'.($o['accuracy_m'] ? (int) $o['accuracy_m'] : '?').'m)</span></td>'
                    .'<td>'.(($o['age_seconds'] < 3600) ? round($o['age_seconds'] / 60).' mnt' : round($o['age_seconds'] / 3600).' jam').'</td>'
                    .'<td class="'.($o['online'] ? 'ok' : '').'">'.($o['online'] ? 'ONLINE' : 'OFFLINE').'</td></tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<p class="muted">Belum ada petugas yang pernah melaporkan lokasi GPS.</p>';
        }

        $html .= '<p class="muted" style="margin-top:14px;border-top:1px solid #e2e8f0;padding-top:6px">Dibuat otomatis oleh Sistem Informasi Manajemen PDAM. Arah aliran: graf terarah BFS sumber &bull; Risiko: bahan+umur+riwayat WO &bull; MNF &amp; tren: cron bulanan.</p></body></html>';

        return Pdf::loadHTML($html)->setPaper('a4', 'portrait')->output();
    }
}
