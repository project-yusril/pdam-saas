<template>
    <AppLayout page-title="Jalan" page-subtitle="Master data jalan — tergabung ke rute baca meter per wilayah">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <i class="pi pi-map text-primary-600 text-lg" />
                    <h2 class="font-semibold text-vueheading">Daftar Jalan</h2>
                </div>
                <button @click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700">
                    <i class="pi pi-plus text-xs" /> Tambah Jalan
                </button>
            </div>

            <DataTable :rows="rows" :loading="loading">
                <template #columns>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Jalan</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Desa / Kelurahan</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </template>
                <template #row="{ row }">
                    <td class="px-4 py-3 font-medium text-vueheading">{{ row.name }}</td>
                    <td class="px-4 py-3 text-sm">{{ villageName(row.village_id) }}</td>
                    <td class="px-4 py-3"><span class="px-2 py-0.5 text-xs rounded-full" :class="row.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">{{ row.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="px-4 py-3 text-right"><button @click="openEdit(row)" class="text-gray-500 hover:text-primary-600 text-sm font-medium">Edit</button></td>
                </template>
            </DataTable>
        </div>

        <div class="mt-4 px-5 py-4 bg-white rounded-xl border border-gray-200 text-sm text-gray-500">
            💡 Jalan dikaitkan ke <b>rute baca meter</b> di halaman <router-link to="/meter-routes" class="text-primary-600 underline">Rute Baca Meter</router-link>.
            Setelah jalan masuk sebuah rute, jalan otomatis tersaring saat tambah pelanggan (rute→jalan).
        </div>

        <!-- Modal -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="closeCreate">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-vueheading flex items-center gap-2"><i class="pi pi-map text-primary-600" /> {{ editingId ? 'Edit Jalan' : 'Tambah Jalan' }}</h3>
                    <button class="p-2 rounded-md hover:bg-gray-100 text-gray-400" @click="closeCreate"><i class="pi pi-times" /></button>
                </div>
                <form class="p-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Desa / Kelurahan <span class="text-red-500">*</span></label>
                        <select v-model="form.village_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                            <option value="" disabled>Pilih Desa/Kelurahan</option>
                            <option v-for="v in villages" :key="v.id" :value="v.id">{{ v.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Nama Jalan <span class="text-red-500">*</span></label>
                        <input v-model="form.name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Jl. Gajah Mada" />
                    </div>
                    <div v-if="error" class="px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ error }}</div>
                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                        <button type="button" @click="closeCreate" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700" :disabled="saving">
                            <i class="pi pi-check text-xs mr-1" /> {{ saving ? 'Menyimpan…' : (editingId ? 'Simpan Perubahan' : 'Simpan Jalan') }}
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

const rows = ref([]);
const villages = ref([]);
const loading = ref(true);
const showCreate = ref(false);
const saving = ref(false);
const error = ref('');
const editingId = ref(null);

const form = reactive({ village_id: '', name: '' });

async function loadAddress() {
    try {
        const { data } = await api.get('/address/villages');
        villages.value = data.data || [];
    } catch { /* Global API interceptor */ }
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/address/streets');
        rows.value = data.data || [];
    } catch {
        rows.value = [];
    }
    loading.value = false;
}

function villageName(id) {
    const v = villages.value.find((x) => String(x.id) === String(id));
    return v?.name || '-';
}

function openCreate() {
    editingId.value = null;
    form.village_id = '';
    form.name = '';
    error.value = '';
    showCreate.value = true;
}

function openEdit(row) {
    editingId.value = row.id;
    form.village_id = row.village_id;
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
        const payload = { village_id: Number(form.village_id), name: form.name };
        if (editingId.value) {
            await api.put('/address/streets/' + editingId.value, payload);
        } else {
            await api.post('/address/streets', payload);
        }
        closeCreate();
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal menyimpan jalan.';
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    load();
    loadAddress();
});
</script>
