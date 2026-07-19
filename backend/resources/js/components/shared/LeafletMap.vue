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
    <div ref="mapContainer" class="w-full rounded-xl border border-gray-200" style="height: 500px;"></div>
    <div class="mt-2 flex gap-4 text-xs text-gray-500">
      <span>🟢 Lunas</span><span>⚪ Belum Bayar</span><span>🟡 Menunggak 2</span><span>🔴 Menunggak 3+</span><span>⚫ Isolir</span>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import api from '../../api';

const props = defineProps({
  zoneId: { type: Number, default: null },
  height: { type: String, default: '500px' },
});

const mapContainer = ref(null);
const mapInstance = ref(null);
const geoLayer = ref(null);
const markerLayer = ref(null);
const activeFilters = ref(['green', 'white', 'yellow', 'red', 'black']);
const filters = ref([
  { key: 'green', label: 'Hijau', count: 0, activeClass: 'bg-green-100 text-green-700 border-green-300', inactiveClass: 'bg-gray-100 text-gray-400 border-gray-200' },
  { key: 'white', label: 'Putih', count: 0, activeClass: 'bg-gray-100 text-gray-600 border-gray-300', inactiveClass: 'bg-gray-100 text-gray-400 border-gray-200' },
  { key: 'yellow', label: 'Kuning', count: 0, activeClass: 'bg-yellow-100 text-yellow-700 border-yellow-300', inactiveClass: 'bg-gray-100 text-gray-400 border-gray-200' },
  { key: 'red', label: 'Merah', count: 0, activeClass: 'bg-red-100 text-red-700 border-red-300', inactiveClass: 'bg-gray-100 text-gray-400 border-gray-200' },
  { key: 'black', label: 'Hitam', count: 0, activeClass: 'bg-gray-700 text-white border-gray-700', inactiveClass: 'bg-gray-100 text-gray-400 border-gray-200' },
]);

const colorMap = { green: '#22c55e', white: '#94a3b8', yellow: '#eab308', red: '#ef4444', black: '#1e293b' };

function toggleFilter(key) {
  const idx = activeFilters.value.indexOf(key);
  if (idx >= 0) activeFilters.value.splice(idx, 1);
  else activeFilters.value.push(key);
  if (geoLayer.value) loadGeoData();
}

function createIconHtml(color) {
  return `<div style="width:16px;height:16px;border-radius:50%;background:${color};border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,0.3);"></div>`;
}

async function loadGeoData() {
  try {
    const params = { bbox: '' };
    if (props.zoneId) params.zone_id = props.zoneId;
    const { data } = await api.get('/gis/customers', { params });

    // Count by color
    const counts = { green: 0, white: 0, yellow: 0, red: 0, black: 0 };
    (data.features || []).forEach(f => {
      const c = f.properties?.status_color;
      if (counts[c] !== undefined) counts[c]++;
    });
    filters.value.forEach(f => { f.count = counts[f.key] || 0; });
  } catch { /* Global API interceptor displays the failure. */ }

  try {
    const { data: pipeData } = await api.get('/gis/pipes');
    if (geoLayer.value) {
      geoLayer.value.clearLayers();
      if (pipeData?.data?.length) {
        pipeData.data.forEach(pipe => {
          if (pipe.geometry?.coordinates?.length >= 2) {
            const latlngs = pipe.geometry.coordinates.map(c => [c[1], c[0]]);
            const polyline = window.L.polyline(latlngs, {
              color: pipe.properties?.status === 'rusak' ? '#ef4444' : '#3b82f6',
              weight: pipe.properties?.diameter > 100 ? 4 : 2,
              opacity: 0.8,
            });
            polyline.bindPopup(`<b>${pipe.name || 'Pipa'}</b><br>Diameter: ${pipe.properties?.diameter || '-'}mm<br>Material: ${pipe.properties?.material || '-'}<br>Status: ${pipe.properties?.status || '-'}`);
            polyline.addTo(geoLayer.value);
          }
        });
      }
    }

    // Load customer points separately for color filtering
    const { data: custData } = await api.get('/gis/customers', {
      params: {
        ...(props.zoneId ? { zone_id: props.zoneId } : {}),
        status: activeFilters.value.join(','),
      },
    });

    if (markerLayer.value) markerLayer.value.clearLayers();
    (custData.features || []).forEach(f => {
      const props = f.properties || {};
      if (f.geometry?.coordinates?.length >= 2) {
        const latlng = [f.geometry.coordinates[1], f.geometry.coordinates[0]];
        const color = colorMap[props.status_color] || '#94a3b8';
        const marker = window.L.circleMarker(latlng, {
          radius: 6,
          fillColor: color,
          color: '#fff',
          weight: 1,
          fillOpacity: 0.9,
        });
        marker.bindPopup(`
          <div style="font-family:sans-serif;font-size:12px;">
            <b>${props.name || props.customer_number}</b><br>
            No: ${props.customer_number}<br>
            Status: ${props.status_label}<br>
            Tunggakan: ${props.arrears_months} bulan<br>
            Total: Rp ${Number(props.total_arrears || 0).toLocaleString()}
          </div>
        `);
        if (markerLayer.value) marker.addTo(markerLayer.value);
      }
    });
  } catch { /* Global API interceptor displays the failure. */ }
}

onMounted(async () => {
  await nextTick();
  if (!mapContainer.value || !window.L) return;

  mapInstance.value = window.L.map(mapContainer.value).setView([-0.0, 117.5], 12);
  window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 19,
  }).addTo(mapInstance.value);

  geoLayer.value = window.L.featureGroup().addTo(mapInstance.value);
  markerLayer.value = window.L.featureGroup().addTo(mapInstance.value);

  await loadGeoData();
});

watch(() => props.zoneId, () => { if (mapInstance.value) loadGeoData(); });
</script>
