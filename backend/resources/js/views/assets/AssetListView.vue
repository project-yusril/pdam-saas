<template>
  <AppLayout page-title="Daftar Aset" page-subtitle="Register & kelola aset tetap PDAM">
    <DataTable :rows="assets" :loading="loading">
      <template #columns>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kategori</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nilai Buku</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
      </template>
      <template #row="{ row }">
        <td class="px-4 py-3 text-sm font-mono">{{ row.asset_code }}</td>
        <td class="px-4 py-3 font-medium">{{ row.name }}</td>
        <td class="px-4 py-3 text-sm">{{ row.category?.name || '-' }}</td>
        <td class="px-4 py-3 text-sm">Rp {{ Number(row.book_value || 0).toLocaleString() }}</td>
        <td class="px-4 py-3"><span class="px-2 py-0.5 text-xs rounded-full" :class="row.status === 'aktif' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">{{ row.status }}</span></td>
      </template>
    </DataTable>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import DataTable from '../../components/shared/DataTable.vue';
import api from '../../api';

const assets = ref([]);
const loading = ref(true);
onMounted(async () => {
  try { const { data } = await api.get('/assets'); assets.value = data.data || []; } catch { /* Global API interceptor displays the failure. */ }
  loading.value = false;
});
</script>
