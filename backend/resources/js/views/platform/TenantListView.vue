<template>
    <PlatformLayout page-title="Kelola Tenant PDAM" page-subtitle="Daftar, provisioning, & status langganan PDAM">
        <div class="flex justify-end mb-4">
            <button @click="showForm = !showForm" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                <i class="pi pi-plus mr-1" /> Tambah PDAM
            </button>
        </div>

        <div v-if="showForm" class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
            <p class="font-semibold text-gray-800 mb-4">Provisioning PDAM Baru</p>
            <form @submit.prevent="submitTenant" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input v-model="form.code" required placeholder="Kode (mis. pdam-medan)" class="px-3 py-2 border rounded-lg text-sm" />
                <input v-model="form.name" required placeholder="Nama PDAM" class="px-3 py-2 border rounded-lg text-sm" />
                <input v-model="form.city" placeholder="Kota" class="px-3 py-2 border rounded-lg text-sm" />
                <input v-model="form.province" placeholder="Provinsi" class="px-3 py-2 border rounded-lg text-sm" />
                <input v-model="form.admin_name" required placeholder="Nama Admin" class="px-3 py-2 border rounded-lg text-sm" />
                <input v-model="form.admin_email" required type="email" placeholder="Email Admin" class="px-3 py-2 border rounded-lg text-sm" />
                <input v-model="form.admin_password" required type="password" placeholder="Password Admin (min 8)" class="px-3 py-2 border rounded-lg text-sm" />
                <input v-model="form.admin_phone" placeholder="Telepon Admin" class="px-3 py-2 border rounded-lg text-sm" />
                <div v-if="formError" class="md:col-span-2 text-sm text-red-600">{{ formError }}</div>
                <div class="md:col-span-2 flex gap-2">
                    <button type="submit" :disabled="saving" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
                    <button type="button" @click="showForm = false" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm">Batal</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Kode</th>
                        <th class="px-4 py-3 font-medium">Nama</th>
                        <th class="px-4 py-3 font-medium">Kota</th>
                        <th class="px-4 py-3 font-medium">User</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="loading"><td colspan="6" class="px-4 py-6 text-center text-gray-400">Memuat...</td></tr>
                    <tr v-else-if="!tenants.length"><td colspan="6" class="px-4 py-6 text-center text-gray-400">Belum ada tenant</td></tr>
                    <tr v-for="t in tenants" :key="t.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-gray-700">{{ t.code }}</td>
                        <td class="px-4 py-3">
                            <router-link :to="`/platform/tenants/${t.id}`" class="text-blue-600 hover:underline font-medium">{{ t.name }}</router-link>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ t.city || '-' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ t.users_count ?? 0 }}</td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-0.5 rounded-full text-xs', isActive(t) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600']">
                                {{ isActive(t) ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <router-link :to="`/platform/tenants/${t.id}`" class="text-gray-400 hover:text-blue-600" title="Detail">
                                    <i class="pi pi-eye" />
                                </router-link>
                                <button type="button" @click="toggleStatus(t)" :disabled="t._busy"
                                    :class="['relative inline-flex h-6 w-11 items-center rounded-full transition disabled:opacity-50', isActive(t) ? 'bg-green-500' : 'bg-gray-300']"
                                    :title="isActive(t) ? 'Klik untuk menonaktifkan' : 'Klik untuk mengaktifkan'">
                                    <span :class="['inline-block h-4 w-4 transform rounded-full bg-white transition', isActive(t) ? 'translate-x-6' : 'translate-x-1']" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PlatformLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../../api';
import PlatformLayout from '../../layouts/PlatformLayout.vue';

const tenants = ref([]);
const loading = ref(true);
const showForm = ref(false);
const saving = ref(false);
const formError = ref('');
const form = reactive({ code: '', name: '', city: '', province: '', admin_name: '', admin_email: '', admin_password: '', admin_phone: '' });

function isActive(t) {
    return t.subscription_status === 'active';
}

async function loadTenants() {
    loading.value = true;
    try {
        const { data } = await api.get('/platform/tenants');
        tenants.value = (data.data ?? data).map((t) => ({ ...t, _busy: false }));
    } catch (e) { /* interceptor 401 */ } finally {
        loading.value = false;
    }
}

async function toggleStatus(t) {
    const nextActive = !isActive(t);
    if (!nextActive && !confirm(`Nonaktifkan ${t.name}? Semua user PDAM ini tidak akan bisa login.`)) return;
    t._busy = true;
    try {
        const { data } = await api.post(`/platform/tenants/${t.id}/toggle-status`, { is_active: nextActive });
        t.subscription_status = (data.data ?? data).subscription_status;
    } catch (e) {
        alert(e.response?.data?.message || 'Gagal mengubah status');
    } finally {
        t._busy = false;
    }
}

async function submitTenant() {
    saving.value = true;
    formError.value = '';
    try {
        await api.post('/platform/tenants', { ...form });
        showForm.value = false;
        Object.keys(form).forEach((k) => (form[k] = ''));
        await loadTenants();
    } catch (e) {
        const errs = e.response?.data?.errors;
        formError.value = errs ? Object.values(errs).flat().join(' ') : (e.response?.data?.message || 'Gagal menyimpan');
    } finally {
        saving.value = false;
    }
}

onMounted(loadTenants);
</script>
