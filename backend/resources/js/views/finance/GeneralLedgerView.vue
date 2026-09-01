<template>
    <AppLayout page-title="Buku Jurnal" page-subtitle="Mutasi transaksi per akun (Buku Besar)">
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4 flex flex-wrap items-end gap-4">
            <label class="flex flex-col text-sm">
                <span class="text-gray-500 mb-1">Dari Periode</span>
                <input v-model="from" type="month" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </label>
            <label class="flex flex-col text-sm">
                <span class="text-gray-500 mb-1">Sampai Periode</span>
                <input v-model="to" type="month" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </label>
            <button
                @click="load"
                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg"
            >
                Muat Laporan
            </button>
        </div>

        <DataTable :rows="rows" :loading="loading">
            <template #columns>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Akun</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deskripsi</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Kredit</th>
            </template>
            <template #row="{ row }">
                <td class="px-4 py-3 text-sm whitespace-nowrap">{{ row.date }}</td>
                <td class="px-4 py-3 text-sm">{{ row.code }}</td>
                <td class="px-4 py-3 text-sm">{{ row.name }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ row.description }}</td>
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

// Periode demo sesuai SEED_DATA (Jan–Jun 2026).
const from = ref('2026-01');
const to = ref(currentPeriod());
const rows = ref([]);
const loading = ref(true);

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/reports/accounting/general-ledger', { params: { from: from.value, to: to.value } });
        const ledger = data.data || [];
        const flat = [];
        ledger.forEach((account) => {
            (account.lines || []).forEach((line) => {
                flat.push({
                    date: line.date,
                    code: account.account_code,
                    name: account.account_name,
                    description: line.description,
                    debit: line.type === 'DEBIT' ? line.amount : 0,
                    credit: line.type === 'KREDIT' ? line.amount : 0,
                });
            });
        });
        rows.value = flat;
    } catch {
        rows.value = [];
    }
    loading.value = false;
}

onMounted(load);
</script>
