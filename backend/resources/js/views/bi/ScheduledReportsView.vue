<template>
    <AppLayout page-title="Laporan Terjadwal" page-subtitle="Buat export CSV atau HTML yang disimpan secara privat">
        <form class="grid gap-3 rounded-xl border bg-white p-5 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="create">
            <input v-model="form.name" required maxlength="150" placeholder="Nama laporan" class="rounded-lg border px-3 py-2 text-sm" />
            <select v-model="form.dataset" class="rounded-lg border px-3 py-2 text-sm"><option v-for="dataset in datasets" :key="dataset.name" :value="dataset.name">{{ dataset.label }}</option></select>
            <select v-model="form.format" class="rounded-lg border px-3 py-2 text-sm"><option value="csv">CSV</option><option value="html">HTML</option></select>
            <select v-model="form.frequency" class="rounded-lg border px-3 py-2 text-sm"><option value="daily">Harian</option><option value="weekly">Mingguan</option><option value="monthly">Bulanan</option></select>
            <input v-model="form.local_time" required type="time" class="rounded-lg border px-3 py-2 text-sm" />
            <input v-if="form.frequency === 'weekly'" v-model.number="form.day_of_week" type="number" min="0" max="6" class="rounded-lg border px-3 py-2 text-sm" placeholder="Hari (0-6)" />
            <input v-if="form.frequency === 'monthly'" v-model.number="form.day_of_month" type="number" min="1" max="31" class="rounded-lg border px-3 py-2 text-sm" placeholder="Tanggal" />
            <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white">Buat Jadwal</button>
        </form>
        <div class="mt-5 overflow-hidden rounded-xl border bg-white">
            <div v-for="report in reports" :key="report.id" class="flex flex-wrap items-center justify-between gap-3 border-b p-4 last:border-0">
                <div><p class="font-medium text-gray-900">{{ report.name }}</p><p class="text-xs text-gray-500">{{ report.dataset }} · {{ report.format.toUpperCase() }} · berikutnya {{ date(report.next_run_at) }}</p></div>
                <div class="flex gap-2"><button class="rounded-lg border px-3 py-1.5 text-sm" @click="run(report)">Jalankan</button><button class="rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-600" @click="remove(report)">Hapus</button></div>
            </div>
            <p v-if="!reports.length" class="p-6 text-center text-sm text-gray-500">Belum ada jadwal.</p>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../../api';
import AppLayout from '../../layouts/AppLayout.vue';

const reports = ref([]);
const datasets = [
    { name: 'bills', label: 'Tagihan', columns: ['bill_number', 'period', 'amount_due', 'status', 'due_date'] },
    { name: 'customers', label: 'Pelanggan', columns: ['customer_number', 'status', 'installation_date'] },
    { name: 'payments', label: 'Pembayaran', columns: ['payment_number', 'payment_type', 'amount', 'status', 'paid_at'] },
];
const form = reactive({ name: '', dataset: 'bills', format: 'csv', frequency: 'daily', timezone: Intl.DateTimeFormat().resolvedOptions().timeZone, local_time: '08:00', day_of_week: 1, day_of_month: 1 });
const selected = computed(() => datasets.find((item) => item.name === form.dataset));

async function load() { reports.value = (await api.get('/bi/scheduled-reports')).data.data; }
async function create() { await api.post('/bi/scheduled-reports', { ...form, columns: selected.value.columns }); form.name = ''; await load(); }
async function run(report) { await api.post(`/bi/scheduled-reports/${report.id}/run`); }
async function remove(report) { await api.delete(`/bi/scheduled-reports/${report.id}`); await load(); }
function date(value) { return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '-'; }
onMounted(load);
</script>
