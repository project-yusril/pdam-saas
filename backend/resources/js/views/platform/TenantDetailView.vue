<template>
    <PlatformLayout :page-title="tenant?.name || 'Detail Tenant'" page-subtitle="Modul, langganan, & status PDAM">
        <router-link to="/platform/tenants" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline mb-4">
            <i class="pi pi-arrow-left" /> Kembali ke daftar
        </router-link>

        <div v-if="loading" class="text-gray-400 py-8 text-center">Memuat...</div>

        <template v-else-if="tenant">
            <!-- Info ringkas -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-sm text-gray-500">Kode PDAM</p>
                    <p class="text-lg font-bold text-gray-800 mt-1 font-mono">{{ tenant.code }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-sm text-gray-500">Kota / Provinsi</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">{{ tenant.city || '-' }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-sm text-gray-500">Total User</p>
                    <p class="text-lg font-bold text-blue-600 mt-1">{{ tenant.users_count ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-sm text-gray-500">Status</p>
                    <p class="text-lg font-bold mt-1" :class="tenant.subscription_status === 'active' ? 'text-green-600' : 'text-red-600'">
                        {{ tenant.subscription_status === 'active' ? 'Aktif' : 'Nonaktif' }}
                    </p>
                </div>
            </div>

            <!-- Modul entitlement -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <p class="font-semibold text-gray-800">Modul PDAM Ini</p>
                    <span class="text-sm text-gray-500">{{ activeCount }} aktif / {{ modules.length }} total</span>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Kode Modul</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Kedaluwarsa</th>
                            <th class="px-4 py-3 font-medium text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!modules.length"><td colspan="4" class="px-4 py-6 text-center text-gray-400">Belum ada entitlement modul</td></tr>
                        <tr v-for="m in modules" :key="m.module_code" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-gray-700">{{ m.module_code }}</td>
                            <td class="px-4 py-3">
                                <span :class="['px-2 py-0.5 rounded-full text-xs', statusClass(m.status)]">{{ statusLabel(m.status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ m.expires_at ? m.expires_at.substring(0, 10) : '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button v-if="m.status !== 'active'" @click="activate(m)" :disabled="m._busy"
                                    class="px-3 py-1 bg-green-600 text-white rounded-md text-xs font-medium hover:bg-green-700 disabled:opacity-50">
                                    Aktifkan
                                </button>
                                <button v-else @click="lock(m)" :disabled="m._busy"
                                    class="px-3 py-1 bg-red-100 text-red-600 rounded-md text-xs font-medium hover:bg-red-200 disabled:opacity-50">
                                    Kunci
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </PlatformLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api';
import PlatformLayout from '../../layouts/PlatformLayout.vue';

const route = useRoute();
const tenant = ref(null);
const modules = ref([]);
const loading = ref(true);

const activeCount = computed(() => modules.value.filter((m) => m.status === 'active').length);

function statusLabel(s) {
    return { active: 'Aktif', locked: 'Terkunci', pending: 'Menunggu' }[s] || s;
}
function statusClass(s) {
    return {
        active: 'bg-green-100 text-green-700',
        locked: 'bg-red-100 text-red-600',
        pending: 'bg-yellow-100 text-yellow-700',
    }[s] || 'bg-gray-100 text-gray-500';
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get(`/platform/tenants/${route.params.id}`);
        const detail = data.data ?? data;
        tenant.value = detail;
        modules.value = (detail.subscription_modules || []).map((m) => ({ ...m, _busy: false }));
    } catch (e) { /* interceptor 401 */ } finally {
        loading.value = false;
    }
}

async function activate(m) {
    m._busy = true;
    try {
        await api.post(`/platform/tenants/${route.params.id}/modules/${m.module_code}/activate`, {});
        m.status = 'active';
    } catch (e) {
        alert(e.response?.data?.message || 'Gagal mengaktifkan modul');
    } finally {
        m._busy = false;
    }
}

async function lock(m) {
    if (!confirm(`Kunci modul ${m.module_code} untuk PDAM ini?`)) return;
    m._busy = true;
    try {
        await api.post(`/platform/tenants/${route.params.id}/modules/${m.module_code}/lock`, {});
        m.status = 'locked';
    } catch (e) {
        alert(e.response?.data?.message || 'Gagal mengunci modul');
    } finally {
        m._busy = false;
    }
}

onMounted(load);
</script>
