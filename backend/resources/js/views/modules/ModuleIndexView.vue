<template>
    <AppLayout :page-title="title" :page-subtitle="subtitle">
        <div v-if="error" class="mb-4 px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm flex items-center justify-between">
            <span><i class="pi pi-exclamation-triangle mr-2" />{{ error }}</span>
            <button class="text-sm underline" @click="load">Coba lagi</button>
        </div>

        <template v-if="!module">
            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-400">Modul tidak dikenal.</div>
        </template>

        <template v-else-if="resource && resource.kind === 'kpi' && isObject">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div v-for="(value, key) in kpis" :key="key" class="bg-white rounded-xl border border-gray-200 p-4">
                    <div class="text-xs text-gray-500">{{ prettyKey(key) }}</div>
                    <div class="text-lg font-semibold text-gray-800 mt-1">{{ displayValue(value, key) }}</div>
                </div>
            </div>
        </template>

        <template v-else-if="resource && resource.kind === 'info'">
            <div class="bg-white rounded-xl border border-gray-200 p-10">
                <h3 class="font-semibold text-vueheading mb-2">{{ module.name }}</h3>
                <p class="text-sm text-gray-500">
                    Modul informasi — belum ada endpoint list untuk halaman ini. Lihat dashboard terkait / dokumentasi
                    operasional produk untuk alur kerja modul ini.
                </p>
            </div>
        </template>

        <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <!-- Toolbar -->
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <i class="pi pi-box text-primary-600 text-lg" />
                    <h2 class="font-semibold text-vueheading">Daftar {{ module.name }}</h2>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        v-if="resource && resource.searchable"
                        v-model="q"
                        class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-48"
                        placeholder="Cari…"
                        @keyup.enter="reload({ page: 1 })"
                    />
                    <select v-if="resource && resource.paginated" v-model.number="perPage" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm" @change="reload({ page: 1 })">
                        <option :value="25">25/hal</option>
                        <option :value="50">50/hal</option>
                        <option :value="100">100/hal</option>
                    </select>
                    <button
                        v-for="top in (resource && resource.actions) || []"
                        :key="top.key"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm border border-gray-300 hover:bg-gray-50"
                        @click="runTopLevel(top)"
                    >
                        <i class="pi pi-bolt text-xs" /> {{ top.label }}
                    </button>
                    <button
                        v-if="resource && resource.create"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700"
                        @click="openCreate"
                    >
                        <i class="pi pi-plus text-xs" /> {{ resource.create.title }}
                    </button>
                </div>
            </div>

            <DataTable :rows="rows" :loading="loading">
                <template #columns>
                    <th v-for="col in columns" :key="col.key" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        {{ col.label }}
                    </th>
                    <th v-if="visibleActions.length" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </template>
                <template #row="{ row }">
                    <td v-for="col in columns" :key="col.key" class="px-4 py-3 text-sm text-gray-700">
                        {{ displayValue(row[col.key], col.key) }}
                    </td>
                    <td v-if="visibleActions.length" class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        <button
                            v-for="action in applicableActions(row)"
                            :key="action.key"
                            class="text-primary-600 hover:text-primary-800 text-sm font-medium"
                            @click="runRowAction(action, row)"
                        >
                            {{ action.label }}
                        </button>
                    </td>
                </template>
                <template #empty>
                    <slot name="empty">Tidak ada data pada modul ini.</slot>
                </template>
            </DataTable>

            <!-- Pagination -->
            <div v-if="meta && totalPages(meta) > 1" class="flex items-center justify-between px-5 py-3 border-t border-gray-100 text-sm text-gray-600">
                <span>{{ meta.from }}–{{ meta.to }} dari {{ meta.total }}</span>
                <div class="space-x-2">
                    <button class="px-3 py-1 rounded border disabled:opacity-40" :disabled="page <= 1" @click="reload({ page: page - 1 })">«</button>
                    <span class="px-2">Hal {{ page }}/{{ totalPages(meta) }}</span>
                    <button class="px-3 py-1 rounded border disabled:opacity-40" :disabled="page >= totalPages(meta)" @click="reload({ page: page + 1 })">»</button>
                </div>
            </div>
        </div>

        <!-- Modal create -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="closeCreate">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-vueheading">{{ resource.create.title }}</h3>
                    <button class="p-2 rounded-md hover:bg-gray-100 text-gray-400" @click="closeCreate"><i class="pi pi-times" /></button>
                </div>
                <form class="p-6 space-y-4" @submit.prevent="submitCreate">
                    <div v-for="field in resource.create.fields" :key="field.key">
                        <label class="block text-sm font-medium text-vuetext mb-1">
                            {{ field.label }} <span v-if="field.required" class="text-red-500">*</span>
                        </label>
                        <select v-if="field.type === 'select'" v-model="form[field.key]" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">— pilih —</option>
                            <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
                        </select>
                        <textarea v-else-if="field.type === 'textarea' || field.type === 'json'" v-model="form[field.key]" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" />
                        <input v-else :type="inputType(field)" v-model="form[field.key]" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        <p v-if="formErrors[field.key]" class="text-red-500 text-xs mt-1">{{ formErrors[field.key] }}</p>
                    </div>
                    <div v-if="resource.create.fileField" class="border border-dashed border-gray-300 rounded-lg p-3 text-sm">
                        <label class="text-vuetext">{{ resource.create.fileField.label }}</label>
                        <input type="file" class="mt-1 block" @change="onFilePicked" />
                    </div>
                    <div v-if="createError" class="px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ createError }}</div>
                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                        <button type="button" class="px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100" @click="closeCreate">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700" :disabled="saving">
                            {{ saving ? 'Menyimpan…' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import AppLayout from '../../layouts/AppLayout.vue';
import DataTable from '../../components/shared/DataTable.vue';
import api from '../../api';
import { getApiErrorMessage } from '../../api/errors.js';
import { findModule } from '../../config/modules';
import { RESOURCE_CONFIG } from '../../config/resources';
import { buildListParams, normalizeListEnvelope, totalPages, validateCreateFields } from '../../composables/workbench.js';
import { formatRp } from '../../utils/money';

const route = useRoute();
const module = computed(() => findModule(route.params.code));
const resource = computed(() => (module.value ? RESOURCE_CONFIG[module.value.code] : null));
const title = computed(() => module.value?.name || 'Modul');
const subtitle = computed(() => (module.value ? `${module.value.code} — ${module.value.name}` : ''));
const listPath = computed(() => (resource.value && resource.value.list) || module.value?.endpoint || '');

const loading = ref(true);
const error = ref('');
const rows = ref([]);
const meta = ref(null);
const q = ref('');
const page = ref(1);
const perPage = ref(25);
const isObject = ref(false);
const kpis = ref({});

const showCreate = ref(false);
const saving = ref(false);
const createError = ref('');
const form = reactive({});
const formErrors = ref({});
const pickedFile = ref(null);

const visibleActions = computed(() => (resource.value && resource.value.rowActions) || []);

const columns = computed(() => {
    if (rows.value.length === 0) return [];
    const first = rows.value[0];

    return Object.keys(first)
        .filter((k) => !['id', 'pdam_org_id', 'created_at', 'updated_at', 'tenant_id', 'deleted_at'].includes(k))
        .slice(0, 8)
        .map((k) => ({ key: k, label: prettyKey(k) }));
});

function prettyKey(key) {
    return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function isMoneyKey(key) {
    return /amount|price|total|cost|debit|credit|balance|salary|value|revenue|fee|charge|penalty/i.test(key);
}

function displayValue(value, key) {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'number' || (typeof value === 'string' && /^-?[0-9.,]+$/.test(String(value)))) {
        const n = Number(String(value).replace(/,/g, ''));
        if (!Number.isNaN(n)) {
            return isMoneyKey(key) ? formatRp(n) : n.toLocaleString('id-ID');
        }
    }
    if (Array.isArray(value) || (typeof value === 'object')) return prettyKey(JSON.stringify(value).slice(0, 60));

    return String(value);
}

function inputType(field) {
    return field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text';
}

function applicableActions(row) {
    return visibleActions.value.filter((a) => !a.when || a.when(row));
}

async function load() {
    if (!listPath.value) {
        loading.value = false;
        error.value = '';

        return;
    }
    loading.value = true;
    error.value = '';
    try {
        const { data } = await api.get(listPath.value, {
            params: resource.value ? buildListParams(resource.value, { page: page.value, perPage: perPage.value, q: q.value }) : { page: page.value, per_page: perPage.value },
        });
        const payload = data.data;
        if (payload && !Array.isArray(payload) && typeof payload === 'object') {
            kpis.value = payload;
            isObject.value = true;
        } else {
            const norm = normalizeListEnvelope(data);
            rows.value = norm.rows;
            meta.value = norm.meta;
            isObject.value = false;
        }
    } catch (e) {
        error.value = getApiErrorMessage(e, 'Gagal memuat data modul. Periksa koneksi/entitlement.');
        rows.value = [];
    }
    loading.value = false;
}

function reload(patch = {}) {
    page.value = patch.page ?? page.value;
    load();
}

function openCreate() {
    for (const key of Object.keys(form)) delete form[key];
    for (const field of resource.value.create.fields) form[field.key] = '';
    formErrors.value = {};
    createError.value = '';
    pickedFile.value = null;
    showCreate.value = true;
}

function closeCreate() {
    showCreate.value = false;
}

function onFilePicked(ev) {
    pickedFile.value = ev.target.files && ev.target.files[0] ? ev.target.files[0] : null;
}

async function submitCreate() {
    saving.value = true;
    createError.value = '';
    const { valid, errors, payload } = validateCreateFields(resource.value.create.fields, { ...form });
    formErrors.value = errors;
    if (!valid) {
        saving.value = false;

        return;
    }
    try {
        if (resource.value.create.fileField) {
            if (!pickedFile.value) throw new Error('file wajib dipilih');
            const fd = new FormData();
            fd.append('file', pickedFile.value);
            for (const [k, v] of Object.entries(payload)) fd.append(k, typeof v === 'object' ? JSON.stringify(v) : v);
            await api.post(listPath.value, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
        } else {
            await api.post(listPath.value, payload);
        }
        closeCreate();
        await load();
    } catch (e) {
        createError.value = resource.value.create.submitError || getApiErrorMessage(e, e.message);
    } finally {
        saving.value = false;
    }
}

async function runRowAction(action, row) {
    try {
        let body = {};
        if (action.select) {
            const chosen = window.prompt(`${action.select.label} — opsi: ${action.select.options.join(', ')}`);
            if (!chosen || !action.select.options.includes(chosen)) return;
            body = { [action.select.key]: chosen };
        }
        await api.post(action.path(row), body);
        await load();
    } catch (e) {
        error.value = getApiErrorMessage(e, 'Aksi gagal.');
    }
}

async function runTopLevel(top) {
    try {
        await api.post(top.path);
        await load();
    } catch (e) {
        error.value = getApiErrorMessage(e, 'Aksi gagal.');
    }
}

onMounted(load);
</script>
