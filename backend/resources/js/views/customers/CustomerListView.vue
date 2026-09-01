<template>
    <AppLayout page-title="Pelanggan" page-subtitle="Daftar & tambah pelanggan PDAM">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <i class="pi pi-users text-primary-600 text-lg" />
                    <h2 class="font-semibold text-vueheading">Daftar Pelanggan</h2>
                </div>
                <button
                    @click="openCreate"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700"
                >
                    <i class="pi pi-plus text-xs" /> Tambah Pelanggan
                </button>
            </div>

            <DataTable :rows="customers" :loading="loading">
                <template #columns>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Pelanggan</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wilayah</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Golongan</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Telepon</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </template>
                <template #row="{ row }">
                    <td class="px-4 py-3 text-sm font-mono">{{ row.customer_number }}</td>
                    <td class="px-4 py-3 font-medium text-vueheading">{{ row.full_name }}</td>
                    <td class="px-4 py-3 text-sm">{{ row.zone?.name || '-' }}</td>
                    <td class="px-4 py-3 text-sm">{{ row.tariff_category?.name || '-' }}</td>
                    <td class="px-4 py-3 text-sm">{{ row.phone || '-' }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 text-xs rounded-full" :class="statusClass(row.status)">{{ row.status }}</span>
                    </td>
                </template>
            </DataTable>
        </div>

        <!-- Modal Tambah Pelanggan -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="closeCreate">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-vueheading flex items-center gap-2"><i class="pi pi-plus text-primary-600" /> Tambah Pelanggan Baru</h3>
                    <button class="p-2 rounded-md hover:bg-gray-100 text-gray-400" @click="closeCreate"><i class="pi pi-times" /></button>
                </div>

                <form class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submit">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-vuetext mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input v-model="form.full_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="Nama pelanggan" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Telepon</label>
                        <input v-model="form.phone" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="08xxxxxxxxxx" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Email</label>
                        <input v-model="form.email" type="email" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="email@example.com" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Wilayah <span class="text-red-500">*</span></label>
                        <select v-model="form.zone_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 bg-white">
                            <option value="" disabled>Pilih Wilayah</option>
                            <option v-for="z in zones" :key="z.id" :value="z.id">{{ z.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Golongan Tarif <span class="text-red-500">*</span></label>
                        <select v-model="form.tariff_category_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 bg-white">
                            <option value="" disabled>Pilih Golongan</option>
                            <option v-for="t in tariffs" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Jalan</label>
                        <select v-model="form.street_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 bg-white">
                            <option value="">Tidak ada</option>
                            <option v-for="s in streets" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Rute Baca Meter</label>
                        <select v-model="form.meter_route_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 bg-white">
                            <option value="">Tidak ada</option>
                            <option v-for="r in meterRoutes" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">No. Meter</label>
                        <input v-model="form.meter_serial_number" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="Serial meter" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Tanggal Pasang</label>
                        <input v-model="form.installation_date" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-vuetext mb-1">Alamat Detail</label>
                        <input v-model="form.address_detail" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="RT/RW, Desa/Kelurahan, Kecamatan" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Latitude</label>
                        <input v-model="form.latitude" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="-0.1234" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Longitude</label>
                        <input v-model="form.longitude" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="109.2421" />
                    </div>

                    <div v-if="error" class="md:col-span-2 px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ error }}</div>

                    <div class="md:col-span-2 flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                        <button type="button" @click="closeCreate" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700" :disabled="saving">
                            <i class="pi pi-check text-xs mr-1" /> {{ saving ? 'Menyimpan…' : 'Simpan Pelanggan' }}
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

const customers = ref([]);
const loading = ref(true);

const zones = ref([]);
const tariffs = ref([]);
const streets = ref([]);
const meterRoutes = ref([]);

const showCreate = ref(false);
const saving = ref(false);
const error = ref('');

const emptyForm = () => ({
    full_name: '',
    phone: '',
    email: '',
    zone_id: '',
    tariff_category_id: '',
    street_id: '',
    address_detail: '',
    latitude: '',
    longitude: '',
    meter_serial_number: '',
    meter_route_id: '',
    installation_date: '',
});
const form = reactive(emptyForm());

function statusClass(status) {
    if (status === 'active') return 'bg-green-100 text-green-700';
    if (status === 'disconnected') return 'bg-red-100 text-red-700';
    return 'bg-gray-100 text-gray-600';
}

async function loadCustomers() {
    loading.value = true;
    try {
        const { data } = await api.get('/customers');
        customers.value = data.data || [];
    } catch {
        customers.value = [];
    }
    loading.value = false;
}

async function loadOptions() {
    try {
        const [z, t, s, r] = await Promise.all([
            api.get('/zones'),
            api.get('/tariffs'),
            api.get('/address/streets'),
            api.get('/meter-routes'),
        ]);
        zones.value = z.data.data || [];
        tariffs.value = t.data.data || [];
        streets.value = s.data.data || [];
        meterRoutes.value = r.data.data || [];
    } catch { /* Global API interceptor displays the failure. */ }
}

function openCreate() {
    Object.assign(form, emptyForm());
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
        const payload = { ...form };
        Object.keys(payload).forEach((k) => {
            if (payload[k] === '') payload[k] = null;
        });
        if (payload.zone_id) payload.zone_id = Number(payload.zone_id);
        if (payload.tariff_category_id) payload.tariff_category_id = Number(payload.tariff_category_id);
        if (payload.street_id) payload.street_id = Number(payload.street_id);
        if (payload.meter_route_id) payload.meter_route_id = Number(payload.meter_route_id);
        if (payload.latitude) payload.latitude = Number(payload.latitude);
        if (payload.longitude) payload.longitude = Number(payload.longitude);

        await api.post('/customers', payload);
        closeCreate();
        await loadCustomers();
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal menyimpan pelanggan. Periksa kembali isian.';
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    loadCustomers();
    loadOptions();
});
</script>
