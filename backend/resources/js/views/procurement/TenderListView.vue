<template>
    <AppLayout page-title="Pengadaan & Tender">
        <DataTable :rows="tenders" :loading="loading">
            <template #columns>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No Tender</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Judul</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Budget</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deadline</th>
            </template>
            <template #row="{ row }">
                <td class="px-4 py-3 text-sm font-mono">{{ row.tender_number }}</td>
                <td class="px-4 py-3 font-medium">{{ row.title }}</td>
                <td class="px-4 py-3 text-sm">Rp {{ Number(row.budget_ceiling).toLocaleString() }}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-0.5 text-xs rounded-full" :class="row.status === 'awarded' ? 'bg-green-100 text-green-700' : row.status === 'published' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'">{{ row.status }}</span>
                </td>
                <td class="px-4 py-3 text-sm">{{ row.submission_deadline }}</td>
            </template>
        </DataTable>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import DataTable from '../../components/shared/DataTable.vue';
import api from '../../api';

const tenders = ref([]);
const loading = ref(true);

onMounted(async () => {
    try { const { data } = await api.get('/tenders'); tenders.value = data.data || []; } catch { /* Global API interceptor displays the failure. */ }
    loading.value = false;
});
</script>
