<template>
    <AppLayout page-title="Pemasangan Baru" page-subtitle="Pipeline calon pelanggan — survey berjalan hingga terpasang menjadi pelanggan">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <i class="pi pi-user-plus text-primary-600 text-lg" />
                    <h2 class="font-semibold text-vueheading">Daftar Calon Pelanggan</h2>
                </div>
                <button @click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700">
                    <i class="pi pi-plus text-xs" /> Tambah Pemasangan
                </button>
            </div>

            <DataTable :rows="prospects" :loading="loading">
                <template #columns>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kontak</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Alamat Pasang</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </template>
                <template #row="{ row }">
                    <td class="px-4 py-3 font-medium text-vueheading">{{ row.full_name }}</td>
                    <td class="px-4 py-3 text-sm">{{ row.phone || '-' }}<div v-if="row.email" class="text-xs text-gray-400">{{ row.email }}</div></td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ row.installation_address || row.address || '-' }}</td>
                    <td class="px-4 py-3"><span class="px-2 py-0.5 text-xs rounded-full" :class="statusClass(row.status)">{{ statusLabel(row.status) }}</span></td>
                    <td class="px-4 py-3 text-right">
                        <template v-if="activatable(row.status)">
                            <button @click="activate(row)" class="text-primary-600 hover:text-primary-800 text-sm font-medium">Aktivasi → Pelanggan</button>
                        </template>
                        <span v-else class="text-xs text-gray-400">menunggu survey</span>
                    </td>
                </template>
            </DataTable>
        </div>

        <div class="mt-4 px-5 py-4 bg-white rounded-xl border border-gray-200 text-sm text-gray-500">
            💡 Alur: pelanggan baru dimulai di sini sebagai <b>calon pelanggan</b> → survey → schedule → terpasang → <b>Aktivasi</b> menjadi pelanggan di <router-link to="/customers" class="text-primary-600 underline">Data Pelanggan</router-link>. Dua data ini memang berbeda domain.
        </div>

        <!-- Modal Tambah -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="closeCreate">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-vueheading flex items-center gap-2"><i class="pi pi-user-plus text-primary-600" /> Tambah Pemasangan Baru</h3>
                    <button class="p-2 rounded-md hover:bg-gray-100 text-gray-400" @click="closeCreate"><i class="pi pi-times" /></button>
                </div>
                <form class="p-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input v-model="form.full_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">NIK <span class="text-red-500">*</span></label>
                        <input v-model="form.nik" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-vuetext mb-1">Telepon</label>
                            <input v-model="form.phone" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-vuetext mb-1">Email</label>
                            <input v-model="form.email" type="email" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Alamat Pasang <span class="text-red-500">*</span></label>
                        <input v-model="form.installation_address" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div v-if="error" class="px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ error }}</div>
                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                        <button type="button" @click="closeCreate" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700" :disabled="saving"><i class="pi pi-check text-xs mr-1" /> {{ saving ? 'Menyimpan…' : 'Simpan' }}</button>
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

const prospects = ref([]);
const loading = ref(true);
const showCreate = ref(false);
const saving = ref(false);
const error = ref('');
const form = reactive({ full_name: '', nik: '', phone: '', email: '', installation_address: '' });

const STATUS_LABELS = {
    pending_review: 'Menunggu Review',
    surveying: 'Survei Berjalan',
    survey_approved: 'Survei Disetujui',
    installation_scheduled: 'Jadwal Terpasang',
    installed: 'Terpasang',
    active: 'Aktif',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
};
const STATUS_COLORS = {
    pending_review: 'bg-gray-100 text-gray-600',
    surveying: 'bg-blue-100 text-blue-700',
    survey_approved: 'bg-purple-100 text-purple-700',
    installation_scheduled: 'bg-orange-100 text-orange-700',
    installed: 'bg-green-100 text-green-700',
    active: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-600',
};

function statusLabel(s) {
    return STATUS_LABELS[s] || s;
}
function statusClass(s) {
    return STATUS_COLORS[s] || 'bg-gray-100 text-gray-600';
}
// Hanya prospek yang sudah lolos survey/siap dipasang bisa diaktivasi.
function activatable(s) {
    return ['survey_approved', 'installation_scheduled', 'installed'].includes(s);
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/prospects');
        prospects.value = data.data || [];
    } catch {
        prospects.value = [];
    }
    loading.value = false;
}

async function activate(row) {
    if (!confirm(`Yakin aktivasi "${row.full_name}" menjadi pelanggan?`)) return;
    try {
        await api.post('/prospects/' + row.id + '/activate', {});
        alert(`${row.full_name} berhasil menjadi pelanggan.`);
        await load();
    } catch (e) {
        alert(e.response?.data?.error?.message || 'Gagal aktivasi. Periksa kembali status/kelengkapan prospek.');
    }
}

function openCreate() {
    Object.assign(form, { full_name: '', nik: '', phone: '', email: '', installation_address: '' });
    error.value = '';
    showCreate.value = true;
}
function closeCreate() {
    showCreate.value = false;
    error.value = '';
}

async function submit() {
    saving.value = true;
    error.value = '';
    try {
        await api.post('/prospects', {
            full_name: form.full_name,
            nik: form.nik,
            phone: form.phone || null,
            email: form.email || null,
            installation_address: form.installation_address,
            status: 'pending_review',
        });
        closeCreate();
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal menyimpan.';
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
