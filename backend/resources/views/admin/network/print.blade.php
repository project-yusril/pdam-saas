<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Lembar Status Jaringan — {{ $org }}</title>
@php
    $W = 1000; $H = 560;
    $bounds ? ($cf = cos(deg2rad(($bounds['maxLat'] + $bounds['minLat']) / 2))) : ($cf = 1);
    $sx = $bounds ? $W / max(1e-7, ($bounds['maxLng'] - $bounds['minLng']) * $cf) : 0;
    $sy = $bounds ? $H / max(1e-7, ($bounds['maxLat'] - $bounds['minLat'])) : 0;
    $s = $bounds ? min($sx, $sy) : 0;
    $proj = function (array $c) use ($bounds, $cf, $W, $H, $s) {
        if (! $bounds) { return null; }
        $x = $W / 2 + ($c[0] * $cf - ($bounds['maxLng'] + $bounds['minLng']) / 2 * $cf) * $s;
        $y = $H / 2 - ($c[1] - ($bounds['maxLat'] + $bounds['minLat']) / 2) * $s;

        return [round($x, 1), round($y, 1)];
    };
@endphp
<style>
  @page { size: A4 landscape; margin: 10mm; }
  * { box-sizing: border-box; }
  body { font: 11px/1.45 Arial, Helvetica, sans-serif; color: #0f1729; margin: 0; padding: 10px 14px; background: #fff; }
  .noprint { position: fixed; right: 14px; top: 10px; }
  .noprint button { padding: 8px 14px; border-radius: 8px; border: 0; background: #1d4ed8; color: #fff; font-weight: 700; cursor: pointer; }
  header { display: flex; gap: 16px; align-items: baseline; border-bottom: 2px solid #1d4ed8; padding-bottom: 6px; margin-bottom: 10px; }
  header h1 { margin: 0; font-size: 16px; color: #1d4ed8; flex: 1; }
  header .who { font-size: 12px; }
  header .stamp { color: #475569; font-size: 10px; }
  .grid { display: flex; gap: 14px; }
  .map-box { flex: 1.2; min-width: 0; }
  .side { width: 360px; flex-shrink: 0; }
  svg.net-box { border: 1px solid #cbd5e1; background: #fbfdff; width: 100%; height: auto; display: block; }
  .legend { font-size: 9.5px; color: #475569; margin-top: 4px; }
  .legend b { display: inline-block; width: 18px; height: 4px; vertical-align: middle; margin: 0 3px 0 8px; }
  .score-chip { float: right; font-size: 15px; padding: 2px 9px; border-radius: 6px; }
  table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
  th, td { border-bottom: 1px solid #e2e8f0; padding: 3px 6px; text-align: left; font-size: 10px; }
  th { background: #eef2ff; text-transform: uppercase; font-size: 8.5px; letter-spacing: .3px; }
  .k { text-align: right; font-variant-numeric: tabular-nums; }
  .tag { font-weight: 700; padding: 0 6px; border-radius: 8px; font-size: 9px; }
  .t-kritis { background: #fee2e2; color: #991b1b; }
  .t-tinggi { background: #ffedd5; color: #9a3412; }
  .t-baik { background: #dcfce7; color: #166534; }
  .t-waspada { background: #fef3c7; color: #92400e; }
  .t-nihil, .t-belum { background: #f1f5f9; color: #475569; }
  h2.sub { font-size: 11px; margin: 10px 0 2px; color: #1d4ed8; }
  .foot { margin-top: 10px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 9px; padding-top: 5px; }
  @media print { .noprint { display: none; } }
</style>
</head>
<body>
<div class="noprint"><a href="{{ route('admin.network.status.pdf') }}" style="display:inline-block;padding:8px 14px;border-radius:8px;background:#0d9488;color:#fff;font-weight:700;text-decoration:none;margin-right:8px">📄 Unduh PDF</a><button onclick="window.print()">🖨 Cetak / simpan PDF</button></div>

<header>
  <div>
    <h1>PDAM {{ strtoupper($org) }}</h1>
    <div class="stamp">Lembar Status Jaringan &bull; Area {{ $period }} &bull; dicetak {{ now()->format('d/m/Y H:i') }}</div>
  </div>
</header>

<div class="grid">
  <div class="map-box">
    <svg class="net-box" viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="xMidYMid meet">
      @if (! $bounds)
        <text x="{{ $W / 2 }}" y="{{ $H / 2 }}" text-anchor="middle" fill="#64748b" style="font-size:14px">Jaringan belum punya geometri — belum ada pipa / DMA terekam.</text>
      @endif
      @foreach ($dmaPolys as $d)
        @php
          $ring = array_values(array_filter(array_map($proj, $d['ring'] ?? [])));
        @endphp
        @if (count($ring) > 2)
          <polygon points="{{ implode(' ', array_map(fn ($p) => "{$p[0]},{$p[1]}", $ring)) }}" fill="#93c5fd" fill-opacity="0.12" stroke="#3b82f6" stroke-width="1" stroke-dasharray="5 3" />
        @endif
      @endforeach
      @php $lc = ['kritis' => '#dc2626', 'tinggi' => '#ea580c', 'sedang' => '#eab308']; @endphp
      @foreach ($pipes as $p)
        @php
          $pts = array_values(array_filter(array_map($proj, $p['pts'] ?? [])));
          if ($p['status'] === 'rusak') { $col = '#dc2626'; }
          elseif ($p['level'] === 'kritis') { $col = $lc['kritis']; }
          elseif ($p['level'] === 'tinggi') { $col = $lc['tinggi']; }
          elseif ($p['level'] === 'sedang') { $col = $lc['sedang']; }
          elseif ($p['status'] === 'rencana') { $col = '#64748b'; }
          else { $col = '#1d4ed8'; }
          $wd = in_array($p['level'] ?? null, ['kritis', 'tinggi'], true) ? 2.4 : 1.4;
        @endphp
        @if (count($pts) > 1)
          <polyline points="{{ implode(' ', array_map(fn ($q) => "{$q[0]},{$q[1]}", $pts)) }}" fill="none" stroke="{{ $col }}" stroke-width="{{ $wd }}" stroke-linecap="round" {{ $p['status'] === 'rencana' ? 'stroke-dasharray="4 3"' : '' }} />
        @endif
      @endforeach
      @php $dc = ['pump' => '#eab308', 'reservoir' => '#14b8a6', 'intake' => '#0ea5e9', 'treatment' => '#a78bfa', 'valve' => '#1d4ed8', 'hydrant' => '#f97316']; @endphp
      @foreach ($nodes as $n)
        @php $pt = $proj($n['pt'] ?? [0, 0]); @endphp
        @if ($pt)
          @if ($n['type'] === 'valve')
            <circle cx="{{ $pt[0] }}" cy="{{ $pt[1] }}" r="3.2" fill="{{ $n['status'] === 'closed' ? '#ef4444' : '#1d4ed8' }}" stroke="#fff" stroke-width=".8" />
          @elseif ($n['type'] !== 'junction')
            <circle cx="{{ $pt[0] }}" cy="{{ $pt[1] }}" r="{{ $n['type'] === 'hydrant' ? 2.4 : 3.8 }}" fill="{{ $dc[$n['type']] ?? '#64748b' }}" stroke="#fff" stroke-width=".8" />
          @endif
        @endif
      @endforeach
    </svg>
    <div class="legend">
      Jaringan {{ count($pipes) }} pipa, {{ count($nodes) }} node, {{ count($dmaPolys) }} DMA — poligon biru = DMA aktif.
      <b style="background:#1d4ed8"></b> normal <b style="background:#eab308"></b> sedang <b style="background:#ea580c"></b> tinggi <b style="background:#dc2626"></b> kritis/rusak <b style="background:repeating-linear-gradient(90deg,#64748b 0 4px,#fff 4px 6px)"></b> rencana
      &nbsp;● pompa ● reservoir ● valve ● hydrant
    </div>
  </div>

  <div class="side">
    <h2 class="sub" style="display:inline">Kesehatan jaringan</h2>
    <span class="score-chip {{ $health['score'] >= 85 ? 't-baik' : ($health['score'] >= 60 ? 't-waspada' : 't-kritis') }}">{{ $health['score'] }}/100</span>
    <table>
      @foreach ($health['issues'] as $i => $issue)
        <tr><td><span class="tag t-{{ $issue['count'] === 0 ? 'baik' : ($issue['severity'] === 'crit' ? 'kritis' : 'waspada') }}">{{ $issue['count'] }}</span></td><td>{{ $issue['label'] }}</td></tr>
      @endforeach
      @if (! $health['issues'])
        <tr><td colspan="2" style="color:#15803d">Tidak ada temuan ✔</td></tr>
      @endif
    </table>

    <h2 class="sub">Prioritas penggantian pipa (top {{ min(15, count($priorities)) }})</h2>
    @if ($priorities)
      <table>
        <tr><th>#</th><th>Ruas</th><th>Skor</th><th>Bahan</th><th>Umur</th><th>WO repair</th></tr>
        @foreach ($priorities as $p)
          <tr>
            <td>{{ $loop->iteration }}</td><td>{{ $p['name'] }}</td>
            <td class="k"><span class="tag t-{{ $p['level'] === 'kritis' ? 'kritis' : 'tinggi' }}">{{ $p['score'] }}</span></td>
            <td>{{ $p['material'] ?: '–' }}</td>
            <td class="k">{{ $p['install_year'] ? (now()->year - $p['install_year']).' yr' : '–' }}</td>
            <td class="k">{{ $p['repairs'] ?: '–' }}</td>
          </tr>
        @endforeach
      </table>
    @else
      <p class="muted" style="color:#15803d">Belum masuk 5 besar — aman dulu.</p>
    @endif

    <h2 class="sub">NRW/MDmA {{ $period }}</h2>
    <table>
      <tr><th>DMA</th><th>Kelas</th><th>NRW %</th><th>Suplai m³</th><th>Tertagih m³</th></tr>
      @forelse ($nrwRows as $d)
        <tr>
          @php $cls = match ($d['status'] ?? null) { 'baik' => 'baik', 'waspada' => 'waspada', 'kritis' => 'kritis', default => 'nihil' }; @endphp
          <td>{{ $d['code'] }} {{ $d['name'] ? '— '.$d['name'] : '' }}</td>
          <td><span class="tag t-{{ $cls }}">{{ $d['status'] ?: 'belum' }}</span></td>
          <td class="k">{{ $d['nrw_percentage'] ?? '–' }}</td>
          <td class="k">{{ $d['system_input_m3'] ? number_format($d['system_input_m3'], 0, ',', '.') : '–' }}</td>
          <td class="k">{{ $d['billed_metered_m3'] ? number_format($d['billed_metered_m3'], 0, ',', '.') : '–' }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="muted">Belum ada NRW tersimpan — jalankan <code>pdam:nrw-monthly</code>.</td></tr>
      @endforelse
    </table>
  </div>
</div>

<div class="foot">Dibuat dari Dashboard GIS Jaringan PDAM · Arah aliran graf-terarah · Audit node/edge DMA · Prioritas dari material + umur + riwayat WO · Tren NRW tersimpan per bulan di nrw_balances · Catatan: lembar ini A4 landscape untuk arsip/keputusan; cetak lalu PDF utk kirim manajemen.</div>
</body>
</html>
