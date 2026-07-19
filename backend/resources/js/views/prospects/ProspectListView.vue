<template>
    <AppLayout page-title="Pemasangan Baru">
        <DataTable :rows="prospects" :loading="loading">
            <template #columns>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">NIK</th>
            </template>
            <template #row="{ row }">
                <td class="px-4 py-3 font-medium">{{ row.full_name }}</td>
                <td class="px-4 py-3 text-sm">{{ row.nik }}</td>
            </template>
        </DataTable>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import DataTable from '../../components/shared/DataTable.vue';
import api from '../../api';

const prospects = ref([]);
const loading = ref(true);

onMounted(async () => {
    try { const { data } = await api.get('/prospects'); prospects.value = data.data || []; } catch { /* Global API interceptor displays the failure. */ }
    loading.value = false;
});
</script>
