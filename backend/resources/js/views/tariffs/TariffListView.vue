<template>
    <AppLayout page-title="Golongan Tarif" page-subtitle="Kelola golongan tarif & tier pemakaian air">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <i class="pi pi-tags text-primary-600 text-lg" />
                    <h2 class="font-semibold text-vueheading">Daftar Golongan Tarif</h2>
                </div>
                <button @click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700">
                    <i class="pi pi-plus text-xs" /> Tambah Golongan
                </button>
            </div>

            <DataTable :rows="tariffs" :loading="loading">
                <template #columns>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Golongan</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Abonemen</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tarif</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </template>
                <template #row="{ row }">
                    <td class="px-4 py-3 text-sm font-mono">{{ row.code }}</td>
                    <td class="px-4 py-3 font-medium text-vueheading">{{ row.name }}</td>
                    <td class="px-4 py-3 text-sm">{{ row.abonemen?.toLocaleString?.('id-ID') ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm text-right">{{ formatTier(row) }}</td>
                    <td class="px-4 py-3 text-right"><button @click="openEdit(row)" class="text-gray-500 hover:text-primary-600 text-sm font-medium">Edit</button></td>
                </template>
            </DataTable>
        </div>

        <!-- Modal -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="closeCreate">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-vueheading flex items-center gap-2"><i class="pi pi-tags text-primary-600" /> {{ editingId ? 'Edit Golongan Tarif' : 'Tambah Golongan Tarif' }}</h3>
                    <button class="p-2 rounded-md hover:bg-gray-100 text-gray-400" @click="closeCreate"><i class="pi pi-times" /></button>
                </div>

                <form class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submit">
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Kode <span class="text-red-500">*</span></label>
                        <input v-model="form.code" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="R1" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Nama Golongan <span class="text-red-500">*</span></label>
                        <input v-model="form.name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Rumah Tangga A1" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Grup <span class="text-red-500">*</span></label>
                        <select v-model="form.group_type" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                            <option value="" disabled>Pilih Grup</option>
                            <option value="rumah_tangga">Rumah Tangga</option>
                            <option value="bisnis">Bisnis</option>
                            <option value="sosial">Sosial</option>
                            <option value="pemerintah">Pemerintah</option>
                            <option value="industri">Industri</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Minimum (m³)</label>
                        <input v-model="form.minimum_usage_m3" type="number" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Abonemen</label>
                        <input v-model="form.abonemen" type="number" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-vuetext mb-1">Biaya Meter</label>
                        <input v-model="form.meter_maintenance_fee" type="number" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-vuetext mb-1">Biaya Admin</label>
                        <input v-model="form.admin_fee" type="number" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>

                    <div class="md:col-span-2">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-vuetext">Tier Pemakaian <span class="text-red-500">*</span></span>
                            <button type="button" @click="addTier" class="text-sm text-primary-600 hover:text-primary-700">+ Tambah Tier</button>
                        </div>
                        <div v-for="(tier, i) in form.tiers" :key="i" class="grid grid-cols-5 gap-2 mb-2">
                            <input v-model="tier.min_usage" type="number" min="0" placeholder="Min m³" class="border border-gray-300 rounded-lg px-2 py-2 text-sm" />
                            <input v-model="tier.max_usage" type="number" min="0" placeholder="Maks m³" class="border border-gray-300 rounded-lg px-2 py-2 text-sm" />
                            <input v-model="tier.price_per_m3" type="number" min="0" placeholder="Rp/m³" class="border border-gray-300 rounded-lg px-2 py-2 text-sm col-span-2" />
                            <button type="button" class="text-red-500 hover:bg-red-50 rounded-lg" @click="removeTier(i)"><i class="pi pi-trash" /></button>
                        </div>
                    </div>

                    <div v-if="error" class="md:col-span-2 px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ error }}</div>

                    <div class="md:col-span-2 flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                        <button type="button" @click="closeCreate" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700" :disabled="saving">
                            <i class="pi pi-check text-xs mr-1" /> {{ saving ? 'Menyimpan…' : (editingId ? 'Simpan Perubahan' : 'Simpan Golongan') }}
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

const tariffs = ref([]);
const loading = ref(true);
const showCreate = ref(false);
const saving = ref(false);
const error = ref('');
const editingId = ref(null);

const emptyTier = () => ({ tier_order: 1, min_usage: 0, max_usage: 10, price_per_m3: 0 });
const emptyForm = () => ({
    code: '',
    name: '',
    group_type: '',
    description: '',
    minimum_usage_m3: 10,
    abonemen: 0,
    meter_maintenance_fee: 0,
    admin_fee: 0,
    tiers: [emptyTier()],
});
const form = reactive(emptyForm());

function formatTier(row) {
    const t = (row.tiers || [])[0];
    if (!t) return '-';
    return `Rp ${Number(t.price_per_m3).toLocaleString('id-ID')}/m³`;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/tariffs');
        tariffs.value = data.data || [];
    } catch {
        tariffs.value = [];
    }
    loading.value = false;
}

function addTier() {
    const next = { ...emptyTier(), tier_order: form.tiers.length + 1, min_usage: form.tiers[form.tiers.length - 1]?.max_usage ?? 0 };
    form.tiers.push(next);
}
function removeTier(i) {
    form.tiers.splice(i, 1);
}

function openCreate() {
    editingId.value = null;
    Object.assign(form, emptyForm());
    error.value = '';
    showCreate.value = true;
}

function openEdit(row) {
    editingId.value = row.id;
    form.code = row.code;
    form.name = row.name;
    form.group_type = row.group_type || '';
    form.description = row.description || '';
    form.minimum_usage_m3 = row.minimum_usage_m3 ?? 0;
    form.abonemen = row.abonemen ?? 0;
    form.meter_maintenance_fee = row.meter_maintenance_fee ?? 0;
    form.admin_fee = row.admin_fee ?? 0;
    form.tiers = (row.tiers || []).map((t) => ({
        tier_order: t.tier_order,
        min_usage: t.min_usage,
        max_usage: t.max_usage,
        price_per_m3: t.price_per_m3,
    }));
    if (form.tiers.length === 0) form.tiers = [emptyTier()];
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
            code: form.code,
            name: form.name,
            group_type: form.group_type,
            description: form.description || null,
            minimum_usage_m3: Number(form.minimum_usage_m3),
            abonemen: Number(form.abonemen),
            meter_maintenance_fee: Number(form.meter_maintenance_fee),
            admin_fee: Number(form.admin_fee),
            tiers: form.tiers.map((t, i) => ({
                tier_order: i + 1,
                min_usage: Number(t.min_usage),
                max_usage: Number(t.max_usage),
                price_per_m3: Number(t.price_per_m3),
            })),
        };
        if (editingId.value) {
            await api.put('/tariffs/' + editingId.value, payload);
        } else {
            await api.post('/tariffs', payload);
        }
        closeCreate();
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal menyimpan golongan tarif.';
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
