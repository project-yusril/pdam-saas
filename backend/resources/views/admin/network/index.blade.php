@extends('admin.layouts.app')

@section('title', 'GIS Jaringan Perpipaan')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
<style>
    .net-wrap { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: .9rem; align-items: start; }
    #map-net { height: 70vh; background: #e8edf3; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); z-index: 0; }
    .net-tools { display: flex; gap: .45rem; flex-wrap: wrap; align-items: center; margin-bottom: .6rem; }
    .net-select, .net-input { border: 1px solid #cfd6e2; border-radius: 6px; font-size: .78rem; padding: .35rem .5rem; background: #fff; }
    .btn-xs { font-size: .72rem; padding: .3rem .6rem; border: 1px solid #cfd6e2; background: #fff; border-radius: 6px; cursor: pointer; }
    .btn-xs:hover { background: #f0f2f8; }
    .btn-xs.on { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
    .btn-xs.red { background: #b91c1c; color: #fff; border-color: #b91c1c; }
    .btn-xs.green { background: #15803d; color: #fff; border-color: #15803d; }
    .layer-toggle { font-size: .74rem; margin-bottom: .5rem; }
    .layer-toggle label { margin-right: .7rem; color: #2b2f45; }
    #toast-net { position: fixed; top: 14px; left: 50%; transform: translateX(-50%); padding: .5rem 1rem; border-radius: 8px;
        color: #fff; font-size: .82rem; display: none; z-index: 4000; box-shadow: 0 4px 12px rgba(0,0,0,.25); }
    .side-card { background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: .95rem 1.05rem; margin-bottom: .9rem; }
    .side-card h2 { font-size: .95rem; margin-bottom: .45rem; }
    .muted { color: #6b7280; font-size: .74rem; }
    .ins-kv { font-size: .78rem; margin-bottom: .3rem; } .ins-kv b { color: #374151; }
    .nrw-table { font-size: .76rem; } .nrw-table th, .nrw-table td { padding: .4rem .5rem; }
    .badge-s { font-size: .66rem; padding: .12rem .5rem; border-radius: 999px; color: #fff; }
    .b-baik { background: #15803d; } .b-waspada { background: #b45309; } .b-kritis { background: #b91c1c; } .b-nihil { background: #94a3b8; }
    .legend-row { display: flex; align-items: center; gap: .45rem; font-size: .76rem; padding: .1rem 0; }
    .lg-line { width: 18px; height: 3px; border-radius: 2px; }
    .lg-dot { width: 10px; height: 10px; border-radius: 50%; border: 1.5px solid #fff; box-shadow: 0 0 0 1px #999; }
    .flow-arrow { background: transparent; border: none; }
    .flow-arrow span { position: absolute; left: 50%; top: 50%; color: #0284c7; font-size: 11px; font-weight: 700; text-shadow: 0 0 3px #fff; }
</style>
@endpush

@section('content')
<h1>GIS Jaringan Perpipaan <small style="font-size:.85rem;color:#6b7280;">— editor, isolasi bocor, DMA &amp; NRW (OSM gratis)</small></h1>

<div class="net-wrap">
    <div>
        <div class="net-tools">
            <select id="sel-type" class="net-select">
                <option value="pipe">🛢 Gambar pipa (LineString)</option>
                <option value="valve">🔻 Valve</option>
                <option value="junction">➕ Junction</option>
                <option value="hydrant">🧯 Hydrant</option>
                <option value="pump">⚙ Pompa</option>
                <option value="reservoir">🟩 Reservoir</option>
                <option value="dma">⬠ Area DMA (polygon)</option>
            </select>
            <button type="button" class="btn-xs" id="btn-draw">✏️ Mulai gambar</button>
            <button type="button" class="btn-xs" id="btn-cancel-draw">Batal</button>
            <span class="muted">ujung pipa auto-nyambung ke node terdekat (≤12 m) / junction baru</span>
            <span style="margin-left:auto"></span>
            <button type="button" class="btn-xs" id="btn-refresh">↻</button>
            <button type="button" class="btn-xs" id="btn-fit">⤢</button>
        </div>
        <div class="layer-toggle">
            <label><input type="checkbox" id="lay-pipes" checked> Pipa</label>
            <label><input type="checkbox" id="lay-nodes" checked> Node</label>
            <label><input type="checkbox" id="lay-flow" checked> Arah aliran</label>
            <label><input type="checkbox" id="lay-officers" checked> 👷 Petugas live</label>
            <label><input type="checkbox" id="lay-dma"> DMA</label>
            <label><input type="checkbox" id="lay-impact" checked> Area terdampak</label>
            <label><input type="checkbox" id="lay-customers"> Pelanggan</label>
        </div>
        <div id="map-net"></div>
    </div>

    <aside>
        <div class="side-card">
            <h2>Ringkasan</h2>
            <div class="ins-kv">Pipa: <b id="c-pipes">–</b> (rusak <b id="c-rusak">–</b>) · Node: <b id="c-nodes">–</b> · DMA: <b id="c-dma">–</b></div>
            <div class="legend-row"><span class="lg-line" style="background:#1d4ed8"></span> Pipa aktif</div>
            <div class="legend-row"><span class="lg-line" style="background:#dc2626"></span> Pipa rusak (insiden)</div>
            <div class="legend-row"><span class="lg-line" style="background:#64748b;height:0;border-top:2px dashed #64748b"></span> Pipa rencana</div>
            <div class="legend-row"><span class="lg-dot" style="background:#1d4ed8"></span> Valve terbuka</div>
            <div class="legend-row"><span class="lg-dot" style="background:#ef4444"></span> Valve tertutup</div>
            <div class="legend-row"><span class="lg-dot" style="background:#eab308"></span> Pompa · <span class="lg-dot" style="background:#14b8a6"></span> Reservoir · <span class="lg-dot" style="background:#f97316"></span> Hydrant</div>
            <div class="legend-row"><span style="color:#0284c7;font-weight:700">➜</span> Arah aliran (BFS dari sumber)</div>
        </div>

        <div class="side-card" id="sel-card">
            <h2>Fitur terpilih</h2>
            <p class="muted" id="sel-empty">Klik pipa / node pada peta.</p>
            <div id="sel-box" style="display:none">
                <div class="ins-kv"><b id="sel-name"></b> <span class="muted" id="sel-meta"></span></div>
                <div style="display:flex;flex-wrap:wrap;gap:.35rem;margin:.45rem 0">
                    <button type="button" class="btn-xs" id="btn-toggle-valve" style="display:none">💧 Buka/Tutup</button>
                    <button type="button" class="btn-xs red" id="btn-isolate" style="display:none">🚱 Isolasi bocor</button>
                    <button type="button" class="btn-xs green" id="btn-incident" style="display:none">📞 Insiden → WO</button>
                    <button type="button" class="btn-xs" id="btn-edit-props">✎ Edit</button>
                    <button type="button" class="btn-xs red" id="btn-delete">🗑</button>
                </div>
                <div id="prop-form" style="display:none;font-size:.76rem;background:#f8fafc;border:1px solid #e5e9f0;border-radius:8px;padding:.6rem">
                    <label>Nama <input class="net-input" id="in-name" style="width:100%"></label>
                    <div style="display:flex;gap:.5rem;margin-top:.4rem;">
                        <label>Status <select class="net-select" id="in-status">
                            <option value="active">aktif</option><option value="rusak">rusak</option>
                            <option value="rencana">rencana</option><option value="closed" disabled>closed (valve)</option>
                        </select></label>
                    </div>
                    <div style="display:flex;gap:.5rem;margin-top:.4rem;">
                        <label>Material <input class="net-input" id="in-mat" style="width:90px"></label>
                        <label>Ø mm <input class="net-input" id="in-dia" type="number" style="width:70px"></label>
                    </div>
                    <div style="display:flex;gap:.4rem;margin-top:.55rem;">
                        <button type="button" class="btn-xs green" id="btn-prop-save">Simpan</button>
                        <button type="button" class="btn-xs" id="btn-prop-cancel">Batal</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="side-card" id="iso-card" style="display:none">
            <h2>Hasil isolasi</h2>
            <div id="iso-body" style="font-size:.78rem"></div>
            <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.55rem;align-items:center">
                <button type="button" class="btn-xs green" id="btn-wo">📋 Work Order darurat</button>
                <button type="button" class="btn-xs" id="btn-dispatch" style="background:#16a34a;color:#fff;border-color:#16a34a">🚑 Dispatch petugas terdekat</button>
                Prioritas <select class="net-select" id="wo-priority">
                    <option value="urgent">urgent</option><option value="high">high</option>
                    <option value="medium" selected>medium</option><option value="low">low</option>
                </select>
                Petugas <select class="net-select" id="wo-assign"><option value="">—</option></select>
            </div>
        </div>

        <div class="side-card" id="officer-card">
            <h2>Petugas lapangan <button type="button" class="btn-xs" id="btn-officer-refresh" style="float:right">↻</button></h2>
            <div id="officer-list"><span class="muted">memuat…</span></div>
            <p class="muted">Titik GPS asli dari aplikasi mobile (lapor lokasi / submit survey / baca meter). Klik pipa → <b>Isolasi</b> atau <b>Dispatch</b>.</p>
        </div>

        <div class="side-card">
            <h2>NRW per DMA <button type="button" class="btn-xs" id="btn-nrw-refresh" style="float:right">↻</button></h2>
            <table class="nrw-table">
                <thead><tr><th>DMA</th><th>Periode</th><th>Suplai m³</th><th>Terbaca m³</th><th>NRW%</th><th>Tren 6 bln</th></tr></thead>
                <tbody id="nrw-body"><tr><td colspan="6" class="muted">memuat…</td></tr></tbody>
            </table>
            <p class="muted">Hitung otomatis dari suplai (rata-rata flow DMA) vs konsumsi tagihan pelanggan dalam polygon. Status: ≤20% baik · 20–30% waspada · &gt;30% kritis. Tren dari <code>nrw_balances</code> (cron <code>pdam:nrw-monthly</code> tgl 1).</p>
        </div>

        <div class="side-card">
            <h2>MNF — bocor malam <button type="button" class="btn-xs" id="btn-mnf-refresh" style="float:right">↻</button></h2>
            <table class="nrw-table">
                <thead><tr><th>DMA</th><th>Flow 02–04</th><th>m³/hari</th><th>% baseline</th><th>L/kon/hari</th></tr></thead>
                <tbody id="mnf-body"><tr><td colspan="5" class="muted">memuat…</td></tr></tbody>
            </table>
            <p class="muted">Minimum Night Flow jam&nbsp;02:00–04:00 (7&nbsp;bln terakhir) vs baseline DMA. Ambang <b id="mnf-threshold">15</b>%: di atas = <b style="color:#b45309">waspada</b>, &gt;2× = <b style="color:#b91c1c">merah</b> (polygon DMA ikut berwarna saat layer DMA aktif). DMA merah → suspect bocor halus / sambungan ilegal.</p>
        </div>
    </aside>
</div>

<div id="toast-net"></div>
<meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script>
(function () {
    'use strict';
    const BASE = "{{ url('admin/network') }}";
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (id) => document.getElementById(id);
    const toast = (msg, ok = true) => { const t = $('toast-net'); t.textContent = msg; t.style.background = ok ? '#15803d' : '#b91c1c'; t.style.display = 'block'; clearTimeout(toast._t); toast._t = setTimeout(() => t.style.display = 'none', 3600); };
    const num = (v) => (v === null || v === undefined || v === '' ) ? '-' : Number(v).toLocaleString('id-ID');

    async function api(path, opts = {}) {
        opts.headers = Object.assign({ 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, opts.headers || {});
        if (opts.body && typeof opts.body === 'object') { opts.body = JSON.stringify(opts.body); opts.headers['Content-Type'] = 'application/json'; }
        const res = await fetch(BASE + '/' + path, opts);
        let json = null; try { json = await res.json(); } catch (e) { /* non-json */ }
        return { ok: res.ok, status: res.status, json };
    }
    const toGeo = (ll) => [ll.lng, ll.lat];                    // Leaflet → GeoJSON [lon,lat]
    const toLL = (c) => [c[1], c[0]];                          // GeoJSON[lon,lat] → Leaflet [lat,lng]
    const ringToGeo = (latlngs) => { const r = latlngs.map(toGeo); r.push(r[0]); return r; };

    // ── Peta ───────────────────────────────────────────────────────────────
    const map = L.map('map-net', { preferCanvas: true }).setView([1.45, 109.35], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);
    L.control.scale().addTo(map);

    const groups = {
        pipes: L.layerGroup().addTo(map),
        nodes: L.layerGroup().addTo(map),
        flows: L.layerGroup().addTo(map),
        officers: L.layerGroup().addTo(map),
        dmas: L.layerGroup(),
        impact: L.layerGroup().addTo(map),
        customers: L.layerGroup(),
    };
    let layers = null;          // cache dari layers.json
    let selected = null;        // {kind:'pipe'|'node', feature}
    let lastIsolate = null;
    let officersLive = [];      // cache technicians.json utk marker + daftar
    let mnfStatus = {};         // dma_id → {status, pct_of_base, ...} dari mnf.json
    let trendByDma = {};        // dma_id → periods[] dari nrw-trend.json

    const COLORS = { valve: '#1d4ed8', junction: '#94a3b8', hydrant: '#f97316', pump: '#eab308', reservoir: '#14b8a6', intake: '#0ea5e9', treatment: '#a78bfa' };
    const pipeStyle = (p) => {
        const s = p.properties.status;
        const dia = Number(p.properties.diameter_mm || 0);
        if (s === 'rusak')   return { color: '#dc2626', weight: 4, opacity: .95 };
        if (s === 'rencana') return { color: '#64748b', weight: dia >= 110 ? 3 : 1.5, opacity: .85, dashArray: '6 6' };
        return { color: dia >= 300 ? '#1d4ed8' : (dia >= 110 ? '#2563eb' : '#60a5fa'),
                 weight: dia >= 300 ? 5 : (dia >= 110 ? 3 : 1.6), opacity: dia >= 110 ? .9 : .6 };
    };

    function drawLayers(data) {
        layers = data;
        ['pipes', 'nodes', 'flows', 'dmas'].forEach(g => groups[g].clearLayers());
        (data.pipes || []).forEach((f) => {
            const line = L.polyline((f.geometry.coordinates || []).map(toLL), pipeStyle(f)).addTo(groups.pipes);
            line.on('click', (e) => { L.DomEvent.stopPropagation(e); select('pipe', f); });
            if (Number(f.properties.diameter_mm || 0) >= 110) {
                const len = f.properties.length_meters ? ' ≈ ' + Math.round(f.properties.length_meters) + ' m' : '';
                line.bindTooltip((f.properties.name || f.properties.feature_type) + len, { sticky: true });
            }
        });
        (data.nodes || []).forEach((f) => {
            const closed = f.properties.status === 'closed';
            const m = L.circleMarker(toLL(f.geometry.coordinates), {
                radius: 7, color: '#fff', weight: 1.5,
                fillColor: closed ? '#ef4444' : (COLORS[f.properties.feature_type] || '#6b7280'),
                fillOpacity: 1,
            }).addTo(groups.nodes);
            m.on('click', (e) => { L.DomEvent.stopPropagation(e); select('node', f); });
            m.bindTooltip((f.properties.name || f.properties.feature_type) + (closed ? ' [CLOSED]' : ''), { direction: 'top' });
        });
        (data.dmas || []).forEach((f) => {
            const ring = ((f.geometry.coordinates || [[]])[0]).map(toLL);
            const id = f.properties.feature_id, m = mnfStatus[id];
            const style = (m && m.status === 'merah') ? { color: '#b91c1c', fillColor: '#ef4444', fillOpacity: .16 }
                : (m && m.status === 'waspada') ? { color: '#d97706', fillColor: '#f59e0b', fillOpacity: .10 }
                : { color: '#0891b2', fillColor: '#06b6d4', fillOpacity: .05 };
            L.polygon(ring, Object.assign(style, { weight: 1.6 }))
                .addTo(groups.dmas)
                .bindPopup('DMA: ' + (f.properties.name || '') + (m && m.pct_of_base !== null
                    ? '<br>MFN 02–04: ' + m.mnf_m3day + ' m³/hari = <b>' + m.pct_of_base + '%</b> baseline → ' + m.status
                    : ''));
        });
        const nodePos = {};
        (data.nodes || []).forEach(n => { (n.geometry?.coordinates || []).length >= 2 && (nodePos[n.properties.feature_id] = [n.geometry.coordinates[1], n.geometry.coordinates[0]]); });
        (data.edges || []).forEach((ed) => {
            if (!ed.flow) return;
            const a = nodePos[ed.flow.from], b = nodePos[ed.flow.to];
            if (!a || !b || (a[0] === b[0] && a[1] === b[1])) return;
            const ang = Math.atan2(b[1] - a[1], b[0] - a[0]) * 180 / Math.PI;
            const mid = [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2];
            L.marker(mid, {
                icon: L.divIcon({ className: 'flow-arrow', html: '<span style="transform:translate(-50%,-50%) rotate(' + ang.toFixed(1) + 'deg)">➜</span>', iconSize: [14, 14], iconAnchor: [0, 0] }),
                interactive: false, keyboard: false,
            }).addTo(groups.flows);
        });
        $('c-pipes').textContent = (data.pipes || []).length;
        $('c-rusak').textContent = (data.pipes || []).filter(p => p.properties.status === 'rusak').length;
        $('c-nodes').textContent = (data.nodes || []).length;
        $('c-dma').textContent = (data.dmas || []).length;
    }

    function fitNetwork() {
        if (!layers) return;
        const pts = [];
        (layers.pipes || []).forEach(f => (f.geometry.coordinates || []).forEach(c => pts.push(toLL(c))));
        (layers.nodes || []).forEach(f => pts.push(toLL(f.geometry.coordinates)));
        if (pts.length > 1) map.fitBounds(L.latLngBounds(pts).pad(.06));
    }

    async function loadLayers() { const { ok, json } = await api('layers.json'); if (ok) drawLayers(json); else toast('Gagal memuat jaringan', false); }

    // ── Panel seleksi ──────────────────────────────────────────────────────
    function select(kind, f) {
        selected = { kind, f };
        $('sel-empty').style.display = 'none';
        $('sel-box').style.display = 'block';
        $('prop-form').style.display = 'none';
        const p = f.properties;
        $('sel-name').textContent = p.name || (p.feature_type + ' #' + p.feature_id);
        $('sel-meta').textContent = '· ' + p.feature_type + ' · ' + (p.status || '-')
            + (p.length_meters ? ' · ' + Math.round(p.length_meters) + ' m' : '')
            + (p.diameter_mm ? ' · Ø' + p.diameter_mm : '') + (p.material ? ' · ' + p.material : '');
        $('btn-toggle-valve').style.display = p.feature_type === 'valve' ? '' : 'none';
        $('btn-isolate').style.display = kind === 'pipe' ? '' : 'none';
        $('btn-incident').style.display = kind === 'pipe' ? '' : 'none';
    }
    map.on('click', () => { $('sel-box').style.display = 'none'; $('sel-empty').style.display = 'block'; selected = null; });

    $('btn-edit-props').onclick = () => {
        if (!selected) return; const p = selected.f.properties;
        $('in-name').value = p.name || '';
        $('in-status').value = ['active', 'rusak', 'rencana'].includes(p.status) ? p.status : 'active';
        $('in-status').querySelector('option[value=closed]').disabled = !(p.feature_type === 'valve');
        $('in-mat').value = p.material || ''; $('in-dia').value = p.diameter_mm || p.diameter || '';
        $('prop-form').style.display = 'block';
    };
    $('btn-prop-cancel').onclick = () => $('prop-form').style.display = 'none';
    $('btn-prop-save').onclick = async () => {
        if (!selected) return;
        const p = Object.assign({}, selected.f.properties, {
            name: $('in-name').value, status: $('in-status').value,
            material: $('in-mat').value || null, diameter_mm: $('in-dia').value ? +$('in-dia').value : null,
        });
        const { ok, json } = await api('features/' + selected.f.properties.feature_id, { method: 'PATCH', body: { name: p.name, status: p.status, properties: p } });
        if (ok) { toast('Properti tersimpan'); await loadLayers(); } else toast((json && (json.error || json.message)) || 'Gagal update', false);
    };
    $('btn-toggle-valve').onclick = async () => {
        if (!selected) return; const ns = selected.f.properties.status === 'closed' ? 'active' : 'closed';
        const { ok } = await api('features/' + selected.f.properties.feature_id, { method: 'PATCH', body: { status: ns } });
        toast(ok ? 'Valve ' + (ns === 'closed' ? 'ditutup' : 'dibuka') : 'Gagal toggle', ok); await loadLayers();
    };
    $('btn-delete').onclick = async () => {
        if (!selected || !confirm('Hapus fitur ini + edge terkait?')) return;
        const { ok } = await api('features/' + selected.f.properties.feature_id, { method: 'DELETE' });
        toast(ok ? 'Fitur dihapus' : 'Gagal hapus', ok);
        if (ok) { selected = null; $('sel-box').style.display = 'none'; $('sel-empty').style.display = 'block'; await loadLayers(); }
    };

    // ── Gambar (L.Draw) ────────────────────────────────────────────────────
    let drawer = null;
    $('btn-draw').onclick = () => {
        const type = $('sel-type').value;
        cancelDraw();
        if (type === 'dma') { drawer = new L.Draw.Polygon(map, { shapeOptions: { color: '#0891b2' }, allowIntersection: false }); drawer.enable(); $('btn-draw').classList.add('on'); return; }
        if (type === 'pipe') { drawer = new L.Draw.Polyline(map, { shapeOptions: { color: '#1d4ed8' }, metric: false, foot: false, nautical: false }); drawer.enable(); $('btn-draw').classList.add('on'); return; }
        drawer = new L.Draw.Marker(map); drawer.enable(); $('btn-draw').classList.add('on');
    };
    function cancelDraw() { if (drawer) { drawer.disable(); drawer = null; } $('btn-draw').classList.remove('on'); }
    $('btn-cancel-draw').onclick = cancelDraw;

    map.on(L.Draw.Event.CREATED, async (e) => {
        cancelDraw();
        const type = $('sel-type').value;
        if (type === 'dma') {
            const ring = ringToGeo(e.layer.getLatLngs()[0] || []);
            const code = (prompt('Kode DMA (mis. DMA-02):') || '').trim();
            const name = (prompt('Nama DMA:') || code).trim();
            if (!code) { toast('DMA dibatalkan'); return; }
            const { ok, json } = await api('dmas', { method: 'POST', body: { code, name, boundary: ring } });
            toast(ok ? 'DMA dibuat' : (json && json.error) || 'Gagal DMA', ok);
            await Promise.all([loadLayers(), loadNrw()]); return;
        }
        if (type === 'pipe') {
            const coords = (e.layer.getLatLngs() || []).map(toGeo);
            if (coords.length < 2) { toast('Pipa: minimal 2 titik', false); return; }
            const { ok, json } = await api('features', { method: 'POST', body: {
                feature_type: 'pipe', geometry: { type: 'LineString', coordinates: coords },
                name: ($('in-name').value || '').trim() || null,
            } });
            if (ok) toast('Pipa tersimpan + auto-wiring'); else toast((json && json.error) || (json && json.message) || 'Gagal simpan pipa', false);
            await loadLayers(); return;
        }
        const ll = e.layer.getLatLng();
        const { ok, json } = await api('features', { method: 'POST', body: {
            feature_type: type, geometry: { type: 'Point', coordinates: toGeo(ll) } } });
        toast(ok ? ('Node ' + type + ' tersimpan') : ((json && (json.error || json.message)) || 'Gagal'), ok);
        await loadLayers();
    });

    // ── Layer toggles ──────────────────────────────────────────────────────
    const toggleMap = { 'lay-pipes': 'pipes', 'lay-nodes': 'nodes', 'lay-flow': 'flows', 'lay-officers': 'officers', 'lay-dma': 'dmas', 'lay-impact': 'impact', 'lay-customers': 'customers' };
    Object.entries(toggleMap).forEach(([id, g]) => $(id).addEventListener('change', () =>
        $(id).checked ? groups[g].addTo(map) : map.removeLayer(groups[g])));

    // ── Isolasi ────────────────────────────────────────────────────────────
    async function runIsolate() {
        if (!selected) { toast('Klik pipa/ruas dulu', false); return; }
        const { ok, json } = await api('isolate', { method: 'POST', body: { feature_id: selected.f.properties.feature_id } });
        if (!ok) { toast((json && json.note) || (json && json.message) || 'Tidak bisa diisolasi', false); if (json && json.note) showIso(json); return; }
        lastIsolate = json;
        showIso(json); drawOverlay(json);
        toast('Isolasi: ' + (json.valves_to_close || []).length + ' valve perlu ditutup · ' + ((json.affected && json.affected.count) || 0) + ' pelanggan terdampak');
    }
    function showIso(r) {
        $('iso-card').style.display = 'block';
        const vClose = (r.valves_to_close || []).map(v => '• ' + (v.name || '#' + v.id)).join('<br>') || '—';
        const vClosed = (r.valves_closed || []);
        $('iso-body').innerHTML =
            '<div class="ins-kv"><b>Valve yang harus ditutup:</b><br>' + vClose + '</div>'
            + (vClosed.length ? '<div class="ins-kv muted">Sudah tertutup: ' + vClosed.map(v => v.name || '#' + v.id).join(', ') + '</div>' : '')
            + (r.reached_sources && r.reached_sources.length ? '<div class="ins-kv" style="color:#b45309">⚠ Masih nyambung ke sumber: ' + r.reached_sources.map(s => s.name || s.feature_type).join(', ') + '</div>' : '')
            + (r.note ? '<div class="muted">' + r.note + '</div>' : '')
            + '<div class="ins-kv">Segmen pipa terdampak: <b>' + (r.isolated_pipes || []).length + '</b> · Pelanggan: <b>' + ((r.affected && r.affected.count) || 0) + '</b></div>'
            + ((r.affected && r.affected.customers && r.affected.customers.length)
                ? '<div class="muted">' + r.affected.customers.slice(0, 10).map(c => c.customer_number + ' — ' + c.name).join('<br>') + (r.affected.customers.length > 10 ? '<br>… (maks 300 ditampilkan)' : '') + '</div>' : '');
    }
    function drawOverlay(r) {
        groups.impact.clearLayers(); groups.customers.clearLayers();
        const closedIds = (r.valves_to_close || []).map(v => v.id);
        const pipeIds = (r.isolated_pipes || []).map(p => p.id);
        // highlight terdampak: garis ulang pipa/valve terpilih di atas layer normal
        ((layers && layers.pipes) || []).forEach(f => { if (pipeIds.includes(f.properties.feature_id))
            L.polyline((f.geometry.coordinates || []).map(toLL), { color: '#f59e0b', weight: 6, opacity: .4 }).addTo(groups.impact); });
        ((layers && layers.nodes) || []).forEach(f => { if (closedIds.includes(f.properties.feature_id))
            L.circleMarker(toLL(f.geometry.coordinates), { radius: 11, color: '#b91c1c', fillColor: '#ef4444', fillOpacity: .25, weight: 3 }).addTo(groups.impact)
                .bindTooltip('TUTUP valve ini').openTooltip(); });
        if (r.affected && r.affected.polygon && r.affected.polygon.length >= 3)
            L.polygon(r.affected.polygon.map(toLL), { color: '#f59e0b', dashArray: '5 5', fillColor: '#fbbf24', fillOpacity: .07, weight: 2 }).addTo(groups.impact);
        ((r.affected && r.affected.customers) || []).forEach(c =>
            L.circleMarker([c.lat, c.lng], { radius: 4, color: '#7c3aed', fillColor: '#a78bfa', fillOpacity: .9 }).addTo(groups.customers)
                .bindTooltip(c.customer_number + ' — ' + c.name));
        if (!map.hasLayer(groups.customers)) { groups.customers.addTo(map); $('lay-customers').checked = true; }
    }
    $('btn-isolate').onclick = runIsolate;
    $('btn-clear-iso') && ($('btn-clear-iso').onclick = () => { groups.impact.clearLayers(); groups.customers.clearLayers(); $('iso-card').style.display = 'none'; });

    // ── Insiden → WO ───────────────────────────────────────────────────────
    async function createWO(featureId) {
        if (!featureId) { toast('Klik pipa dulu', false); return; }
        if (!confirm('Buat WorkOrder emergensi utk insiden jaringan ini?')) return;
        const { ok, json } = await api('incidents', { method: 'POST', body: {
            feature_id: featureId, priority: $('wo-priority').value, assigned_to: $('wo-assign').value ? +$('wo-assign').value : null } });
        toast(ok ? 'WO ' + (json.wo_number || json.id) + ' dibuat · pipa ditandai RUSAK' : ((json && json.message) || 'Gagal membuat WO'), ok);
        await loadLayers();
    }
    $('btn-wo').onclick = () => selected && createWO(selected.f.properties.feature_id);
    $('btn-dispatch').onclick = () => selected && dispatchNearest(selected.f.properties.feature_id);
    $('btn-incident').onclick = () => { $('wo-priority').value = 'urgent'; $('iso-card').style.display = 'block'; createWO(selected.f.properties.feature_id); };
    $('btn-officer-refresh').onclick = loadOfficersLive;

    async function loadOfficers() {
        const { ok, json } = await api('officers.json'); if (!ok) return;
        $('wo-assign').innerHTML = '<option value="">—</option>' + (json.items || []).map(o => `<option value="${o.id}">${o.name}</option>`).join('');
    }

    // ── Petugas lapangan LIVE (GPS dari mobile) + dispatch ─────────────────
    const fmtAgo = (sec) => sec < 90 ? 'baru saja' : sec < 3600 ? Math.round(sec / 60) + ' mnt lalu' : Math.round(sec / 3600) + ' jam lalu';
    async function loadOfficersLive() {
        const { ok, json } = await api('technicians.json');
        if (!ok) return;
        officersLive = json.items || [];
        drawOfficers(json.stale_minutes || 30);
        renderOfficerList(json.stale_minutes || 30);
    }
    function drawOfficers(stale) {
        groups.officers.clearLayers();
        officersLive.forEach(o => {
            const m = L.circleMarker([o.lat, o.lng], {
                radius: 8, weight: 2, color: '#fff',
                fillColor: o.online ? '#16a34a' : '#9ca3af', fillOpacity: .95,
            }).addTo(groups.officers);
            m.bindTooltip('👷 ' + o.name + ' · ' + fmtAgo(o.age_seconds) + (o.online ? ' (ONLINE)' : ' (offline)'), { direction: 'top' });
            if (o.accuracy_m) L.circle([o.lat, o.lng], { radius: o.accuracy_m * 1.5, color: o.online ? '#16a34a' : '#94a3b8', weight: 1, fill: true, fillOpacity: .12, dashArray: '3 3' }).addTo(groups.officers);
        });
    }
    function renderOfficerList(stale) {
        const box = $('officer-list'); if (!box) return;
        if (!officersLive.length) { box.innerHTML = '<span class="muted">Belum ada petugas pernah lapor GPS hari ini.</span>'; return; }
        box.innerHTML = officersLive.map(o => `<div class="ins-kv">
            <b style="color:${o.online ? '#15803d' : '#6b7280'}">👷 ${o.name}</b>
            <span class="muted">· ${o.online ? 'online' : 'offline'} · ${fmtAgo(o.age_seconds)}${o.accuracy_m ? ' · ±' + Math.round(o.accuracy_m) + 'm' : ''}</span>
        </div>`).join('') + '<div class="muted">Petugas dianggap online bila lapor &lt; ' + stale + ' menit.</div>';
    }
    async function dispatchNearest(featureId) {
        if (!featureId) { toast('Klik pipa/ruas dulu', false); return; }
        if (!confirm('Dispatch WO ke petugas ONLINE terdekat? Titik GPS dari aplikasi mobile.')) return;
        const { ok, status, json } = await api('dispatch', { method: 'POST', body: { feature_id: featureId, priority: $('wo-priority').value } });
        if (ok) {
            toast((json.note || 'WO ter-assign') + ' · rute ' + Math.round(((json.route && json.route.distance_m) || 0)) + ' m');
            if (json.route && json.route.geometry && json.route.geometry.coordinates) {
                const pts = json.route.geometry.coordinates.map(c => [c[1], c[0]]);
                L.polyline(pts, { color: '#16a34a', weight: 4, opacity: .85, dashArray: '7 5' }).addTo(groups.impact)
                    .bindTooltip('Rute 🚑 ' + (json.officer && json.officer.name)).openTooltip();
                L.marker([json.officer.lat, json.officer.lng], { icon: L.divIcon({ html: '🚑', iconSize: [18, 18] }) }).addTo(groups.impact);
            }
            await loadOfficersLive();
        } else if (status === 422) {
            const cands = (json.candidates || []).map(c => `${c.name} — ${c.distance_m} m (${c.online ? 'online' : 'offline'})`).join('<br>');
            $('iso-card').style.display = 'block';
            $('iso-body').innerHTML = '<div class="muted">' + (json.error || 'Gagal') + '</div>' + (cands ? '<div>' + cands + '</div>' : '');
            toast(json.error || 'Tidak ada petugas online', false);
        } else toast((json && (json.error || json.message)) || 'Gagal dispatch', false);
        await loadLayers();
    }

    // ── NRW ────────────────────────────────────────────────────────────────
    async function loadNrw() {
        const { ok, json } = await api('nrw.json');
        const body = $('nrw-body');
        if (!ok) { body.innerHTML = '<tr><td colspan="6" class="muted">gagal</td></tr>'; return; }
        const rows = json.dmas || [];
        if (!rows.length) { body.innerHTML = '<tr><td colspan="6" class="muted">Belum ada DMA aktif — gambar polygon DMA.</td></tr>'; applyTrends(); return; }
        body.innerHTML = rows.map(d => `<tr>
            <td><b>${d.code}</b> ${d.name || ''} <button class="btn-xs" data-nrw="${d.dma_id}" title="Hitung ulang NRW dari data tagihan + reading">${d.has_boundary ? 'polygon' : 'zona'} · ✦ hitung</button></td>
            <td>${d.period || '—'}</td><td>${num(d.system_input_m3)}</td><td>${num(d.billed_metered_m3)}</td>
            <td>${d.nrw_percentage === null || d.nrw_percentage === undefined ? '<span class="badge-s b-nihil">belum</span>'
                : `<span class="badge-s ${d.status === 'baik' ? 'b-baik' : d.status === 'waspada' ? 'b-waspada' : 'b-kritis'}">${d.nrw_percentage}%</span>`}</td>
            <td data-trend="${d.dma_id}" class="muted">…</td></tr>`).join('');
        applyTrends();
        body.querySelectorAll('[data-nrw]').forEach(btn => btn.onclick = async () => {
            const p = (json.period || '').toString(); const { ok: o, json: j } = await api(`dmas/${btn.dataset.nrw}/nrw`, { method: 'POST', body: { period: p } });
            toast(o ? `NRW ${j.nrw_percent}% (${j.supply_m3} vs ${j.billed_m3} m³)` : ((j && j.error) || 'Gagal'), o); await Promise.all([loadNrw(), loadTrend()]);
        });
    }
    $('btn-nrw-refresh').onclick = loadNrw;

    // ── MNF (debit malam) + tren NRW ───────────────────────────────────────
    function spark(vals) {
        const pts = vals.filter(v => v !== null && v !== undefined).map(Number);
        if (!pts.length) return '<span class="muted">–</span>';
        const w = 76, h = 18, max = Math.max(35, ...pts);
        const xy = pts.map((v, i) => [2 + i * (w / Math.max(1, pts.length - 1)), h + 1 - (v / max) * h]);
        const last = pts[pts.length - 1];
        const col = last > 30 ? '#b91c1c' : last > 20 ? '#b45309' : '#15803d';
        return `<svg width="${w + 4}" height="${h + 4}" style="vertical-align:middle"><polyline points="${xy.map(p => p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ')}" fill="none" stroke="${col}" stroke-width="1.7"/></svg> <b style="color:${col};font-size:.72rem">${last}%</b>`;
    }
    function applyTrends() {
        document.querySelectorAll('[data-trend]').forEach(td => {
            const t = trendByDma[td.dataset.trend];
            td.innerHTML = t && t.length ? spark(t.map(p => p.nrw_percentage)) : '<span class="muted">–</span>';
        });
    }
    async function loadTrend() {
        const { ok, json } = await api('nrw-trend.json?months=6'); if (!ok) return;
        trendByDma = {};
        (json.dmas || []).forEach(d => { trendByDma[d.dma_id] = d.periods || []; });
        applyTrends();
    }
    async function loadMnf() {
        const { ok, json } = await api('mnf.json');
        const body = $('mnf-body');
        if (!ok) { body.innerHTML = '<tr><td colspan="5" class="muted">gagal</td></tr>'; return; }
        const items = json.items || [];
        if (!items.length) { body.innerHTML = '<tr><td colspan="5" class="muted">Belum ada DMA aktif.</td></tr>'; return; }
        mnfStatus = {};
        items.forEach(m => { mnfStatus[m.dma_id] = m; });
        $('mnf-threshold').textContent = items[0].alert_pct;
        body.innerHTML = items.map(m => {
            const badge = m.status === 'merah' ? '<span class="badge-s b-kritis">merah</span>'
                : m.status === 'waspada' ? '<span class="badge-s b-waspada">waspada</span>'
                : m.status === 'baik' ? '<span class="badge-s b-baik">baik</span>' : '<span class="badge-s b-nihil">nihil</span>';
            return `<tr><td><b>${m.code}</b></td><td>${m.mnf_m3h ?? '–'} m³/j${m.n_samples ? ' <span class="muted">(' + m.n_samples + ' baca)</span>' : ''}</td><td>${m.mnf_m3day ?? '–'}</td>
                <td class="${m.status === 'merah' ? 'b-kritis' : ''}">${m.pct_of_base ?? '–'}% ${badge}${m.base_source === 'estimasi_koneksi' ? ' <span class="muted">≈est</span>' : ''}</td><td>${m.per_conn_lph ?? '–'}</td></tr>`;
        }).join('');
        if (layers) drawLayers(layers); // warnai polygon DMA
    }
    $('btn-mnf-refresh').onclick = () => loadMnf();

    // ── util buttons ───────────────────────────────────────────────────────
    $('btn-refresh').onclick = () => { loadLayers(); loadNrw(); loadTrend(); loadOfficersLive(); loadMnf(); };
    $('btn-fit').onclick = fitNetwork;

    // ── boot ───────────────────────────────────────────────────────────────
    loadLayers().then(() => window.setTimeout(fitNetwork, 300));
    loadNrw(); loadOfficers(); loadOfficersLive(); loadTrend(); loadMnf();
    setInterval(loadOfficersLive, 30000); // petugas live refresh 30 detik
})();
</script>
@endpush
