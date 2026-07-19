<template>
  <AppLayout page-title="Tiket Pengaduan">
    <DataTable :rows="complaints" :loading="loading">
      <template #columns>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No Tiket</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subjek</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kategori</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prioritas</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">SLA</th>
      </template>
      <template #row="{ row }">
        <td class="px-4 py-3 text-sm font-mono">{{ row.ticket_number }}</td>
        <td class="px-4 py-3 font-medium">{{ row.subject }}</td>
        <td class="px-4 py-3 text-sm">{{ row.category }}</td>
        <td class="px-4 py-3">
          <span class="px-2 py-0.5 text-xs rounded-full" :class="row.priority === 'urgent' ? 'bg-red-100 text-red-700' : row.priority === 'high' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700'">{{ row.priority }}</span>
        </td>
        <td class="px-4 py-3"><span class="px-2 py-0.5 text-xs rounded-full" :class="row.status === 'open' ? 'bg-yellow-100 text-yellow-700' : row.status === 'resolved' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">{{ row.status }}</span></td>
        <td class="px-4 py-3 text-sm">{{ row.sla_due_at?.split('T')[0] || '-' }}</td>
      </template>
    </DataTable>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import DataTable from '../../components/shared/DataTable.vue';
import api from '../../api';

const complaints = ref([]);
const loading = ref(true);
onMounted(async () => {
  try { const { data } = await api.get('/complaints'); complaints.value = data.data || []; } catch { /* Global API interceptor displays the failure. */ }
  loading.value = false;
});
</script>
