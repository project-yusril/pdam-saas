<template>
    <AppLayout page-title="Laporan Baca Meter" page-subtitle="Rincian baca meter per rute & periode — angka lalu vs kini, pemakaian (m³), tarif, foto, dan petugas">
        <!-- Filter -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-sm font-medium text-vuetext mb-1">Periode</label>
                <select v-model="period" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option v-for="p in periods" :key="p" :value="p">{{ p }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-vuetext mb-1">Rute Baca Meter</label>
                <select v-model="routeId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Semua Rute</option>
                    <option v-for="r in routes" :key="r.id" :value="r.id">{{ r.code }} — {{ r.name }}</option>
                </select>
            </div>
            <button
                @click="load"
                :disabled="loading"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 disabled:opacity-60"
            >
                <i class="pi pi-search text-xs" /> Terapkan
            </button>
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <StatCard label="Total Pelanggan" :value="summary.total_customers" icon="pi pi-users" icon-bg="bg-blue-100" icon-color="text-blue-600" />
            <StatCard label="Terbaca" :value="summary.read" icon="pi pi-check-circle" icon-bg="bg-green-100" icon-color="text-green-600" />
            <StatCard label="Belum Dibaca" :value="summary.unread" icon="pi pi-clock" icon-bg="bg-amber-100" icon-color="text-amber-600" />
            <StatCard label="Total Pemakaian" :value="summary.total_usage_m3 + ' m³'" icon="pi pi-water" icon-bg="bg-cyan-100" icon-color="text-cyan-600" />
        </div>

        <!-- Info rute & petugas -->
        <div v-if="info.route" class="bg-white rounded-xl border border-gray-200 px-4 py-3 mb-4 text-sm flex flex-wrap gap-x-6 gap-y-1">
            <span class="text-vuetext"><span class="font-semibold">Rute:</span> {{ info.route.code }} — {{ info.route.name }}</span>
            <span v-if="info.route.zone"><span class="font-semibold">Wilayah:</span> {{ info.route.zone }}</span>
            <span><span class="font-semibold">Petugas:</span> {{ info.route.officer || 'Belum ditugaskan' }}</span>
            <span><span class="font-semibold">Periode:</span> {{ info.period }} (pembanding: {{ info.previous_period }})</span>
        </div>

        <!-- Tabel laporan -->
        <DataTable :rows="rows" :loading="loading">
            <template #columns>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Pelanggan</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jalan</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gol.</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Baca Lalu (m³)</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Baca Kini (m³)</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pemakaian (m³)</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Biaya</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Foto</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Petugas</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            </template>
            <template #row="{ row }">
                <td class="px-4 py-3 text-sm font-mono">{{ row.customer_number }}</td>
                <td class="px-4 py-3 text-sm font-medium text-vueheading">{{ row.full_name }}</td>
                <td class="px-4 py-3 text-sm">{{ row.street || row.address_detail || '-' }}</td>
                <td class="px-4 py-3 text-sm">{{ row.tariff_code || '-' }}</td>
                <td class="px-4 py-3 text-sm text-right">{{ row.previous_reading }}</td>
                <td class="px-4 py-3 text-sm text-right">{{ row.current_reading ?? '-' }}</td>
                <td class="px-4 py-3 text-sm text-right font-medium">
                    {{ row.usage_m3 === null ? '—' : row.usage_m3 }}
                </td>
                <td class="px-4 py-3 text-sm text-right">{{ row.amount_due === null ? '—' : formatRp(row.amount_due) }}</td>
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <a v-if="row.photo_meter_url" :href="row.photo_meter_url" target="_blank" class="p-1.5 rounded-md hover:bg-gray-100 text-primary-600" title="Foto meter">
                            <i class="pi pi-camera" />
                        </a>
                        <a v-if="row.photo_house_url" :href="row.photo_house_url" target="_blank" class="p-1.5 rounded-md hover:bg-gray-100 text-gray-500" title="Foto rumah">
                            <i class="pi pi-home" />
                        </a>
                        <span v-if="!row.photo_meter_url" class="text-gray-300">—</span>
                    </div>
                </td>
                <td class="px-4 py-3 text-sm">{{ row.reader || '-' }}</td>
                <td class="px-4 py-3">
                    <span v-if="row.verifier" class="text-xs text-gray-400 block">verif: {{ row.verifier }}</span>
                    <span
                        class="px-2 py-0.5 text-xs rounded-full inline-block"
                        :class="readingBadge(row)"
                    >{{ statusLabel(row) }}</span>
                </td>
            </template>
        </DataTable>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import StatCard from '../../components/shared/StatCard.vue';
import DataTable from '../../components/shared/DataTable.vue';
import api from '../../api';
import { formatRp } from '../../utils/money.js';

const routes = ref([]);
const periods = ref([]);
const period = ref('');
const routeId = ref('');
const loading = ref(true);
const rows = ref([]);
const summary = ref({ total_customers: 0, read: 0, unread: 0, total_usage_m3: 0 });
const info = ref({ period: '', previous_period: '', route: null });

// Periode default: bulan penuh terakhir (dari September 2025 s/d sekarang).
function buildPeriods() {
    const list = [];
    const start = new Date(Date.UTC(2025, 8, 1)); // Sept 2025
    const now = new Date();
    let cur = new Date(Date.UTC(2025, 8, 1));
    while (cur <= now || list.length === 0) {
        const y = cur.getUTCFullYear();
        const m = String(cur.getUTCMonth() + 1).padStart(2, '0');
        list.push(`${y}-${m}`);
        cur = new Date(Date.UTC(cur.getUTCFullYear(), cur.getUTCMonth() + 1, 1));
    }
    if (list.length > 1) list.pop(); // hapus bulan berjalan (belum penuh)
    return list.reverse();
}

async function loadRoutes() {
    try {
        const { data } = await api.get('/meter-routes');
        routes.value = data.data || [];
    } catch {
        routes.value = [];
    }
}

async function load() {
    if (!period.value) return;
    loading.value = true;
    try {
        const params = { period: period.value };
        if (routeId.value) params.route_id = routeId.value;
        const { data } = await api.get('/meter-readings/report', { params });
        const d = data.data || {};
        rows.value = d.rows || [];
        summary.value = d.summary || { total_customers: 0, read: 0, unread: 0, total_usage_m3: 0 };
        info.value = { period: d.period, previous_period: d.previous_period, route: d.route };
    } catch {
        rows.value = [];
    }
    loading.value = false;
}

function readingBadge(row) {
    if (row.current_reading === null) return 'bg-gray-100 text-gray-500';
    if (row.is_flagged || row.is_rollover) return 'bg-amber-100 text-amber-700';
    if (row.bill_status === 'paid') return 'bg-green-100 text-green-700';
    return 'bg-blue-100 text-blue-700';
}

function statusLabel(row) {
    if (row.current_reading === null) return 'Belum dibaca';
    if (row.is_rollover) return 'Rollover';
    if (row.is_flagged) return 'Flag: ' + (row.flag_reason || 'perlu verifikasi');
    return row.reading_type === 'estimated' ? 'Estimasi' : 'Terverifikasi';
}

onMounted(async () => {
    periods.value = buildPeriods();
    period.value = periods.value[0] || '';
    await loadRoutes();
    await load();
});
</script>
