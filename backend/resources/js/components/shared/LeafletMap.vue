<template>
  <div class="relative">
    <div class="flex gap-2 mb-3 flex-wrap">
      <button
        v-for="f in filters"
        :key="f.key"
        @click="toggleFilter(f.key)"
        :class="['px-3 py-1 rounded-full text-xs font-medium border transition', activeFilters.includes(f.key) ? f.activeClass : f.inactiveClass]"
      >
        {{ f.label }} ({{ f.count }})
      </button>
    </div>
    <div ref="mapContainer" class="w-full rounded-xl border border-gray-200 bg-gray-100" :style="{ height }"></div>
    <div class="mt-2 flex gap-4 text-xs text-gray-500 flex-wrap">
      <span style="color:#22c55e">● Lunas</span>
      <span style="color:#38bdf8">● Belum jatuh tempo</span>
      <span style="color:#eab308">● Menunggak 1 bln</span>
      <span style="color:#f97316">● Menunggak 2 bln</span>
      <span style="color:#ef4444">● Menunggak ≥3 bln</span>
      <span style="color:#1e293b">● Putus / Isolir</span>
    </div>
    <p v-if="empty" class="mt-2 text-xs text-gray-500">
      Tidak ada pelanggan berkoordinat untuk filter ini — isi titik via halaman Blade Peta Pelanggan (/admin/gis/map) atau seed.
    </p>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import api from '../../api';

const props = defineProps({
  zoneId: { type: Number, default: null },
  height: { type: String, default: '500px' },
});

const mapContainer = ref(null);
const empty = ref(false);
let map = null;
let customerLayer = null;
let pipeLayer = null;

const FILTERS = [
  { key: 'green', label: 'Hijau', color: '#22c55e', activeClass: 'bg-green-100 text-green-700 border-green-300', inactiveClass: 'bg-gray-100 text-gray-400 border-gray-200' },
  { key: 'blue', label: 'Biru', color: '#38bdf8', activeClass: 'bg-sky-100 text-sky-700 border-sky-300', inactiveClass: 'bg-gray-100 text-gray-400 border-gray-200' },
  { key: 'yellow', label: 'Kuning', color: '#eab308', activeClass: 'bg-yellow-100 text-yellow-700 border-yellow-300', inactiveClass: 'bg-gray-100 text-gray-400 border-gray-200' },
  { key: 'orange', label: 'Oranye', color: '#f97316', activeClass: 'bg-orange-100 text-orange-700 border-orange-300', inactiveClass: 'bg-gray-100 text-gray-400 border-gray-200' },
  { key: 'red', label: 'Merah', color: '#ef4444', activeClass: 'bg-red-100 text-red-700 border-red-300', inactiveClass: 'bg-gray-100 text-gray-400 border-gray-200' },
  { key: 'black', label: 'Hitam', color: '#1e293b', activeClass: 'bg-gray-700 text-white border-gray-700', inactiveClass: 'bg-gray-100 text-gray-400 border-gray-200' },
];

const filters = ref(FILTERS.map((f) => ({ ...f, count: 0 })));
const activeFilters = ref(FILTERS.map((f) => f.key));

function toggleFilter(key) {
  const idx = activeFilters.value.indexOf(key);
  if (idx >= 0) activeFilters.value.splice(idx, 1);
  else activeFilters.value.push(key);
  loadGeoData();
}

async function loadGeoData() {
  if (!map) return;

  let counts = Object.fromEntries(FILTERS.map((f) => [f.key, 0]));
  try {
    // Ringkasan warna (semua pelanggan, tanpa filter) — 1 query agregate di server.
    const params = props.zoneId ? { zone_id: props.zoneId } : {};
    const { data: res } = await api.get('/gis/customers/status-summary', { params });
    const sum = res?.data ?? res;
    if (sum && typeof sum === 'object') {
      Object.keys(counts).forEach((k) => { counts[k] = Number(sum[k] || 0); });
    }
  } catch { /* interceptor */ }
  filters.value.forEach((f) => { f.count = counts[f.key] || 0; });

  customerLayer.clearLayers();
  let visible = 0;
  try {
    const { data } = await api.get('/gis/customers', {
      params: {
        ...(props.zoneId ? { zone_id: props.zoneId } : {}),
        status: activeFilters.value.join(','),
      },
    });
    const pts = [];
    (data.features || []).forEach((feat) => {
      const p = feat.properties || {};
      const c = p.marker_color || (() => { const m = FILTERS.find((x) => x.key === p.status_color); return m ? m.color : '#94a3b8'; })();
      const lat = feat.geometry.coordinates[1];
      const lng = feat.geometry.coordinates[0];
      pts.push([lat, lng]);
      const marker = L.circleMarker([lat, lng], {
        radius: 6, fillColor: c, color: '#fff', weight: 1.4, fillOpacity: 0.95,
      });
      marker.bindPopup(`
        <div style="font-family:sans-serif;font-size:12px;min-width:180px;">
          <b>${p.name || p.customer_number}</b><br>
          No: ${p.customer_number}<br>
          Status: ${p.status_label || '-'}<br>
          Menunggak: ${p.arrears_months || 0} bulan<br>
          Total tunggakan: Rp ${Number(p.total_arrears || 0).toLocaleString('id-ID')}
        </div>`);
      marker.addTo(customerLayer);
      visible++;
    });
    if (pts.length > 1) map.fitBounds(L.latLngBounds(pts).pad(0.08), { maxZoom: 14 });
  } catch { /* interceptor */ }

  empty.value = visible === 0;

  try {
    pipeLayer.clearLayers();
    const { data: pipeData } = await api.get('/gis/pipes', { params: { per_page: 5000 } });
    (pipeData.data || []).forEach((pipe) => {
      const coords = pipe.geometry?.coordinates;
      if (!coords || coords.length < 2) return;
      const status = pipe.properties?.status ?? pipe.status;
      const dia = Number(pipe.properties?.diameter_mm || pipe.properties?.diameter || 0);
      // Tier: trunk(>=300) / distribusi(110-300) / sambungan-rumah(<=75).
      const style = status === 'rusak'
        ? { color: '#dc2626', weight: 4, opacity: 0.95 }
        : (dia >= 300 ? { color: '#1d4ed8', weight: 5, opacity: 0.75 }
          : dia >= 110 ? { color: '#2563eb', weight: 3, opacity: 0.8 }
          : { color: '#60a5fa', weight: 1.5, opacity: 0.7 });
      const line = Lpolyline(coords, style);
      if (dia >= 110 || status === 'rusak') {
        line.bindPopup(popupPipeHtml(pipe, status, dia));
      }
      line.addTo(pipeLayer);
    });
  } catch { /* interceptor */ }
}

function Lpolyline(coords, style) { return L.polyline(coords.map((cc) => [cc[1], cc[0]]), style); }
function popupPipeHtml(pipe, status, dia) {
  const len = pipe.properties?.length_meters ? '&plusmn; ' + Math.round(pipe.properties.length_meters) + ' m &middot; ' : '';
  return '<div style="font-family:sans-serif;font-size:12px;">'
    + '<b>' + (pipe.name || 'Pipa') + '</b><br>'
    + '&Oslash; ' + (dia || '-') + ' mm &middot; ' + (pipe.properties?.material || '-') + '<br>'
    + len + 'status: ' + (status || '-')
    + '</div>';
}

onMounted(async () => {
  await nextTick();
  if (!mapContainer.value || map) return;
  map = L.map(mapContainer.value, { preferCanvas: true }).setView([1.45, 109.35], 11);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(map);
  pipeLayer = L.featureGroup().addTo(map);
  customerLayer = L.featureGroup().addTo(map);
  await loadGeoData();
});

onBeforeUnmount(() => {
  if (map) { map.remove(); map = null; }
});

watch(() => props.zoneId, () => loadGeoData());
</script>
