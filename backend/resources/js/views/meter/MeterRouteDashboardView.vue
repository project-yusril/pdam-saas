<template>
    <AppLayout page-title="Dashboard Rute" page-subtitle="Progres baca meter per rute">
        <div class="flex flex-wrap items-end gap-4 mb-4">
            <label class="flex flex-col text-sm">
                <span class="text-gray-500 mb-1">Periode Baca</span>
                <input v-model="period" type="month" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </label>
            <button @click="load" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg">Muat</button>
        </div>

        <div v-if="summary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <StatCard label="Pelanggan Aktif" :value="localize(summary.total_customers)" icon="pi pi-users" icon-bg="bg-primary-100" icon-color="text-primary-600" />
            <StatCard label="Sudah Dibaca" :value="localize(summary.total_read)" icon="pi pi-check-circle" icon-bg="bg-green-100" icon-color="text-green-600" />
            <StatCard label="Belum Dibaca" :value="localize(summary.total_remaining)" icon="pi pi-clock" icon-bg="bg-orange-100" icon-color="text-orange-600" />
            <StatCard label="Terflag" :value="localize(summary.total_flagged)" icon="pi pi-exclamation-triangle" icon-bg="bg-red-100" icon-color="text-red-600" />
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 font-semibold text-vueheading">Progres per Rute</div>
            <DataTable :rows="routes" :loading="loading">
                <template #columns>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rute</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Terbaca</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Terflag</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-1/3">Progres</th>
                </template>
                <template #row="{ row }">
                    <td class="px-4 py-3">
                        <div class="font-medium text-vueheading">{{ row.name }}</div>
                        <div class="text-xs text-gray-400">{{ row.code }} · {{ row.total_customers }} pelanggan</div>
                    </td>
                    <td class="px-4 py-3 text-sm text-right">{{ row.read }} / {{ row.total_customers }}</td>
                    <td class="px-4 py-3 text-sm text-right">{{ row.flagged }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-full h-2 bg-gray-100 rounded-full">
                                <div class="h-full bg-primary-600 rounded-full transition-all" :style="{ width: (row.progress_percent || 0) + '%' }" />
                            </div>
                            <span class="text-xs font-medium text-vuetext w-12 text-right">{{ row.progress_percent || 0 }}%</span>
                        </div>
                    </td>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import StatCard from '../../components/shared/StatCard.vue';
import DataTable from '../../components/shared/DataTable.vue';
import api from '../../api';

// Periode baca demo: 2026-04·05·06 (sesuai ReadingPeriod). Default yang terakhir.
const period = ref('2026-06');
const loading = ref(true);
const routes = ref([]);
const summary = ref(null);

function localize(n) {
    return Number(n ?? 0).toLocaleString('id-ID');
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/meter-readings/route-progress', { params: { period: period.value } });
        const payload = data.data || {};
        routes.value = payload.routes || [];
        summary.value = payload.summary || null;
    } catch {
        routes.value = [];
        summary.value = null;
    }
    loading.value = false;
}

onMounted(load);
</script>
