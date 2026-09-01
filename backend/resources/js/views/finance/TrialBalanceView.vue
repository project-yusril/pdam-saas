<template>
    <AppLayout page-title="Neraca Saldo" page-subtitle="Total debit vs kredit per akun">
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

        <div class="mb-4 flex items-center gap-3">
            <span
                :class="summary.is_balanced ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium"
            >
                <i :class="['pi', summary.is_balanced ? 'pi-check-circle' : 'pi-exclamation-triangle']" />
                {{ summary.is_balanced ? 'SEIMBANG' : 'TIDAK SEIMBANG' }}
            </span>
            <span class="text-sm text-gray-600">Total Debit: {{ formatRp(summary.total_debit) }}</span>
            <span class="text-sm text-gray-600">Total Kredit: {{ formatRp(summary.total_credit) }}</span>
        </div>

        <DataTable :rows="rows" :loading="loading">
            <template #columns>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Akun</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Kredit</th>
            </template>
            <template #row="{ row }">
                <td class="px-4 py-3 text-sm">{{ row.account_code }}</td>
                <td class="px-4 py-3">{{ row.account_name }}</td>
                <td class="px-4 py-3 text-sm text-right">{{ row.debit ? formatRp(row.debit) : '—' }}</td>
                <td class="px-4 py-3 text-sm text-right">{{ row.credit ? formatRp(row.credit) : '—' }}</td>
            </template>
        </DataTable>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import DataTable from '../../components/shared/DataTable.vue';
import api from '../../api';
import { formatRp } from '../../utils/money';
import { currentPeriod } from '../../utils/dates';

const from = ref('2026-01');
const to = ref(currentPeriod());
const rows = ref([]);
const summary = ref({ total_debit: 0, total_credit: 0, is_balanced: false });
const loading = ref(true);

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/reports/accounting/trial-balance', { params: { from: from.value, to: to.value } });
        const payload = data.data || {};
        rows.value = payload.rows || [];
        summary.value = {
            total_debit: payload.total_debit,
            total_credit: payload.total_credit,
            is_balanced: payload.is_balanced,
        };
    } catch {
        rows.value = [];
    }
    loading.value = false;
}

onMounted(load);
</script>
