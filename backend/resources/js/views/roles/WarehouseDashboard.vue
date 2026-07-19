<template>
  <AppLayout page-title="Dashboard Warehouse">
    <div class="grid grid-cols-4 gap-4 mb-6">
      <StatCard label="Total Item" :value="kpi.totalItems" icon="pi pi-box" icon-bg="bg-blue-100" icon-color="text-blue-600" />
      <StatCard label="Stok Menipis" :value="kpi.lowStock" icon="pi pi-exclamation-circle" icon-bg="bg-yellow-100" icon-color="text-yellow-600" />
      <StatCard label="Stok Habis" :value="kpi.emptyStock" icon="pi pi-times-circle" icon-bg="bg-red-100" icon-color="text-red-600" />
      <StatCard label="Nilai Persediaan" :value="'Rp '+Number(kpi.inventoryValue).toLocaleString()" icon="pi pi-dollar" icon-bg="bg-green-100" icon-color="text-green-600" />
    </div>
    <div class="grid grid-cols-2 gap-6">
      <div class="bg-white rounded-xl border p-6">
        <h3 class="font-semibold mb-4">Stok Menipis</h3>
        <div v-if="kpi.lowStockItems.length" class="space-y-2">
          <div v-for="item in kpi.lowStockItems" :key="item.id" class="flex justify-between text-sm border-b border-gray-100 py-2">
            <span>{{ item.material_name }} <span class="text-gray-400">({{ item.warehouse }})</span></span>
            <span class="font-mono text-red-600">{{ item.quantity }} {{ item.unit }}</span>
          </div>
        </div>
        <div v-else class="text-sm text-gray-400 text-center py-4">Semua stok aman.</div>
      </div>
      <div class="bg-white rounded-xl border p-6">
        <h3 class="font-semibold mb-4">Transfer Terbaru</h3>
        <div class="text-sm text-gray-500 text-center py-8">Lihat modul Warehouse untuk detail transfer & PO</div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import StatCard from '../../components/shared/StatCard.vue';
import api from '../../api';

const kpi = ref({ totalItems: 0, lowStock: 0, emptyStock: 0, inventoryValue: 0, lowStockItems: [] });

onMounted(async () => {
  try {
    const [{ data: wh }, { data: inv }] = await Promise.all([
      api.get('/warehouse/dashboard'),
      api.get('/inventory/balance-sheet'),
    ]);
    kpi.value = {
      totalItems: wh.data?.total_items || 0,
      lowStock: wh.data?.low_stock_count || 0,
      emptyStock: wh.data?.empty_stock_count || 0,
      inventoryValue: inv.data?.total_inventory_value || 0,
      lowStockItems: wh.data?.low_stock || [],
    };
  } catch { /* Global API interceptor displays the failure. */ }
});
</script>
