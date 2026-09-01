<template>
    <AppLayout page-title="Arus Kas" page-subtitle="Mutasi kas & bank pada periode berjalan">
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4 flex flex-wrap items-end gap-4">
            <label class="flex flex-col text-sm">
                <span class="text-gray-500 mb-1">Dari Periode</span>
                <input v-model="from" type="month" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </label>
            <label class="flex flex-col text-sm">
                <span class="text-gray-500 mb-1">Sampai Periode</span>
                <input v-model="to" type="month" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </label>
            <button @click="load" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg">Muat Laporan</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <StatCard label="Kas Masuk" :value="formatRp(report.inflow)" icon="pi pi-arrow-down-left" icon-bg="bg-green-100" icon-color="text-green-600" />
            <StatCard label="Kas Keluar" :value="formatRp(report.outflow)" icon="pi pi-arrow-up-right" icon-bg="bg-red-100" icon-color="text-red-600" />
            <StatCard label="Arus Kas Bersih" :value="formatRp(report.net)" icon="pi pi-chart-line" icon-bg="bg-blue-100" icon-color="text-blue-600" />
        </div>

        <DataTable :rows="lines" :loading="loading">
            <template #columns>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deskripsi</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Jenis</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Jumlah</th>
            </template>
            <template #row="{ row }">
                <td class="px-4 py-3 text-sm whitespace-nowrap">{{ row.date }}</td>
                <td class="px-4 py-3 text-sm">{{ row.description }}</td>
                <td class="px-4 py-3 text-sm text-right">
                    <span :class="row.direction === 'in' ? 'text-green-600' : 'text-red-600'" class="font-medium">{{ row.direction === 'in' ? 'Masuk' : 'Keluar' }}</span>
                </td>
                <td class="px-4 py-3 text-sm text-right">{{ formatRp(row.amount) }}</td>
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
import { formatRp } from '../../utils/money';
import { currentPeriod } from '../../utils/dates';

const from = ref('2026-01');
const to = ref(currentPeriod());
const report = ref({ inflow: 0, outflow: 0, net: 0, lines: [] });
const loading = ref(true);
const lines = computed(() => report.value.lines || []);

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/reports/accounting/cash-flow', { params: { from: from.value, to: to.value } });
        report.value = data.data || report.value;
    } catch {
        report.value = { inflow: 0, outflow: 0, net: 0, lines: [] };
    }
    loading.value = false;
}

onMounted(load);
</script>
