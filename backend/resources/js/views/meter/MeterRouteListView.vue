<template>
    <AppLayout page-title="Rute Baca Meter" page-subtitle="Kelola rute dan jalan yang tergabung per wilayah">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <i class="pi pi-route text-primary-600 text-lg" />
                    <h2 class="font-semibold text-vueheading">Daftar Rute</h2>
                </div>
                <button @click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700">
                    <i class="pi pi-plus text-xs" /> Tambah Rute
                </button>
            </div>

            <DataTable :rows="routes" :loading="loading">
                <template #columns>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Rute</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wilayah</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </template>
                <template #row="{ row }">
                    <td class="px-4 py-3 text-sm font-mono">{{ row.code }}</td>
                    <td class="px-4 py-3 font-medium text-vueheading">{{ row.name }}</td>
                    <td class="px-4 py-3 text-sm">{{ row.zone?.name || '-' }}</td>
                    <td class="px-4 py-3 text-right">
                        <router-link :to="'/meter-routes/' + row.id" class="text-primary-600 hover:text-primary-800 text-sm font-medium">Kelola Jalan</router-link>
                        <button @click="openEdit(row)" class="text-gray-500 hover:text-primary-600 text-sm font-medium ms-3">Edit</button>
                    </td>
                </template>
            </DataTable>
        </div>

        <!-- Modal -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="closeCreate">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-vueheading flex items-center gap-2"><i class="pi pi-route text-primary-600" /> {{ editingId ? 'Edit Rute' : 'Tambah Rute' }}</h3>
                    <button class="p-2 rounded-md hover:bg-gray-100 text-gray-400" @click="closeCreate"><i class="pi pi-times" /></button>
                </div>
                <form class="p-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Wilayah <span class="text-red-500">*</span></label>
                        <select v-model="form.zone_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                            <option value="" disabled>Pilih Wilayah</option>
                            <option v-for="z in zones" :key="z.id" :value="z.id">{{ z.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Kode <span class="text-red-500">*</span></label>
                        <input v-model="form.code" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="RT-01" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Nama <span class="text-red-500">*</span></label>
                        <input v-model="form.name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Rute Pontianak Kota" />
                    </div>
                    <div v-if="error" class="px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ error }}</div>
                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                        <button type="button" @click="closeCreate" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700" :disabled="saving">
                            <i class="pi pi-check text-xs mr-1" /> {{ saving ? 'Menyimpan…' : (editingId ? 'Simpan Perubahan' : 'Simpan Rute') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import DataTable from '../../components/shared/DataTable.vue';
import api from '../../api';

const routes = ref([]);
const zones = ref([]);
const loading = ref(true);
const showCreate = ref(false);
const saving = ref(false);
const error = ref('');
const editingId = ref(null);

const form = reactive({ zone_id: '', code: '', name: '' });

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/meter-routes');
        routes.value = data.data || [];
    } catch {
        routes.value = [];
    }
    loading.value = false;
}

async function loadZones() {
    try {
        const { data } = await api.get('/zones');
        zones.value = data.data || [];
    } catch { /* Global API interceptor */ }
}

function openCreate() {
    editingId.value = null;
    form.zone_id = '';
    form.code = '';
    form.name = '';
    error.value = '';
    showCreate.value = true;
}

function openEdit(row) {
    editingId.value = row.id;
    form.zone_id = row.zone_id;
    form.code = row.code;
    form.name = row.name;
    error.value = '';
    showCreate.value = true;
}

function closeCreate() {
    showCreate.value = false;
    error.value = '';
    editingId.value = null;
}

async function submit() {
    saving.value = true;
    error.value = '';
    try {
        const payload = {
            zone_id: form.zone_id ? Number(form.zone_id) : null,
            code: form.code,
            name: form.name,
            is_active: true,
        };
        if (editingId.value) {
            await api.put('/meter-routes/' + editingId.value, payload);
        } else {
            await api.post('/meter-routes', payload);
        }
        closeCreate();
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal menyimpan rute.';
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    load();
    loadZones();
});
</script>
