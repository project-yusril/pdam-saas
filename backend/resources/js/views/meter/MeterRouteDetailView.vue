<template>
    <AppLayout page-title="Rute Baca Meter" page-subtitle="Kelola jalan yang tergabung dalam rute ini">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5 lg:col-span-1">
                <h2 class="font-semibold text-vueheading">{{ route?.name || '…' }}</h2>
                <p class="text-sm text-gray-500 mt-1">Kode: {{ route?.code }} · Wilayah: {{ route?.zone?.name || '-' }}</p>
                <div class="mt-3 flex items-center gap-4 text-sm">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-map text-primary-600" />
                        <span class="font-medium">{{ assignedCount }} jalan</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden lg:col-span-2">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-vueheading">Jalan dalam Rute</h2>
                    <button @click="save" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700" :disabled="saving">
                        <i class="pi pi-save text-xs" /> {{ saving ? 'Menyimpan…' : 'Simpan Jalan Rute' }}
                    </button>
                </div>

                <div v-if="loading" class="p-6 text-sm text-gray-400">Memuat…</div>
                <div v-else class="max-h-[60vh] overflow-y-auto p-2">
                    <label
                        v-for="s in allStreets"
                        :key="s.id"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 cursor-pointer"
                    >
                        <input
                            type="checkbox"
                            :checked="selected.has(s.id)"
                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            @change="toggle(s.id)"
                        />
                        <span class="text-sm text-vuetext">{{ s.name }}</span>
                    </label>
                    <p v-if="allStreets.length === 0" class="p-6 text-center text-sm text-gray-400">Belum ada data jalan. Tambah di halaman <router-link to="/streets" class="text-primary-600 underline">Jalan</router-link>.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import AppLayout from '../../layouts/AppLayout.vue';
import api from '../../api';

const routeMeta = useRoute();
const route = ref(null);
const allStreets = ref([]);
const selected = ref(new Set());
const loading = ref(true);
const saving = ref(false);

const assignedCount = computed(() => selected.value.size);

async function load() {
    loading.value = true;
    try {
        const [{ data: r }, { data: s }] = await Promise.all([
            api.get('/meter-routes/' + routeMeta.params.id),
            api.get('/address/streets'),
        ]);
        route.value = r.data;
        allStreets.value = s.data || [];
        selected.value = new Set((r.data.streets || []).map((x) => x.id));
    } catch {
        route.value = null;
        allStreets.value = [];
        selected.value = new Set();
    }
    loading.value = false;
}

function toggle(id) {
    const next = new Set(selected.value);
    next.has(id) ? next.delete(id) : next.add(id);
    selected.value = next;
}

async function save() {
    saving.value = true;
    try {
        await api.post('/meter-routes/' + routeMeta.params.id + '/sync-streets', {
            street_ids: Array.from(selected.value),
        });
        await load();
    } catch { /* Global API interceptor */ } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
