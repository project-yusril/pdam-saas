<template>
  <AppLayout page-title="Dashboard Keuangan">
    <div class="grid grid-cols-4 gap-4 mb-6">
      <StatCard label="Pendapatan YTD" :value="'Rp '+Number(kpi.revenueYtd).toLocaleString()" icon="pi pi-dollar" icon-bg="bg-green-100" icon-color="text-green-600" />
      <StatCard label="Tunggakan" :value="'Rp '+Number(kpi.outstanding).toLocaleString()" icon="pi pi-exclamation-triangle" icon-bg="bg-red-100" icon-color="text-red-600" />
      <StatCard label="Collection Rate" :value="kpi.collectionRate+'%'" icon="pi pi-percentage" icon-bg="bg-blue-100" icon-color="text-blue-600" />
      <StatCard label="Tagihan Overdue" :value="kpi.overdueCount" icon="pi pi-clock" icon-bg="bg-orange-100" icon-color="text-orange-600" />
    </div>
    <div class="bg-white rounded-xl border p-6">
      <h3 class="font-semibold mb-4">Aktifitas Keuangan</h3>
      <div class="grid grid-cols-2 gap-4 text-sm">
        <div><span class="text-gray-500">Piutang Usaha:</span> Rp {{ Number(kpi.ar || 0).toLocaleString() }}</div>
        <div><span class="text-gray-500">Utang Usaha:</span> Rp {{ Number(kpi.ap || 0).toLocaleString() }}</div>
        <div><span class="text-gray-500">Saldo Bank:</span> Rp {{ Number(kpi.bankBalance || 0).toLocaleString() }}</div>
        <div><span class="text-gray-500">Nilai Persediaan:</span> Rp {{ Number(kpi.inventory || 0).toLocaleString() }}</div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import StatCard from '../../components/shared/StatCard.vue';
import api from '../../api';

const kpi = ref({ revenueYtd: 0, outstanding: 0, collectionRate: 0, overdueCount: 0, ar: 0, ap: 0, bankBalance: 0, inventory: 0 });

onMounted(async () => {
  try {
    const [{ data: d }, { data: inv }] = await Promise.all([
      api.get('/dashboard/finance'),
      api.get('/inventory/balance-sheet'),
    ]);
    kpi.value = { ...kpi.value, ...d.data, inventory: inv.data?.total_inventory_value || 0 };
  } catch { /* Global API interceptor displays the failure. */ }
});
</script>
