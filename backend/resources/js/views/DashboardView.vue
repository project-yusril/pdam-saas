<template>
    <AppLayout page-title="Dashboard">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard label="Total Pelanggan" :value="stats.customers" icon="pi pi-users" icon-bg="bg-blue-100" icon-color="text-blue-600" :trend="5.2" />
            <StatCard label="Pendapatan Bulan Ini" :value="stats.revenue" icon="pi pi-wallet" icon-bg="bg-green-100" icon-color="text-green-600" :trend="3.8" />
            <StatCard label="Tunggakan" :value="stats.arrears" icon="pi pi-exclamation-triangle" icon-bg="bg-red-100" icon-color="text-red-600" :trend="-2.1" />
            <StatCard label="Air Terjual (m³)" :value="stats.waterSold" icon="pi pi-chart-line" icon-bg="bg-purple-100" icon-color="text-purple-600" :trend="7.4" />
        </div>
    </AppLayout>
</template>

<script setup>
import { reactive } from 'vue';
import AppLayout from '../layouts/AppLayout.vue';
import StatCard from '../components/shared/StatCard.vue';

const stats = reactive({ customers: 12450, revenue: 'Rp 845.200.000', arrears: 'Rp 128.500.000', waterSold: '48.230' });
</script>
