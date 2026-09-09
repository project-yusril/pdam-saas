@extends('admin.layouts.app')

@section('title', 'Peta Pelanggan')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<style>
    .gis-wrap { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: 1rem; align-items: start; }
    .gis-main { min-width: 0; }
    #map { height: 72vh; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); background: #eef2f5; z-index: 0; }
    .gis-toolbar { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .75rem; align-items: center; }
    .gis-toolbar select, .gis-toolbar input { padding: .45rem .6rem; border: 1px solid #d4d8e0; border-radius: 6px; font-size: .85rem; background: #fff; }
    .gis-toolbar input[type=text] { min-width: 220px; }
    .chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .75rem; }
    .chip { display: inline-flex; align-items: center; gap: .4rem; border: 1px solid #e2e6ee; background: #fff; border-radius: 999px;
            padding: .25rem .7rem; font-size: .78rem; cursor: pointer; user-select: none; }
    .chip .dot { width: .7rem; height: .7rem; border-radius: 999px; display: inline-block; }
    .chip.off { opacity: .4; text-decoration: line-through; }
    .side-card { background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 1rem 1.1rem; margin-bottom: 1rem; }
    .side-card h2 { font-size: .95rem; margin-bottom: .5rem; }
    .side-card .muted { color: #6b7280; font-size: .78rem; }
    .missing-item { border-top: 1px solid #f0f2f8; padding: .55rem 0; }
    .missing-item .who { font-weight: 600; font-size: .82rem; }
    .missing-item .addr { color: #6b7280; font-size: .75rem; margin: .15rem 0 .4rem; }
    .missing-item .rowbtns { display: flex; gap: .35rem; flex-wrap: wrap; }
    .btn-sm { font-size: .72rem; padding: .25rem .55rem; border-radius: 5px; border: 1px solid #cfd6e2; background: #fff; cursor: pointer; }
    .btn-sm:hover { background: #f0f2f8; }
    .btn-sm.primary { background: #4f46e5; color: #fff; border-color: #4f46e5; }
    .btn-sm.warn { background: #d97706; color: #fff; border-color: #d97706; }
    .btn-sm.danger { background: #dc2626; color: #fff; border-color: #dc2626; }
    .btn-sm.picking { background: #059669; color: #fff; border-color: #059669; animation: pulse 1s infinite; }
    @keyframes pulse { 50% { opacity: .6; } }
    .house-marker { background: transparent; border: none; }
    .house-marker svg { display: block; filter: drop-shadow(0 1px 1px rgba(0,0,0,.4)); }
    .stop-marker { background: #fff; border: 2px solid #1d4ed8; color: #1d4ed8; border-radius: 999px;
                    width: 22px; height: 22px; line-height: 18px; text-align: center; font-weight: 700; font-size: .7rem; }
    .origin-marker { background: #7c3aed; border: 2px solid #fff; border-radius: 999px; width: 14px; height: 14px; }
    .searchbox { position: relative; }
    .search-results { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #d4d8e0;
                      border-radius: 6px; margin-top: 2px; box-shadow: 0 6px 14px rgba(0,0,0,.1); max-height: 260px; overflow: auto; z-index: 1000; }
    .search-results .hit { padding: .5rem .65rem; font-size: .78rem; cursor: pointer; border-bottom: 1px solid #f0f2f8; }
    .search-results .hit:hover { background: #eef2ff; }
    #routing-info { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: .5rem .8rem; font-size: .8rem;
                    margin-top: .6rem; display: none; }
    .leaflet-container a { color: #1d4ed8; }
    #toast { position: fixed; bottom: 1.2rem; left: 50%; transform: translateX(-50%); background: #111827; color: #fff;
             padding: .55rem 1rem; border-radius: 8px; font-size: .8rem; display: none; z-index: 2000; box-shadow: 0 4px 12px rgba(0,0,0,.25); }
    .legend-title { font-weight: 700; margin: 0 0 .35rem; font-size: .8rem; }
    .legend-row { display: flex; align-items: center; gap: .5rem; font-size: .8rem; padding: .15rem 0; cursor: pointer; }
    .legend-row.off { opacity: .35; text-decoration: line-through; }
    .spinner { display: inline-block; width: .8rem; height: .8rem; border: 2px solid #c7d2fe; border-top-color: #4f46e5;
               border-radius: 999px; animation: spin .7s linear infinite; vertical-align: middle; }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>
@endpush

@section('content')
<h1>Peta Pelanggan — Wilayah Baca Meter</h1>

<div class="gis-wrap">
    <div class="gis-main">
        <div class="gis-toolbar">
            <select id="f-zone" title="Filter zona">
                <option value="">Semua zona</option>
                @foreach ($zones as $zone)
                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                @endforeach
            </select>
            <select id="f-route" title="Filter rute baca">
                <option value="">Semua rute baca</option>
                @foreach ($routes as $route)
                    <option value="{{ $route->id }}">{{ $route->code }} — {{ $route->name }}</option>
                @endforeach
            </select>
            <input type="text" id="f-q" placeholder="Cari nama / no. pelanggan…" autocomplete="off">
            <div class="searchbox">
                <input type="text" id="f-address" placeholder="📍 Cari alamat (OSM/Nominatim)…" autocomplete="off">
                <div class="search-results" id="address-results" style="display:none"></div>
            </div>
            <button type="button" class="btn btn-primary" id="btn-reroute">Rute baca meter terpilih</button>
        </div>

        <div class="chips" id="chips"></div>

        <div id="map"></div>
        <div id="routing-info"></div>
    </div>

    <div class="gis-side">
        <div class="side-card">
            <h2>Legenda status pembayaran</h2>
            <div id="legend"></div>
            <p class="muted" style="margin-top:.5rem">Klik legenda untuk menampilkan/menyembunyikan warna. Ikon rumah di cluster; zoom untuk memisahkan.</p>
        </div>

        <div class="side-card">
            <h2>Pelanggan tanpa koordinat <span id="missing-count"></span></h2>
            <p class="muted">Isi titik dari alamat (Nominatim) atau tandai manual di peta.</p>
            <div id="missing-actions" style="display:flex;gap:.4rem;margin:.5rem 0 .25rem">
                <button type="button" class="btn-sm warn" id="btn-geocode-missing">Isi otomatis (maks 10)</button>
            </div>
            <div id="missing-list"></div>
        </div>
    </div>
</div>

<div id="toast"></div>

<meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
(function () {
    'use strict';

    // ── Konfigurasi ──────────────────────────────────────────────────────
    const BASE = '{{ url('admin/gis') }}';
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const LEGEND = [
        { color: 'green',  label: 'Lunas' },
        { color: 'blue',   label: 'Belum jatuh tempo' },
        { color: 'yellow', label: 'Menunggak 1 bulan' },
        { color: 'orange', label: 'Menunggak 2 bulan' },
        { color: 'red',    label: 'Menunggak 3+ bulan' },
        { color: 'black',  label: 'Putus / Isolir' },
    ];
    const HEX = {
        green: '#22c55e', blue: '#38bdf8', yellow: '#eab308',
        orange: '#f97316', red: '#ef4444', black: '#1e293b',
    };
    const rupiah = (v) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v || 0);
    const km = (m) => (m / 1000).toFixed(1) + ' km';
    const fmtDur = (s) => { const m = Math.round(s / 60); return m >= 60 ? Math.floor(m / 60) + ' jam ' + (m % 60) + ' mnt' : m + ' mnt'; };
    const toast = (msg) => { const t = document.getElementById('toast'); t.textContent = msg; t.style.display = 'block'; clearTimeout(toast._h); toast._h = setTimeout(() => t.style.display = 'none', 3200); };

    // ── State ────────────────────────────────────────────────────────────
    const state = {
        active: new Set(['green', 'blue', 'yellow', 'orange', 'red', 'black']),
        origin: null,            // latlng awal rute
        routeLayer: null,        // polyline rute
        stopLayer: null,
        originMarker: null,
        featuresById: new Map(),
        pickCustomer: null,      // mode tandai manual
        firstLoad: true,
    };

    // ── Peta & tile OSM (gratis) ─────────────────────────────────────────
    const map = L.map('map', { zoomControl: true, attributionControl: true })
        .setView([1.45, 109.35], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);
    L.control.scale().addTo(map);

    const colors = {};                       // per-warna layer group
    LEGEND.forEach(({ color }) => { colors[color] = L.featureGroup().addTo(map); });
    const markers = L.markerClusterGroup({ disableClusteringAtZoom: 16, chunkedLoading: true });
    markers.addTo(map);

    // ── Ikon rumah SVG ───────────────────────────────────────────────────
    function houseSvg(hex) {
        const stroke = hex === '#1e293b' ? '#0b1220' : '#ffffff';
        return '<svg width="26" height="30" viewBox="0 0 24 28" xmlns="http://www.w3.org/2000/svg">' +
            '<path d="M12 1 L23 10 H21 V27 H3 V10 H1 Z" fill="' + hex + '" stroke="' + stroke + '" stroke-width="1.6" stroke-linejoin="round"/>' +
            '<path d="M10 27 V18 H14 V27 Z" fill="rgba(0,0,0,.28)"/>' +
            '<rect x="9.6" y="12" width="4.8" height="3.6" fill="rgba(255,255,255,.85)" rx=".5"/>' +
            '</svg>';
    }
    function houseIcon(hex) {
        return L.divIcon({ className: 'house-marker', html: houseSvg(hex), iconSize: [26, 30], iconAnchor: [13, 29], popupAnchor: [0, -27] });
    }

    function featureLatLng(f) { return L.latLng(f.geometry.coordinates[1], f.geometry.coordinates[0]); }

    function popupHtml(p) {
        const badge = '<span class="badge" style="background:' + p.marker_color + ';color:#fff;margin-left:.3rem">' + p.status_label + '</span>';
        const arrears = p.total_arrears > 0
            ? '<tr><th>Total tunggakan</th><td><b>' + rupiah(p.total_arrears) + '</b></td></tr>' : '';
        const address = p.address ? '<tr><th>Detail alamat</th><td>' + p.address + '</td></tr>' : '';
        return '<div style="min-width:230px">' +
            '<b>' + p.name + '</b> <span class="badge" style="background:' + p.marker_color + ';color:#fff">' + p.status_label + '</span><br>' +
            '<span style="font-size:.78rem;color:#6b7280">' + p.customer_number + (p.zone ? ' · ' + p.zone : '') + '</span>' +
            '<table style="margin-top:.45rem;font-size:.78rem;width:100%">' +
            address +
            (p.arrears_months > 0 ? '<tr><th>Menunggak</th><td>' + p.arrears_months + ' bulan</td></tr>' : '') +
            arrears +
            '</table>' +
            '<div style="margin-top:.6rem;display:flex;gap:.35rem;flex-wrap:wrap">' +
            '<button type="button" class="btn-sm primary" data-act="origin" data-cid="' + p.id + '">Mulai rute dr sini</button>' +
            '<button type="button" class="btn-sm" data-act="target" data-cid="' + p.id + '">Akhiri rute di sini</button>' +
            '<button type="button" class="btn-sm" data-act="center" data-cid="' + p.id + '">Posisikan peta</button>' +
            '</div></div>';
    }

    // ── Muat GeoJSON dari server ─────────────────────────────────────────
    async function loadMap() {
        const params = new URLSearchParams();
        const zone = document.getElementById('f-zone').value;
        const route = document.getElementById('f-route').value;
        const q = document.getElementById('f-q').value.trim();
        if (zone) params.set('zone_id', zone);
        if (route) params.set('meter_route_id', route);
        if (q) params.set('q', q);

        let json;
        try {
            const res = await fetch(BASE + '/customers.json?' + params.toString());
            json = await res.json();
        } catch (e) { toast('Gagal memuat data peta.'); return; }

        state.featuresById.clear();
        LEGEND.forEach(({ color }) => colors[color].clearLayers());
        markers.clearLayers();

        (json.features || []).forEach((f) => {
            const color = f.properties.status_color;
            if (!state.active.has(color)) return;
            state.featuresById.set(String(f.properties.id), f);
            const marker = L.marker(featureLatLng(f), { icon: houseIcon(f.properties.marker_color || HEX[color]) });
            marker.bindPopup(popupHtml(f.properties));
            markers.addLayer(marker);
        });

        if (state.firstLoad && json.features && json.features.length) {
            const latlngs = json.features.map(featureLatLng);
            map.fitBounds(L.latLngBounds(latlngs), { padding: [30, 30], maxZoom: 14 });
            state.firstLoad = false;
        }
        await loadSummary();
    }

    async function loadSummary() {
        const params = new URLSearchParams();
        const zone = document.getElementById('f-zone').value;
        const route = document.getElementById('f-route').value;
        if (zone) params.set('zone_id', zone);
        if (route) params.set('meter_route_id', route);

        try {
            const res = await fetch(BASE + '/summary.json?' + params.toString());
            const json = await res.json();
            renderChips(json.counts);
            renderLegend(json.counts);
            const mc = document.getElementById('missing-count');
            if (mc) mc.textContent = '(' + json.missing_coords + ')';
        } catch (e) { /* diam */ }
    }

    function renderChips(counts) {
        const el = document.getElementById('chips');
        el.innerHTML = '';
        LEGEND.forEach(({ color, label }) => {
            const c = document.createElement('span');
            c.className = 'chip' + (state.active.has(color) ? '' : ' off');
            c.innerHTML = '<span class="dot" style="background:' + HEX[color] + '"></span>' + label + ' · ' + (counts[color] || 0);
            c.onclick = () => toggleColor(color);
            el.appendChild(c);
        });
    }
    function renderLegend(counts) {
        const el = document.getElementById('legend');
        el.innerHTML = '';
        LEGEND.forEach(({ color, label }) => {
            const r = document.createElement('div');
            r.className = 'legend-row' + (state.active.has(color) ? '' : ' off');
            r.innerHTML = '<span class="dot" style="width:.8rem;height:.8rem;border-radius:999px;display:inline-block;background:' + HEX[color] + '"></span>' +
                label + ' <span style="margin-left:auto;color:#6b7280">' + (counts[color] || 0) + '</span>';
            r.onclick = () => toggleColor(color);
            el.appendChild(r);
        });
    }

    function toggleColor(color) {
        if (state.active.has(color)) state.active.delete(color); else state.active.add(color);
        loadMap().catch(() => {});
    }

    // ── Delegasi tombol popup ────────────────────────────────────────────
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-act]');
        if (!btn) return;
        const f = state.featuresById.get(btn.dataset.cid);
        if (!f) return;
        const latlng = featureLatLng(f);
        if (btn.dataset.act === 'center') { map.setView(latlng, 17); }
        if (btn.dataset.act === 'origin') { setOrigin(latlng); toast('Titik awal rute diset di ' + f.properties.customer_number + '.'); }
        if (btn.dataset.act === 'target') { drawRoute(state.origin, latlng); }
    });

    function setOrigin(latlng) {
        state.origin = latlng;
        if (state.originMarker) state.originMarker.remove();
        state.originMarker = L.marker(latlng, { icon: L.divIcon({ className: 'origin-marker', iconSize: [14, 14] }) }).addTo(map)
            .bindPopup('Titik awal rute');
    }

    async function drawRoute(from, to) {
        if (!from) { toast('Set titik awal dulu (klik "Mulai rute dr sini" di popup).'); return; }
        showBusy(true);
        try {
            const url = BASE + '/route.json?profile=driving&points=' + encodeURIComponent(from.lat + ',' + from.lng + '|' + to.lat + ',' + to.lng);
            const res = await fetch(url);
            const json = await res.json();
            if (!res.ok || !json.geometry) { toast(json.error || 'Routing gagal.'); return; }
            if (state.routeLayer) state.routeLayer.remove();
            state.routeLayer = L.geoJSON(json.geometry, {
                style: { color: '#3b82f6', weight: 4, opacity: .9 },
            }).addTo(map);
            const info = document.getElementById('routing-info');
            info.style.display = 'block';
            info.innerHTML = '<b>Rute terhitung (OSRM, gratis)</b> — jarak ' + km(json.distance) +
                ' · perkiraan ' + fmtDur(json.duration) + ' <button type="button" class="btn-sm" id="btn-clear-route">Bersihkan</button>';
            document.getElementById('btn-clear-route').onclick = () => { info.style.display = 'none'; if (state.routeLayer) state.routeLayer.remove(); state.routeLayer = null; };
            map.fitBounds(state.routeLayer.getBounds(), { padding: [40, 40] });
        } catch (e) { toast('Routing gagal (server OSRM tidak menjawab).'); }
        showBusy(false);
    }

    // ── Rute baca meter per meter-route (tour) ───────────────────────────
    async function loadTour() {
        const routeId = document.getElementById('f-route').value;
        if (!routeId) { toast('Pilih rute baca meter dulu pada filter di atas.'); return; }
        showBusy(true);
        try {
            const res = await fetch(BASE + '/tour.json?route_id=' + routeId + '&profile=driving');
            const json = await res.json();
            if (!res.ok) { toast(json.error || 'Tour gagal.'); return; }
            if (state.routeLayer) state.routeLayer.remove();
            if (state.stopLayer) state.stopLayer.remove();
            state.routeLayer = L.geoJSON(json.geometry, { style: { color: '#7c3aed', weight: 4, opacity: .9, dashArray: '6 8' } }).addTo(map);
            state.stopLayer = L.layerGroup().addTo(map);
            (json.stops || []).forEach((s, i) => {
                L.marker([s.lat, s.lng], { icon: L.divIcon({ className: 'stop-marker', html: String(s.seq), iconSize: [22, 22], iconAnchor: [11, 11] }) })
                    .bindPopup('<b>#' + s.seq + '</b> ' + s.name + '<br>' + s.customer_number)
                    .addTo(state.stopLayer);
            });
            const info = document.getElementById('routing-info');
            info.style.display = 'block';
            info.innerHTML = '<b>Rute baca (nearest-neighbor via OSRM)</b> — ' + (json.stops || []).length + ' titik · jarak ' +
                km(json.distance) + ' · perkiraan ' + fmtDur(json.duration) +
                ' <button type="button" class="btn-sm" id="btn-clear-route">Bersihkan</button>';
            document.getElementById('btn-clear-route').onclick = () => { info.style.display = 'none'; if (state.routeLayer) state.routeLayer.remove(); state.routeLayer = null; if (state.stopLayer) state.stopLayer.remove(); state.stopLayer = null; };
            map.fitBounds(state.routeLayer.getBounds(), { padding: [40, 40] });
        } catch (e) { toast('Tour gagal (server OSRM tidak menjawab).'); }
        showBusy(false);
    }

    // ── Pencarian alamat (Nominatim via proxy server) ────────────────────
    let searchTimer = null;
    const addressInput = document.getElementById('f-address');
    const resultsBox = document.getElementById('address-results');

    addressInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const q = addressInput.value.trim();
        if (q.length < 3) { resultsBox.style.display = 'none'; return; }
        searchTimer = setTimeout(async () => {
            const box = map.getBounds();
            const bbox = [box.getWest(), box.getSouth(), box.getEast(), box.getNorth()].join(',');
            const res = await fetch(BASE + '/search.json?q=' + encodeURIComponent(q) + '&bbox=' + encodeURIComponent(bbox));
            const json = await res.json();
            resultsBox.innerHTML = '';
            if (!json.items || !json.items.length) { resultsBox.innerHTML = '<div class="hit muted">Tidak ditemukan.</div>'; }
            (json.items || []).forEach((it) => {
                const d = document.createElement('div');
                d.className = 'hit';
                d.textContent = it.label;
                d.onclick = () => {
                    resultsBox.style.display = 'none';
                    addressInput.value = '';
                    const latlng = L.latLng(it.lat, it.lon);
                    L.marker(latlng, { icon: L.divIcon({ className: 'origin-marker', iconSize: [14, 14] }) })
                        .addTo(map).bindPopup('Hasil geocoding: ' + it.label).openPopup();
                    map.setView(latlng, 16);
                };
                resultsBox.appendChild(d);
            });
            resultsBox.style.display = 'block';
        }, 450);
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('.searchbox')) resultsBox.style.display = 'none'; });

    // ── Panel pelanggan tanpa koordinat ──────────────────────────────────
    async function loadMissing() {
        const params = new URLSearchParams();
        const zone = document.getElementById('f-zone').value;
        const route = document.getElementById('f-route').value;
        if (zone) params.set('zone_id', zone);
        if (route) params.set('meter_route_id', route);
        try {
            const res = await fetch(BASE + '/no-coords.json?' + params.toString());
            const json = await res.json();
            const list = document.getElementById('missing-list');
            list.innerHTML = '';
            (json.items || []).forEach((c) => {
                const item = document.createElement('div');
                item.className = 'missing-item';
                item.innerHTML = '<div class="who">' + c.customer_number + ' — ' + c.name + '</div>' +
                    '<div class="addr">' + (c.address || '-') + '</div>' +
                    '<div class="rowbtns">' +
                    '<button type="button" class="btn-sm primary" data-miss-act="geocode" data-id="' + c.id + '">Geocode alamat</button>' +
                    '<button type="button" class="btn-sm" data-miss-act="pick" data-id="' + c.id + '" data-name="' + c.name.replace(/"/g, '&quot;') + '">Tandai di peta</button>' +
                    '</div>';
                list.appendChild(item);
            });
            if (!json.items || !json.items.length) list.innerHTML = '<p class="muted">Semua pelanggan sudah punya titik.</p>';
        } catch (e) { /* diam */ }
    }

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-miss-act]');
        if (!btn) return;
        const id = btn.dataset.id;
        if (btn.dataset.missAct === 'geocode') {
            btn.disabled = true; btn.textContent = '…';
            try {
                const res = await fetch(BASE + '/customers/' + id + '/geocode', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
                const json = await res.json();
                if (!res.ok) { toast(json.error || 'Gagal geocode.'); } else {
                    toast('Koordinat terisi dari alamat.');
                    map.setView([json.lat, json.lon], 17);
                }
            } catch (err) { toast('Gagal menghubungi server.'); }
            loadMissing(); loadSummary();
            return;
        }
        if (btn.dataset.missAct === 'pick') {
            if (state.pickCustomer === id) { state.pickCustomer = null; btn.classList.remove('picking'); btn.textContent = 'Tandai di peta'; return; }
            state.pickCustomer = id;
            document.querySelectorAll('[data-miss-act="pick"]').forEach((b) => { b.classList.remove('picking'); b.textContent = 'Tandai di peta'; });
            btn.classList.add('picking');
            btn.textContent = 'Klik di peta…';
            toast('Klik lokasi rumah "' + btn.dataset.name + '" pada peta.');
        }
    });

    map.on('click', async (e) => {
        if (!state.pickCustomer) return;
        const id = state.pickCustomer;
        state.pickCustomer = null;
        document.querySelectorAll('[data-miss-act="pick"]').forEach((b) => { b.classList.remove('picking'); b.textContent = 'Tandai di peta'; });
        try {
            const res = await fetch(BASE + '/customers/' + id + '/coordinates', {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ latitude: e.latlng.lat, longitude: e.latlng.lng }),
            });
            const json = await res.json();
            if (!res.ok) { toast('Gagal menyimpan titik: ' + (json.message || 'validation error')); return; }
            toast('Titik rumah tersimpan.');
            L.marker(e.latlng, { icon: houseIcon('#94a3b8') }).addTo(map).bindPopup('Titik baru tersimpan').openPopup();
        } catch (err) { toast('Gagal menyimpan.'); }
        loadMissing(); loadSummary();
    });

    // ── Geocode batch ────────────────────────────────────────────────────
    document.getElementById('btn-geocode-missing').addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        btn.disabled = true;
        const original = btn.textContent;
        btn.innerHTML = '<span class="spinner"></span> Memproses (1 dtk/alamat)…';
        try {
            const res = await fetch(BASE + '/geocode-missing', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ limit: 10 }),
            });
            const json = await res.json();
            if (!res.ok) { toast(json.error || 'Batch geocode gagal.'); } else {
                toast('Diproses ' + json.processed + ' · ditemukan ' + json.found + ' · gagal ' + json.failed + ' · sisa ' + json.remaining);
            }
        } catch (err) { toast('Batch geocode gagal.'); }
        btn.disabled = false;
        btn.textContent = original;
        loadMissing(); loadSummary(); loadMap().catch(() => {});
    });

    document.getElementById('btn-reroute').addEventListener('click', loadTour);

    // ── Filter bar ───────────────────────────────────────────────────────
    let qTimer = null;
    document.getElementById('f-q').addEventListener('input', () => { clearTimeout(qTimer); qTimer = setTimeout(() => loadMap().catch(() => {}), 450); });
    document.getElementById('f-zone').addEventListener('change', () => { state.firstLoad = false; loadMap().catch(() => {}); loadMissing(); });
    document.getElementById('f-route').addEventListener('change', () => { state.firstLoad = false; loadMap().catch(() => {}); loadMissing(); });

    function showBusy(on) {
        document.getElementById('btn-reroute').disabled = on;
        document.getElementById('btn-reroute').textContent = on ? '…' : 'Rute baca meter terpilih';
    }

    // ── init ─────────────────────────────────────────────────────────────
    loadMap().catch(() => {});
    loadMissing();
})();
</script>
@endpush
