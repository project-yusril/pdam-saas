<template>
    <AppLayout page-title="Manajemen Wilayah" page-subtitle="Kelola cabang dan wilayah layanan PDAM">
        <DataTable :rows="zones" :loading="loading">
            <template #columns>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
            </template>
            <template #row="{ row }">
                <td class="px-4 py-3 text-sm">{{ row.code }}</td>
                <td class="px-4 py-3">{{ row.name }}</td>
                <td class="px-4 py-3"><span class="inline-block px-2 py-0.5 text-xs rounded-full" :class="row.is_main ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'">{{ row.is_main ? 'Utama' : 'Cabang' }}</span></td>
                <td class="px-4 py-3 text-right"><router-link :to="'/zones/' + row.id" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Detail</router-link></td>
            </template>
        </DataTable>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import DataTable from '../../components/shared/DataTable.vue';
import api from '../../api';

const zones = ref([]);
const loading = ref(true);

onMounted(async () => {
    try { const { data } = await api.get('/zones'); zones.value = data.data || []; } catch { /* Global API interceptor displays the failure. */ }
    loading.value = false;
});
</script>
