<template>
  <AppLayout page-title="Absensi & Cuti" page-subtitle="Riwayat kehadiran dan pengajuan cuti">
    <div class="grid grid-cols-3 gap-4 mb-6">
      <StatisticalCard label="Hadir Bulan Ini" :value="stats.hadir" icon="pi pi-check-circle" bg="bg-green-100" color="text-green-600" />
      <StatisticalCard label="Sisa Cuti Tahunan" :value="stats.sisaCuti" icon="pi pi-calendar" bg="bg-blue-100" color="text-blue-600" />
      <StatisticalCard label="Lembur Bulan Ini" :value="stats.lembur + ' jam'" icon="pi pi-clock" bg="bg-orange-100" color="text-orange-600" />
    </div>
    <DataTable :rows="attendances" :loading="loading">
      <template #columns>
        <th>Tanggal</th><th>Check In</th><th>Check Out</th><th>Status</th><th>GPS</th>
      </template>
      <template #row="{ row }">
        <td>{{ row.date }}</td>
        <td>{{ row.check_in || '-' }}</td>
        <td>{{ row.check_out || '-' }}</td>
        <td><span :class="'px-2 py-0.5 text-xs rounded-full ' + (row.status==='present'?'bg-green-100 text-green-700':'bg-yellow-100 text-yellow-700')">{{ row.status }}</span></td>
        <td>{{ row.check_in_lat ? row.check_in_lat.toFixed(4) + ', ' + row.check_in_lng.toFixed(4) : '-' }}</td>
      </template>
    </DataTable>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import AppLayout from '../../layouts/AppLayout.vue'
import DataTable from '../../components/shared/DataTable.vue'
import api from '../../api'
import StatisticalCard from '../../components/shared/StatCard.vue'

const attendances = ref([])
const loading = ref(true)
const stats = reactive({ hadir: 0, sisaCuti: 12, lembur: 0 })

onMounted(async () => {
  try {
    const { data: att } = await api.get('/hr/attendances', { params: { per_page: 30, date_from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0] } })
    attendances.value = att.data || []
    stats.hadir = attendances.value.filter(r => r.status === 'present').length
  } catch { /* Global API interceptor displays the failure. */ }
  loading.value = false
})
</script>
