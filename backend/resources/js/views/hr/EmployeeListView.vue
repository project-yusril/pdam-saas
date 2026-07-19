<template>
    <AppLayout page-title="Data Pegawai">
        <DataTable :rows="employees" :loading="loading" :page="page" :per-page="perPage" :total="total">
            <template #columns>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">NIP</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jabatan</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            </template>
            <template #row="{ row }">
                <td class="px-4 py-3 text-sm">{{ row.nip || '-' }}</td>
                <td class="px-4 py-3 font-medium">{{ row.name }}</td>
                <td class="px-4 py-3 text-sm">{{ row.position?.name || '-' }}</td>
                <td class="px-4 py-3 text-sm">{{ row.unit?.name || '-' }}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 text-xs rounded-full" :class="row.employment_status === 'tetap' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'">{{ row.employment_status }}</span></td>
            </template>
        </DataTable>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import DataTable from '../../components/shared/DataTable.vue';
import api from '../../api';

const employees = ref([]);
const loading = ref(true);
const page = ref(1);
const perPage = ref(25);
const total = ref(0);

onMounted(async () => {
    try { const { data } = await api.get('/employees', { params: { page: page.value, per_page: perPage.value } }); employees.value = data.data || []; total.value = data.meta?.total || 0; } catch { /* Global API interceptor displays the failure. */ }
    loading.value = false;
});
</script>
