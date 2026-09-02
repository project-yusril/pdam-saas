<template>
    <AppLayout page-title="Master Alamat" page-subtitle="Kelola Provinsi → Kota/Kab → Kecamatan → Desa/Kelurahan (data global, dipakai semua PDAM)">
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-3 mb-4 flex items-center justify-between">
            <div class="text-sm text-vuetext">💡 Hapus = lembut (soft-delete). Data global tidak bisa dihapus tenant.</div>
            <label class="flex items-center gap-2 text-sm text-vuetext cursor-pointer">
                <input v-model="showDeleted" type="checkbox" @change="reloadAll" class="rounded border-gray-300" />
                Tampilkan data terhapus
            </label>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <!-- Provinsi -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-vueheading flex items-center gap-2"><i class="pi pi-globe text-primary-600 text-lg" /> Provinsi</h2>
                    <button @click="openModal('province')" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700"><i class="pi pi-plus text-xs" /> Tambah</button>
                </div>
                <DataTable :rows="provinces" :loading="loadingP">
                    <template #columns>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                    </template>
                    <template #row="{ row }">
                        <td class="px-4 py-3 text-sm font-mono">{{ row.code }}</td>
                        <td class="px-4 py-3">
                            <button class="font-medium text-vueheading hover:text-primary-600 text-left" @click="selectProvince(row)">{{ row.name }}</button>
                            <div class="text-xs text-gray-400">{{ row.cities?.count ?? row.cities_count ?? '' }}</div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <template v-if="!trashed(row)">
                                <button @click="openModal('province', row)" class="text-gray-500 hover:text-primary-600 text-sm font-medium">Edit</button>
                                <button @click="remove('province', row)" class="text-gray-500 hover:text-red-600 text-sm font-medium ms-3">Hapus</button>
                            </template>
                            <template v-else>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 mr-2">terhapus</span>
                                <button @click="restoreRow('province', row)" class="text-primary-600 hover:text-primary-800 text-sm font-medium">Pulihkan</button>
                            </template>
                        </td>
                    </template>
                </DataTable>
            </div>

            <!-- Kota/Kab -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-vueheading flex items-center gap-2"><i class="pi pi-building text-primary-600 text-lg" /> Kota / Kabupaten</h2>
                    <button @click="openModal('city')" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700" :disabled="!selProvince"><i class="pi pi-plus text-xs" /> Tambah</button>
                </div>
                <div class="px-5 py-3 border-b border-gray-100 text-sm">
                    <select v-model="selProvinceId" @change="onProvinceChange" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                        <option value="">— Pilih Provinsi —</option>
                        <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
                <DataTable :rows="cities" :loading="loadingC">
                    <template #columns>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                    </template>
                    <template #row="{ row }">
                        <td class="px-4 py-3 text-sm font-mono">{{ row.code }}</td>
                        <td class="px-4 py-3">
                            <button class="font-medium text-vueheading hover:text-primary-600 text-left" @click="selectCity(row)">{{ row.name }}</button>
                        </td>
                        <td class="px-4 py-3 text-sm capitalize">{{ row.type }}</td>
                        <td class="px-4 py-3 text-right">
                            <template v-if="!trashed(row)">
                                <button @click="openModal('city', row)" class="text-gray-500 hover:text-primary-600 text-sm font-medium">Edit</button>
                                <button @click="remove('city', row)" class="text-gray-500 hover:text-red-600 text-sm font-medium ms-3">Hapus</button>
                            </template>
                            <template v-else>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 mr-2">terhapus</span>
                                <button @click="restoreRow('city', row)" class="text-primary-600 hover:text-primary-800 text-sm font-medium">Pulihkan</button>
                            </template>
                        </td>
                    </template>
                </DataTable>
            </div>

            <!-- Kecamatan -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-vueheading flex items-center gap-2"><i class="pi pi-map text-primary-600 text-lg" /> Kecamatan</h2>
                    <button @click="openModal('district')" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700" :disabled="!selCity"><i class="pi pi-plus text-xs" /> Tambah</button>
                </div>
                <div class="px-5 py-3 border-b border-gray-100 text-sm">
                    <select v-model="selCityId" @change="onCityChange" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                        <option value="">— Pilih Kota/Kabupaten —</option>
                        <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <DataTable :rows="districts" :loading="loadingD">
                    <template #columns>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                    </template>
                    <template #row="{ row }">
                        <td class="px-4 py-3 text-sm font-mono">{{ row.code }}</td>
                        <td class="px-4 py-3">
                            <button class="font-medium text-vueheading hover:text-primary-600 text-left" @click="selectDistrict(row)">{{ row.name }}</button>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <template v-if="!trashed(row)">
                                <button @click="openModal('district', row)" class="text-gray-500 hover:text-primary-600 text-sm font-medium">Edit</button>
                                <button @click="remove('district', row)" class="text-gray-500 hover:text-red-600 text-sm font-medium ms-3">Hapus</button>
                            </template>
                            <template v-else>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 mr-2">terhapus</span>
                                <button @click="restoreRow('district', row)" class="text-primary-600 hover:text-primary-800 text-sm font-medium">Pulihkan</button>
                            </template>
                        </td>
                    </template>
                </DataTable>
            </div>

            <!-- Desa/Kelurahan (fokus) -->
            <div class="bg-white rounded-xl border-2 border-primary-200 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-primary-100 bg-primary-50/40">
                    <h2 class="font-semibold text-vueheading flex items-center gap-2"><i class="pi pi-briefcase text-primary-600 text-lg" /> Desa / Kelurahan</h2>
                    <button @click="openModal('village')" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700" :disabled="!selDistrict"><i class="pi pi-plus text-xs" /> Tambah</button>
                </div>
                <div class="px-5 py-3 border-b border-gray-100 text-sm">
                    <select v-model="selDistrictId" @change="onDistrictChange" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                        <option value="">— Pilih Kecamatan —</option>
                        <option v-for="d in districts" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                </div>
                <DataTable :rows="villages" :loading="loadingV">
                    <template #columns>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Desa / Kelurahan</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode Pos</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                    </template>
                    <template #row="{ row }">
                        <td class="px-4 py-3 text-sm font-mono">{{ row.code }}</td>
                        <td class="px-4 py-3 font-medium text-vueheading">{{ row.name }}</td>
                        <td class="px-4 py-3 text-sm">{{ row.postal_code || '-' }}</td>
                        <td class="px-4 py-3 text-right">
                            <template v-if="!trashed(row)">
                                <button @click="openModal('village', row)" class="text-gray-500 hover:text-primary-600 text-sm font-medium">Edit</button>
                                <button @click="remove('village', row)" class="text-gray-500 hover:text-red-600 text-sm font-medium ms-3">Hapus</button>
                            </template>
                            <template v-else>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 mr-2">terhapus</span>
                                <button @click="restoreRow('village', row)" class="text-primary-600 hover:text-primary-800 text-sm font-medium">Pulihkan</button>
                            </template>
                        </td>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="closeModal">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-vueheading flex items-center gap-2"><i class="pi pi-pencil text-primary-600" /> {{ editId ? `Edit ${levelLabel}` : `Tambah ${levelLabel}` }}</h3>
                    <button class="p-2 rounded-md hover:bg-gray-100 text-gray-400" @click="closeModal"><i class="pi pi-times" /></button>
                </div>
                <form class="p-6 space-y-4" @submit.prevent="submit">
                    <div v-if="level === 'province'">
                        <label class="block text-sm font-medium text-vuetext mb-1">Kode <span class="text-red-500">*</span></label>
                        <input v-model="form.code" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        <label class="block text-sm font-medium text-vuetext mb-1 mt-3">Nama <span class="text-red-500">*</span></label>
                        <input v-model="form.name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div v-else-if="level === 'city'">
                        <label class="block text-sm font-medium text-vuetext mb-1">Provinsi <span class="text-red-500">*</span></label>
                        <select v-model="form.province_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                            <option value="" disabled>Pilih Provinsi</option>
                            <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <label class="block text-sm font-medium text-vuetext mb-1 mt-3">Kode <span class="text-red-500">*</span></label>
                        <input v-model="form.code" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        <label class="block text-sm font-medium text-vuetext mb-1 mt-3">Nama <span class="text-red-500">*</span></label>
                        <input v-model="form.name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        <label class="block text-sm font-medium text-vuetext mb-1 mt-3">Tipe</label>
                        <select v-model="form.type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                            <option value="kota">Kota</option>
                            <option value="kabupaten">Kabupaten</option>
                        </select>
                    </div>
                    <div v-else-if="level === 'district'">
                        <label class="block text-sm font-medium text-vuetext mb-1">Kota/Kabupaten <span class="text-red-500">*</span></label>
                        <select v-model="form.city_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                            <option value="" disabled>Pilih Kota/Kab</option>
                            <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <label class="block text-sm font-medium text-vuetext mb-1 mt-3">Kode <span class="text-red-500">*</span></label>
                        <input v-model="form.code" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        <label class="block text-sm font-medium text-vuetext mb-1 mt-3">Nama <span class="text-red-500">*</span></label>
                        <input v-model="form.name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div v-else-if="level === 'village'">
                        <label class="block text-sm font-medium text-vuetext mb-1">Kecamatan <span class="text-red-500">*</span></label>
                        <select v-model="form.district_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                            <option value="" disabled>Pilih Kecamatan</option>
                            <option v-for="d in districts" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                        <label class="block text-sm font-medium text-vuetext mb-1 mt-3">Kode <span class="text-red-500">*</span></label>
                        <input v-model="form.code" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        <label class="block text-sm font-medium text-vuetext mb-1 mt-3">Nama (Desa/Kelurahan) <span class="text-red-500">*</span></label>
                        <input v-model="form.name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        <label class="block text-sm font-medium text-vuetext mb-1 mt-3">Kode Pos</label>
                        <input v-model="form.postal_code" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div v-if="error" class="px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ error }}</div>
                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                        <button type="button" @click="closeModal" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700" :disabled="saving"><i class="pi pi-check text-xs mr-1" /> {{ saving ? 'Menyimpan…' : 'Simpan' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import DataTable from '../../components/shared/DataTable.vue';
import api from '../../api';

const provinces = ref([]);
const cities = ref([]);
const districts = ref([]);
const villages = ref([]);
const loadingP = ref(true);
const loadingC = ref(false);
const loadingD = ref(false);
const loadingV = ref(false);

const selProvinceId = ref('');
const selCityId = ref('');
const selDistrictId = ref('');

const selProvince = computed(() => provinces.value.find((x) => String(x.id) === String(selProvinceId.value)));
const selCity = computed(() => cities.value.find((x) => String(x.id) === String(selCityId.value)));
const selDistrict = computed(() => districts.value.find((x) => String(x.id) === String(selDistrictId.value)));

const showModal = ref(false);
const saving = ref(false);
const error = ref('');
const level = ref('province');
const editId = ref(null);
const showDeleted = ref(false);
const form = reactive({ code: '', name: '', type: 'kabupaten', province_id: '', city_id: '', district_id: '', postal_code: '' });

const LEVEL_LABELS = { province: 'Provinsi', city: 'Kota/Kabupaten', district: 'Kecamatan', village: 'Desa/Kelurahan' };
const levelLabel = computed(() => LEVEL_LABELS[level.value]);
const levelLabelFor = (lvl) => LEVEL_LABELS[lvl];

const get = async (url, setter, loadingSetter) => {
    loadingSetter.value = true;
    try {
        const params = new URLSearchParams();
        if (showDeleted.value) params.set('with_trashed', '1');
        const sep = url.includes('?') ? '&' : '?';
        const qs = params.toString();
        const { data } = await api.get(url + (qs ? sep + qs : ''));
        setter.value = data.data || [];
    } catch {
        setter.value = [];
    }
    loadingSetter.value = false;
};

function trashed(row) {
    return !!row.deleted_at;
}

async function loadProvinces() {
    await get('/address/provinces', provinces, loadingP);
}

async function reloadAll() {
    await loadProvinces();
    selProvinceId.value = '';
    selCityId.value = '';
    selDistrictId.value = '';
    cities.value = [];
    districts.value = [];
    villages.value = [];
}

async function onProvinceChange() {
    if (!selProvinceId.value) { cities.value = []; selCityId.value = ''; return; }
    selCityId.value = '';
    await get('/address/cities?province_id=' + selProvinceId.value, cities, loadingC);
}

async function onCityChange() {
    if (!selCityId.value) { districts.value = []; selDistrictId.value = ''; return; }
    selDistrictId.value = '';
    await get('/address/districts?city_id=' + selCityId.value, districts, loadingD);
}

async function onDistrictChange() {
    if (!selDistrictId.value) { villages.value = []; return; }
    await get('/address/villages?district_id=' + selDistrictId.value, villages, loadingV);
}

// Drill helpers untuk mengaktifkan level di bawah dari kartu atas
async function selectProvince(row) {
    selProvinceId.value = row.id;
    await onProvinceChange();
    const first = cities.value[0];
    if (first) { selCityId.value = first.id; await onCityChange(); }
    const d = districts.value[0];
    if (d) { selDistrictId.value = d.id; await onDistrictChange(); }
}

async function selectCity(row) {
    selCityId.value = row.id;
    await onCityChange();
    const d = districts.value[0];
    if (d) { selDistrictId.value = d.id; await onDistrictChange(); }
}

async function selectDistrict(row) {
    selDistrictId.value = row.id;
    await onDistrictChange();
}

function openModal(lvl, row = null) {
    level.value = lvl;
    editId.value = row?.id ?? null;
    // Reset form dengan parent default dari seleksi cascade bila create
    Object.assign(form, { code: '', name: '', type: 'kabupaten', province_id: selProvinceId.value, city_id: selCityId.value, district_id: selDistrictId.value, postal_code: '' });
    if (row) {
        form.code = row.code;
        form.name = row.name;
        form.postal_code = row.postal_code ?? '';
        form.province_id = row.province_id ?? selProvinceId.value;
        form.city_id = row.city_id ?? selCityId.value;
        form.district_id = row.district_id ?? selDistrictId.value;
        form.type = row.type ?? 'kabupaten';
    }
    if (lvl === 'city' && !form.province_id && selProvinceId.value) form.province_id = selProvinceId.value;
    if (lvl === 'district' && !form.city_id && selCityId.value) form.city_id = selCityId.value;
    if (lvl === 'village' && !form.district_id && selDistrictId.value) form.district_id = selDistrictId.value;
    error.value = '';
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    error.value = '';
}

async function remove(lvl, row) {
    const cascade = confirm(`Yakin hapus ${levelLabelFor(lvl)} "${row.name}" termasuk seluruh anaknya (bottom-up)?\n\nOK = hapus + anak · Cancel = batal`);
    if (!cascade) return;
    const endpoint = { province: '/address/provinces', city: '/address/cities', district: '/address/districts', village: '/address/villages' }[lvl];
    try {
        await api.delete(endpoint + '/' + row.id + '?cascade=1');
        // refresh level yang terdampak
        if (lvl === 'province') await loadProvinces();
        else if (lvl === 'city') await onProvinceChange();
        else if (lvl === 'district') await onCityChange();
        else await onDistrictChange();
    } catch (e) {
        alert(e.response?.data?.error?.message || 'Gagal menghapus.');
    }
}

async function restoreRow(lvl, row) {
    if (!confirm(`Yakin pulihkan ${levelLabelFor(lvl)} "${row.name}"?`)) return;
    const endpoint = { province: '/address/provinces', city: '/address/cities', district: '/address/districts', village: '/address/villages' }[lvl];
    try {
        await api.post(endpoint + '/' + row.id + '/restore');
        if (lvl === 'province') await loadProvinces();
        else if (lvl === 'city') await onProvinceChange();
        else if (lvl === 'district') await onCityChange();
        else await onDistrictChange();
    } catch (e) {
        alert(e.response?.data?.error?.message || 'Gagal memulihkan.');
    }
}

async function submit() {
    saving.value = true;
    error.value = '';
    const endpoint = { province: '/address/provinces', city: '/address/cities', district: '/address/districts', village: '/address/villages' }[level.value];
    const payload = {
        province: { code: form.code, name: form.name },
        city: { province_id: Number(form.province_id), code: form.code, name: form.name, type: form.type },
        district: { city_id: Number(form.city_id), code: form.code, name: form.name },
        village: { district_id: Number(form.district_id), code: form.code, name: form.name, postal_code: form.postal_code || null },
    }[level.value];
    try {
        if (editId.value) {
            await api.put(endpoint + '/' + editId.value, payload);
        } else {
            await api.post(endpoint, payload);
        }
        closeModal();
        // refresh level yang terdampak
        if (level.value === 'province') await loadProvinces();
        else if (level.value === 'city') await onProvinceChange();
        else if (level.value === 'district') await onCityChange();
        else await onDistrictChange();
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal menyimpan. Periksa kode (harus unik) dan isian.';
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    await loadProvinces();
    await onProvinceChange();
});
</script>
