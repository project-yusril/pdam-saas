<template>
    <AppLayout page-title="Rute Baca Meter">
        <DataTable :rows="routes" :loading="loading">
            <template #columns>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Rute</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Petugas</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jumlah</th>
            </template>
            <template #row="{ row }">
                <td class="px-4 py-3 font-medium">{{ row.name }}</td>
                <td class="px-4 py-3">{{ row.officer_name || '-' }}</td>
                <td class="px-4 py-3">{{ row.customer_count }}</td>
            </template>
        </DataTable>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import DataTable from '../../components/shared/DataTable.vue';
import api from '../../api';

const routes = ref([]);
const loading = ref(true);

onMounted(async () => {
    try { const { data } = await api.get('/meter-routes'); routes.value = data.data || []; } catch { /* Global API interceptor displays the failure. */ }
    loading.value = false;
});
</script>
