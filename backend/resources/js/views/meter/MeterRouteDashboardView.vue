<template>
    <AppLayout page-title="Dashboard Rute">
        <div v-for="r in routes" :key="r.route_id" class="bg-white rounded-xl border border-gray-200 p-6 mb-4">
            <div class="flex justify-between mb-2"><div class="font-semibold">{{ r.route_name }}</div><span class="text-sm text-gray-600">{{ r.read }} / {{ r.total }}</span></div>
            <div class="w-full h-2 bg-gray-100 rounded-full"><div class="h-full bg-blue-500 rounded-full" :style="{ width: (r.percent || 0) + '%' }" /></div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import api from '../../api';

const routes = ref([]);

onMounted(async () => {
    try { const { data } = await api.get('/meter-readings/route-progress'); routes.value = data.data || []; } catch { /* Global API interceptor displays the failure. */ }
});
</script>
