<template>
    <AppLayout page-title="Neraca" page-subtitle="Aset = Kewajiban + Ekuitas">
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

        <div class="mb-4">
            <span
                :class="report.is_balanced ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium"
            >
                <i :class="['pi', report.is_balanced ? 'pi-check-circle' : 'pi-exclamation-triangle']" />
                {{ report.is_balanced ? 'BALANCE' : 'TIDAK BALANCE' }}
            </span>
        </div>

        <div v-if="loading" class="text-sm text-gray-400">Memuat laporan…</div>
        <div v-else-if="noData" class="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-400">
            <i class="pi pi-inbox text-3xl mb-2 block" /> Tidak ada data akuntansi
        </div>
        <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <section class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 font-semibold text-sm flex items-center gap-2"><i class="pi pi-building text-blue-600" /> Aset</div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in report.assets" :key="row.account_code">
                            <td class="px-4 py-3">{{ row.account_name }}</td>
                            <td class="px-4 py-3 text-right">{{ formatRp(row.amount) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-gray-200 bg-gray-50">
                        <tr><td class="px-4 py-3 font-semibold">Total Aset</td><td class="px-4 py-3 text-right font-semibold">{{ formatRp(report.total_asset) }}</td></tr>
                    </tfoot>
                </table>
            </section>

            <section class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 font-semibold text-sm flex items-center gap-2"><i class="pi pi-exclamation-circle text-orange-600" /> Kewajiban</div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in report.liabilities" :key="row.account_code">
                            <td class="px-4 py-3">{{ row.account_name }}</td>
                            <td class="px-4 py-3 text-right">{{ formatRp(row.amount) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-gray-200 bg-gray-50">
                        <tr><td class="px-4 py-3 font-semibold">Total Kewajiban</td><td class="px-4 py-3 text-right font-semibold">{{ formatRp(report.total_liability) }}</td></tr>
                    </tfoot>
                </table>
            </section>

            <section class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 font-semibold text-sm flex items-center gap-2"><i class="pi pi-chart-bar text-green-600" /> Ekuitas</div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in report.equity" :key="row.account_code">
                            <td class="px-4 py-3">{{ row.account_name }}</td>
                            <td class="px-4 py-3 text-right">{{ formatRp(row.amount) }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 text-gray-600">Laba Berjalan</td>
                            <td class="px-4 py-3 text-right">{{ formatRp(report.current_earnings) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-gray-200 bg-gray-50">
                        <tr><td class="px-4 py-3 font-semibold">Total Ekuitas</td><td class="px-4 py-3 text-right font-semibold">{{ formatRp(report.total_equity) }}</td></tr>
                    </tfoot>
                </table>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import api from '../../api';
import { formatRp } from '../../utils/money';
import { currentPeriod } from '../../utils/dates';

const from = ref('2026-01');
const to = ref(currentPeriod());
const report = ref({});
const loading = ref(true);
const noData = computed(() => !loading.value && !(report.value.assets || []).length && !report.value.total_asset);

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/reports/accounting/balance-sheet', { params: { from: from.value, to: to.value } });
        report.value = data.data || {};
    } catch {
        report.value = {};
    }
    loading.value = false;
}

onMounted(load);
</script>
