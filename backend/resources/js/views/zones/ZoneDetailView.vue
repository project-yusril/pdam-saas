<template>
    <AppLayout page-title="Detail Wilayah">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div v-if="loading" class="animate-pulse space-y-3"><div class="h-6 bg-gray-200 rounded w-1/3" /><div class="h-4 bg-gray-200 rounded w-1/2" /></div>
            <div v-else-if="zone"><h2 class="text-xl font-bold">{{ zone.name }}</h2><p class="text-gray-500 mt-1">Kode: {{ zone.code }}</p></div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import AppLayout from '../../layouts/AppLayout.vue';
import api from '../../api';

const route = useRoute();
const zone = ref(null);
const loading = ref(true);

onMounted(async () => {
    try { const { data } = await api.get('/zones/' + route.params.id); zone.value = data.data; } catch { /* Global API interceptor displays the failure. */ }
    loading.value = false;
});
</script>
