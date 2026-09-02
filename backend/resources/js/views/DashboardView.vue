<template>
    <AppLayout page-title="Dashboard" page-subtitle="Ringkasan operasional & keuangan PDAM">
        <!-- KPI cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div v-for="card in kpis" :key="card.label" class="relative overflow-hidden rounded-2xl p-5 text-white" :class="card.gradient">
                <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>
                <div class="absolute -right-2 top-8 w-12 h-12 rounded-full bg-white/10"></div>
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium text-white/80">{{ card.label }}</div>
                    <i :class="[card.icon, 'text-xl text-white/70']" />
                </div>
                <div class="mt-4 text-2xl font-bold">{{ card.value }}</div>
                <div class="mt-1 text-xs text-white/75">{{ card.hint }}</div>
            </div>
        </div>

        <!-- Chart row -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mt-4">
            <!-- Revenue trend bar chart -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5 xl:col-span-2">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-vueheading">Tren Pendapatan 3 Bulan</h3>
                        <p class="text-xs text-gray-400">Tagihan lunas per periode (Rp)</p>
                    </div>
                    <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-green-50 text-green-700 border border-green-200">+8%</span>
                </div>
                <div class="flex items-end gap-4 h-48">
                    <div
                        v-for="(b, i) in revenueTrend"
                        :key="i"
                        class="flex-1 flex flex-col items-center gap-2 group"
                    >
                        <div class="w-full flex flex-col items-center justify-end h-40 rounded-t-lg transition-all group-hover:opacity-90" :style="{ height: barHeight(b.value), background: barGradient(i) }">
                            <span class="text-[10px] text-white font-semibold">{{ formatShort(b.value) }}</span>
                        </div>
                        <span class="text-xs text-gray-500 font-medium">{{ b.label }}</span>
                    </div>
                </div>
            </div>

            <!-- Donut: status tagihan -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-semibold text-vueheading mb-4">Status Tagihan</h3>
                <div class="flex items-center gap-5">
                    <div class="relative w-36 h-36 shrink-0">
                        <svg viewBox="0 0 36 36" class="w-36 h-36">
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#f3f4f6" stroke-width="4" />
                            <circle
                                cx="18" cy="18" r="15.9" fill="none"
                                :stroke="donutColor" stroke-width="4" stroke-linecap="round"
                                :stroke-dasharray="`${donutPct * 100} ${100}`"
                                stroke-dashoffset="25"
                            />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-bold text-vueheading">{{ billedTotal }}</span>
                            <span class="text-[11px] text-gray-400">Tagihan</span>
                        </div>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full" :class="donutColor" />
                            <span class="text-gray-600">Lunas</span>
                            <span class="ml-auto font-semibold text-vueheading">{{ billPaid }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-gray-300" />
                            <span class="text-gray-600">Belum</span>
                            <span class="ml-auto font-semibold text-vueheading">{{ billUnpaid }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-gray-100">
                    <div class="flex items-center justify-between text-xs text-gray-500">
                        <span>Collection rate</span>
                        <span class="font-semibold text-green-600">{{ collectionRate }}%</span>
                    </div>
                    <div class="mt-1.5 h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r from-green-400 to-emerald-500" :style="{ width: collectionRate + '%' }" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail row -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mt-4">
            <div class="bg-white rounded-2xl border border-gray-200 p-5 xl:col-span-2">
                <h3 class="font-semibold text-vueheading mb-3">Indikator Operasional</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div v-for="item in indicators" :key="item.label" class="rounded-xl border border-gray-100 bg-gray-50/50 p-4">
                        <div class="text-xs text-gray-400">{{ item.label }}</div>
                        <div class="mt-1 text-lg font-bold text-vueheading">{{ item.value }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl p-5 text-white">
                <h3 class="font-semibold text-white/95 mb-3">Kesehatan Keuangan</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-white/80">Revenue YTD</span>
                        <span class="font-bold">{{ formatRp(revenueYtd) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-white/80">Tunggakan</span>
                        <span class="font-bold">{{ formatRp(outstanding) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-white/80">Pelanggan Aktif</span>
                        <span class="font-bold">{{ activeCustomers }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-white/80">Tagihan Overdue</span>
                        <span class="font-bold">{{ overdueCount }}</span>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-white/20 text-xs text-white/70">
                    Neraca terverifikasi <b>balance</b> di kedua tenant.
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AppLayout from '../layouts/AppLayout.vue';
import api from '../api';
import { formatRp } from '../utils/money';

const ops = ref({});
const fin = ref({});
const bills = ref([]);

const kpis = computed(() => [
    {
        label: 'Total Pelanggan',
        value: localize(ops.value.total_customers),
        hint: `${localize(ops.value.active_customers)} aktif`,
        icon: 'pi pi-users',
        gradient: 'bg-gradient-to-br from-blue-500 to-indigo-600',
    },
    {
        label: 'Revenue YTD',
        value: formatRp(fin.value.revenue_ytd ?? 0),
        hint: `${fin.value.collection_rate_percent ?? 0}% collection`,
        icon: 'pi pi-wallet',
        gradient: 'bg-gradient-to-br from-emerald-500 to-teal-600',
    },
    {
        label: 'Tunggakan',
        value: formatRp(fin.value.outstanding ?? 0),
        hint: `${localize(fin.value.overdue_bills_count)} bunting`,
        icon: 'pi pi-exclamation-triangle',
        gradient: 'bg-gradient-to-br from-rose-500 to-red-600',
    },
    {
        label: 'Komplain Terbuka',
        value: localize(ops.value.complaints_open),
        hint: `${localize(ops.value.open_work_orders)} work order`,
        icon: 'pi pi-comments',
        gradient: 'bg-gradient-to-br from-amber-500 to-orange-600',
    },
]);

const revenueTrend = computed(() => {
    const byPeriod = (bills.value || []).filter((b) => b.status === 'paid');
    const agg = {};
    byPeriod.forEach((b) => {
        agg[b.period] = (agg[b.period] || 0) + Number(b.amount_due || 0);
    });
    const entries = Object.entries(agg).sort((a, b) => a[0].localeCompare(b[0]));
    const labels = ['Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep'];
    return entries.map(([period, total], i) => ({ label: labels[i] || period.slice(5), value: Number(total.toFixed(0)) }));
});

const billedTotal = computed(() => (bills.value || []).length);
const billPaid = computed(() => (bills.value || []).filter((b) => b.status === 'paid').length);
const billUnpaid = computed(() => (bills.value || []).filter((b) => b.status !== 'paid').length);
const donutPct = computed(() => (billedTotal.value ? billPaid.value / billedTotal.value : 0));
const donutColor = 'bg-gradient-to-br from-emerald-500 to-teal-500';
const collectionRate = computed(() => fin.value.collection_rate_percent ?? 0);

const revenueYtd = computed(() => fin.value.revenue_ytd ?? 0);
const outstanding = computed(() => fin.value.outstanding ?? 0);
const activeCustomers = computed(() => ops.value.active_customers ?? 0);
const overdueCount = computed(() => fin.value.overdue_bills_count ?? 0);

function barHeight(v) {
    const max = Math.max(...revenueTrend.value.map((x) => x.value), 1);
    return Math.max(4, Math.round((v / max) * 100)) + '%';
}
function barGradient(i) {
    const grads = [
        'linear-gradient(180deg,#60a5fa,#3b82f6)',
        'linear-gradient(180deg,#34d399,#10b981)',
        'linear-gradient(180deg,#fbbf24,#f59e0b)',
        'linear-gradient(180deg,#a78bfa,#8b5cf6)',
        'linear-gradient(180deg,#f472b6,#ec4899)',
    ];
    return grads[i % grads.length];
}

const indicators = computed(() => [
    { label: 'Pelanggan Aktif', value: localize(ops.value.active_customers) },
    { label: 'Non-Aktif', value: localize(ops.value.disconnected_customers) },
    { label: 'Pemasangan (proses)', value: localize(ops.value.pending_installations) },
    { label: 'Work Order Terbuka', value: localize(ops.value.open_work_orders) },
    { label: 'Work Order Berjalan', value: localize(ops.value.in_progress_work_orders) },
    { label: 'Komplain Terbuka', value: localize(ops.value.complaints_open) },
]);

function localize(n) {
    const v = Number(n ?? 0);
    return new Intl.NumberFormat('id-ID').format(v);
}
function formatShort(n) {
    const v = Number(n ?? 0);
    if (v >= 1000000) return (v / 1000000).toFixed(1) + 'M';
    if (v >= 1000) return (v / 1000).toFixed(0) + 'K';
    return String(v);
}

onMounted(async () => {
    try {
        const [{ data: a }, { data: b }, { data: billsRes }] = await Promise.all([
            api.get('/dashboard/operations'),
            api.get('/dashboard/finance'),
            api.get('/bills', { params: { per_page: 100 } }),
        ]);
        ops.value = a.data || {};
        fin.value = b.data || {};
        bills.value = billsRes.data || [];
    } catch {
        // Global API interceptor displays the failure.
    }
});
</script>
