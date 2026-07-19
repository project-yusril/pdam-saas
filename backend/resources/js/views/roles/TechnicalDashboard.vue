<template>
  <AppLayout page-title="Dashboard Teknik">
    <div class="grid grid-cols-4 gap-4 mb-6">
      <StatCard label="Pelanggan Aktif" :value="kpi.customers" icon="pi pi-users" icon-bg="bg-blue-100" icon-color="text-blue-600" />
      <StatCard label="WO Open" :value="kpi.openWo" icon="pi pi-wrench" icon-bg="bg-yellow-100" icon-color="text-yellow-600" />
      <StatCard label="Anomali Meter" :value="kpi.anomalies" icon="pi pi-exclamation-triangle" icon-bg="bg-red-100" icon-color="text-red-600" />
      <StatCard label="Asset Aktif" :value="kpi.assets" icon="pi pi-building" icon-bg="bg-green-100" icon-color="text-green-600" />
    </div>
    <div class="grid grid-cols-2 gap-6">
      <div class="bg-white rounded-xl border p-6">
        <h3 class="font-semibold mb-3">Rekomendasi Prioritas Ganti Meter</h3>
        <div class="text-sm text-gray-500 text-center py-8">Data tersedia di dashboard METX</div>
      </div>
      <div class="bg-white rounded-xl border p-6">
        <h3 class="font-semibold mb-3">Jadwal Perawatan</h3>
        <div class="text-sm text-gray-500 text-center py-8">Lihat modul Maintenance</div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import StatCard from '../../components/shared/StatCard.vue';
import api from '../../api';

const kpi = ref({ customers: 0, openWo: 0, anomalies: 0, assets: 0 });

onMounted(async () => {
  try {
    const [{ data: ops }, { data: metx }] = await Promise.all([
      api.get('/dashboard/operations'),
      api.get('/dashboard/metx'),
    ]);
    kpi.value.customers = ops.data?.active_customers || 0;
    kpi.value.openWo = ops.data?.open_work_orders || 0;
    kpi.value.anomalies = metx.data?.anomalies_confirmed || 0;
  } catch { /* Global API interceptor displays the failure. */ }
});
</script>
