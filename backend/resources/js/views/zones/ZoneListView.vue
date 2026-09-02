<template>
    <AppLayout page-title="Manajemen Wilayah" page-subtitle="Kelola cabang dan wilayah layanan PDAM">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <i class="pi pi-map-marker text-primary-600 text-lg" />
                    <h2 class="font-semibold text-vueheading">Daftar Wilayah</h2>
                </div>
                <button @click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700">
                    <i class="pi pi-plus text-xs" /> Tambah Wilayah
                </button>
            </div>

            <DataTable :rows="zones" :loading="loading">
                <template #columns>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </template>
                <template #row="{ row }">
                    <td class="px-4 py-3 text-sm font-mono">{{ row.code }}</td>
                    <td class="px-4 py-3 font-medium text-vueheading">{{ row.name }}</td>
                    <td class="px-4 py-3"><span class="inline-block px-2 py-0.5 text-xs rounded-full" :class="row.is_main ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-600'">{{ row.is_main ? 'Utama' : 'Cabang' }}</span></td>
                    <td class="px-4 py-3 text-right">
                        <router-link :to="'/zones/' + row.id" class="text-primary-600 hover:text-primary-800 text-sm font-medium">Detail</router-link>
                        <button @click="openEdit(row)" class="text-gray-500 hover:text-primary-600 text-sm font-medium ms-3">Edit</button>
                    </td>
                </template>
            </DataTable>
        </div>

        <!-- Modal -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="closeCreate">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-vueheading flex items-center gap-2"><i class="pi pi-map-marker text-primary-600" /> {{ editingId ? 'Edit Wilayah' : 'Tambah Wilayah' }}</h3>
                    <button class="p-2 rounded-md hover:bg-gray-100 text-gray-400" @click="closeCreate"><i class="pi pi-times" /></button>
                </div>
                <form class="p-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Kode <span class="text-red-500">*</span></label>
                        <input v-model="form.code" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="ZN-01" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Nama <span class="text-red-500">*</span></label>
                        <input v-model="form.name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Pontianak Kota" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-vuetext">
                        <input v-model="form.is_main" type="checkbox" class="rounded border-gray-300" /> Jadikan wilayah utama
                    </label>
                    <div v-if="error" class="px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ error }}</div>
                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                        <button type="button" @click="closeCreate" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700" :disabled="saving">
                            <i class="pi pi-check text-xs mr-1" /> {{ saving ? 'Menyimpan…' : (editingId ? 'Simpan Perubahan' : 'Simpan Wilayah') }}
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

const zones = ref([]);
const loading = ref(true);
const showCreate = ref(false);
const saving = ref(false);
const error = ref('');
const editingId = ref(null);

const form = reactive({ code: '', name: '', is_main: false });

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/zones');
        zones.value = data.data || [];
    } catch {
        zones.value = [];
    }
    loading.value = false;
}

function openCreate() {
    editingId.value = null;
    form.code = '';
    form.name = '';
    form.is_main = false;
    error.value = '';
    showCreate.value = true;
}

function openEdit(row) {
    editingId.value = row.id;
    form.code = row.code;
    form.name = row.name;
    form.is_main = !!row.is_main;
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
        const payload = { code: form.code, name: form.name, is_main: form.is_main };
        if (editingId.value) {
            await api.put('/zones/' + editingId.value, payload);
        } else {
            await api.post('/zones', payload);
        }
        closeCreate();
        await load();
    } catch (e) {
        error.value = e.response?.data?.error?.message || e.response?.data?.message || 'Gagal menyimpan wilayah.';
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
