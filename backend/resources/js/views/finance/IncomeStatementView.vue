<template>
    <AppLayout page-title="Laba Rugi" page-subtitle="Pendapatan minus beban pada periode berjalan">
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

        <div v-if="loading" class="text-sm text-gray-400">Memuat laporan…</div>
        <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 font-semibold text-sm text-green-700 flex items-center gap-2">
                    <i class="pi pi-chart-line" /> Pendapatan
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in report.revenue" :key="row.account_code">
                            <td class="px-4 py-3">{{ row.account_name }}</td>
                            <td class="px-4 py-3 text-right">{{ formatRp(row.amount) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-gray-200 bg-gray-50">
                        <tr>
                            <td class="px-4 py-3 font-semibold">Total Pendapatan</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ formatRp(report.total_revenue) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 font-semibold text-sm text-red-700 flex items-center gap-2">
                    <i class="pi pi-exclamation-circle" /> Beban
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in report.expense" :key="row.account_code">
                            <td class="px-4 py-3">{{ row.account_name }}</td>
                            <td class="px-4 py-3 text-right">{{ formatRp(row.amount) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-gray-200 bg-gray-50">
                        <tr>
                            <td class="px-4 py-3 font-semibold">Total Beban</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ formatRp(report.total_expense) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div v-if="!loading && report.net_income !== null" class="mt-6 flex items-center justify-between bg-white rounded-xl border border-gray-200 p-5">
            <span class="text-sm font-semibold text-gray-700">Laba / Rugi Bersih</span>
            <span :class="report.net_income >= 0 ? 'text-green-700' : 'text-red-700'" class="text-lg font-bold">{{ formatRp(report.net_income) }}</span>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import api from '../../api';
import { formatRp } from '../../utils/money';
import { currentPeriod } from '../../utils/dates';

const from = ref('2026-01');
const to = ref(currentPeriod());
const report = ref({ revenue: [], expense: [], total_revenue: 0, total_expense: 0, net_income: null });
const loading = ref(true);

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/reports/accounting/income-statement', { params: { from: from.value, to: to.value } });
        report.value = data.data || report.value;
    } catch {
        report.value = { revenue: [], expense: [], total_revenue: 0, total_expense: 0, net_income: null };
    }
    loading.value = false;
}

onMounted(load);
</script>
