<template>
  <AppLayout page-title="Dashboard Direktur">
    <div class="grid grid-cols-4 gap-4 mb-6">
      <StatCard label="Total Pelanggan" :value="kpi.customers" icon="pi pi-users" icon-bg="bg-blue-100" icon-color="text-blue-600" />
      <StatCard label="Pendapatan Bulan Ini" :value="'Rp '+Number(kpi.revenue).toLocaleString()" icon="pi pi-wallet" icon-bg="bg-green-100" icon-color="text-green-600" :trend="kpi.revenueTrend" />
      <StatCard label="Tunggakan" :value="'Rp '+Number(kpi.outstanding).toLocaleString()" icon="pi pi-exclamation-triangle" icon-bg="bg-red-100" icon-color="text-red-600" />
      <StatCard label="Collection Rate" :value="kpi.collectionRate+'%'" icon="pi pi-chart-line" icon-bg="bg-purple-100" icon-color="text-purple-600" />
    </div>
    <div class="bg-white rounded-xl border p-6 mb-6">
      <h3 class="font-semibold text-gray-800 mb-2">Peta Pelanggan — Status Tunggakan</h3>
      <LeafletMap />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import StatCard from '../../components/shared/StatCard.vue';
import LeafletMap from '../../components/shared/LeafletMap.vue';
import api from '../../api';

const kpi = ref({ customers: 0, revenue: 0, outstanding: 0, collectionRate: 0, revenueTrend: 0 });

onMounted(async () => {
  try {
    const { data: d } = await api.get('/bi/kpi-eksekutif');
    kpi.value = { ...kpi.value, ...d.data };
  } catch { /* Global API interceptor displays the failure. */ }
});
</script>
