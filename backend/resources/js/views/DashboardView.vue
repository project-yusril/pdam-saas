<template>
    <AppLayout page-title="Dashboard" page-subtitle="Ringkasan operasional & keuangan">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard
                label="Total Pelanggan"
                :value="localize(ops.total_customers)"
                icon="pi pi-users"
                icon-bg="bg-blue-100"
                icon-color="text-blue-600"
            />
            <StatCard
                label="Revenue YTD"
                :value="formatRp(fin.revenue_ytd ?? 0)"
                icon="pi pi-wallet"
                icon-bg="bg-green-100"
                icon-color="text-green-600"
            />
            <StatCard
                label="Tunggakan"
                :value="formatRp(fin.outstanding ?? 0)"
                icon="pi pi-exclamation-triangle"
                icon-bg="bg-red-100"
                icon-color="text-red-600"
            />
            <StatCard
                label="Komplain Terbuka"
                :value="localize(ops.complaints_open)"
                icon="pi pi-comments"
                icon-bg="bg-purple-100"
                icon-color="text-purple-600"
            />
        </div>

        <div class="mt-6 bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 font-semibold text-sm text-gray-700">
                Indikator Operasional
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="item in indicators" :key="item.label">
                        <td class="px-4 py-3 text-gray-600">{{ item.label }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-800">{{ item.value }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AppLayout from '../layouts/AppLayout.vue';
import StatCard from '../components/shared/StatCard.vue';
import api from '../api';
import { formatRp } from '../utils/money';

const ops = ref({});
const fin = ref({});

const indicators = computed(() => [
    { label: 'Pelanggan Aktif', value: localize(ops.value.active_customers) },
    { label: 'Pelanggan Non-Aktif', value: localize(ops.value.disconnected_customers) },
    { label: 'Pemasangan Baru (proses)', value: localize(ops.value.pending_installations) },
    { label: 'Work Order Terbuka', value: localize(ops.value.open_work_orders) },
    { label: 'Work Order Berjalan', value: localize(ops.value.in_progress_work_orders) },
    { label: 'Collection Rate', value: `${fin.value.collection_rate_percent ?? 0}%` },
    { label: 'Tagihan Overdue', value: localize(fin.value.overdue_bills_count) },
]);

function localize(n) {
    return Number(n ?? 0).toLocaleString('id-ID');
}

onMounted(async () => {
    try {
        const [{ data: a }, { data: b }] = await Promise.all([
            api.get('/dashboard/operations'),
            api.get('/dashboard/finance'),
        ]);
        ops.value = a.data || {};
        fin.value = b.data || {};
    } catch {
        // Global API interceptor menampilkan error.
    }
});
</script>
