<template>
    <PlatformLayout page-title="Dashboard Platform" page-subtitle="Ringkasan SaaS PDAM">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-sm text-gray-500">Total PDAM</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ stats.total }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-sm text-gray-500">PDAM Aktif</p>
                <p class="text-2xl font-bold text-green-600 mt-1">{{ stats.active }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-sm text-gray-500">PDAM Nonaktif</p>
                <p class="text-2xl font-bold text-red-600 mt-1">{{ stats.inactive }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-sm text-gray-500">Total User</p>
                <p class="text-2xl font-bold text-blue-600 mt-1">{{ stats.users }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="font-semibold text-gray-800 mb-3">Modul Terpopuler</p>
                <div v-if="!popular.length" class="text-sm text-gray-400 py-4 text-center">Belum ada data</div>
                <div v-for="m in popular" :key="m.code" class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                    <span class="text-sm text-gray-700">{{ m.code }}</span>
                    <span class="text-sm font-medium text-gray-800">{{ m.count }} PDAM</span>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="font-semibold text-gray-800 mb-3">Katalog Modul per Tier</p>
                <div v-for="(count, tier) in moduleTiers" :key="tier" class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                    <span class="text-sm text-gray-700 capitalize">{{ tier }}</span>
                    <span class="text-sm font-medium text-gray-800">{{ count }} modul</span>
                </div>
            </div>
        </div>
    </PlatformLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../../api';
import PlatformLayout from '../../layouts/PlatformLayout.vue';

const stats = reactive({ total: 0, active: 0, inactive: 0, users: 0 });
const popular = ref([]);
const moduleTiers = ref({});

async function load() {
    try {
        const { data: tRes } = await api.get('/platform/tenants');
        const tenants = tRes.data ?? tRes;
        stats.total = tenants.length;
        stats.active = tenants.filter((t) => t.subscription_status === 'active').length;
        stats.inactive = stats.total - stats.active;
        stats.users = tenants.reduce((sum, t) => sum + (t.users_count ?? 0), 0);
    } catch (e) { /* interceptor 401 */ }

    try {
        const { data: mRes } = await api.get('/platform/modules');
        const modules = mRes.data ?? mRes;
        const tiers = {};
        modules.forEach((m) => { tiers[m.tier || 'lainnya'] = (tiers[m.tier || 'lainnya'] || 0) + 1; });
        moduleTiers.value = tiers;
    } catch (e) { /* ignore */ }

    // Modul terpopuler: hitung dari entitlement aktif tiap tenant
    try {
        const { data: tRes } = await api.get('/platform/tenants');
        const tenants = tRes.data ?? tRes;
        const counter = {};
        for (const t of tenants) {
            const { data: dRes } = await api.get(`/platform/tenants/${t.id}`);
            const detail = dRes.data ?? dRes;
            (detail.subscription_modules || []).forEach((sm) => {
                if (sm.status === 'active') counter[sm.module_code] = (counter[sm.module_code] || 0) + 1;
            });
        }
        popular.value = Object.entries(counter)
            .map(([code, count]) => ({ code, count }))
            .sort((a, b) => b.count - a.count)
            .slice(0, 8);
    } catch (e) { /* ignore */ }
}

onMounted(load);
</script>
