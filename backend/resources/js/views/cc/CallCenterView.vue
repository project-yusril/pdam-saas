<template>
  <AppLayout page-title="Log Panggilan">
    <DataTable :rows="calls" :loading="loading">
      <template #columns>
        <th>Waktu</th><th>Nomor</th><th>Arah</th><th>Durasi</th><th>Disposisi</th><th>Agent</th>
      </template>
      <template #row="{ row }">
        <td>{{ new Date(row.start_time).toLocaleString() }}</td>
        <td>{{ row.caller_number }}</td>
        <td><span :class="'px-2 py-0.5 text-xs rounded-full '+(row.direction==='inbound'?'bg-blue-100 text-blue-700':'bg-purple-100 text-purple-700')">{{ row.direction === 'inbound' ? 'Masuk' : 'Keluar' }}</span></td>
        <td>{{ row.duration_seconds }}dtk</td>
        <td>{{ row.disposition || '-' }}</td>
        <td>{{ row.agent?.name }}</td>
      </template>
    </DataTable>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '../../layouts/AppLayout.vue'
import DataTable from '../../components/shared/DataTable.vue'
import api from '../../api'

const calls = ref([])
const loading = ref(true)

onMounted(async () => {
  try { const { data } = await api.get('/call-logs'); calls.value = data.data || [] } catch { /* Global API interceptor displays the failure. */ }
  loading.value = false
})
</script>
