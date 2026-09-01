<template>
    <AppLayout :page-title="title" :page-subtitle="subtitle">
        <template v-if="!module">
            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-400">Modul tidak dikenal.</div>
        </template>

        <template v-else>
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium bg-blue-50 text-blue-700">
                    <i class="pi pi-box" /> {{ module.code }}
                </span>
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600">
                    Tier {{ module.tier }}
                </span>
            </div>

            <!-- KPI / object response (dashboard endpoints) -->
            <div v-if="isObject" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div v-for="(value, key) in kpis" :key="key" class="bg-white rounded-xl border border-gray-200 p-4">
                    <div class="text-xs text-gray-500">{{ prettyKey(key) }}</div>
                    <div class="text-lg font-semibold text-gray-800 mt-1">{{ displayValue(value, key) }}</div>
                </div>
            </div>

            <!-- Data table -->
            <template v-else>
                <DataTable :rows="rows" :loading="loading">
                    <template #columns>
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                        >
                            {{ col.label }}
                        </th>
                    </template>
                    <template #row="{ row }">
                        <td v-for="col in columns" :key="col.key" class="px-4 py-3 text-sm text-gray-700">
                            {{ displayValue(row[col.key], col.key) }}
                        </td>
                    </template>
                    <template #empty>
                        <slot name="empty">Tidak ada data pada modul ini.</slot>
                    </template>
                </DataTable>
            </template>
        </template>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import AppLayout from '../../layouts/AppLayout.vue';
import DataTable from '../../components/shared/DataTable.vue';
import api from '../../api';
import { findModule } from '../../config/modules';
import { formatRp } from '../../utils/money';

const route = useRoute();
const module = computed(() => findModule(route.params.code));
const title = computed(() => module.value?.name || 'Modul');
const subtitle = computed(() => (module.value ? `${module.value.code} — ${module.value.name}` : ''));

const loading = ref(true);
const rows = ref([]);
const kpis = ref({});
const isObject = ref(false);

const columns = computed(() => {
    if (isObject.value || rows.value.length === 0) return [];
    const first = rows.value[0];
    return Object.keys(first)
        .filter((k) => !['id', 'pdam_org_id', 'created_at', 'updated_at', 'tenant_id'].includes(k))
        .filter((k) => !k.endsWith('_id'))
        .slice(0, 8)
        .map((k) => ({ key: k, label: prettyKey(k) }));
});

function prettyKey(key) {
    return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function isMoneyKey(key) {
    return /amount|price|total|cost|debit|credit|balance|salary|value|revenue|fee|charge|penalty|stock/i.test(key);
}

function displayValue(value, key) {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'number' || (typeof value === 'string' && /^-?[0-9.,]+$/.test(String(value)))) {
        const n = Number(String(value).replace(/,/g, ''));
        if (!Number.isNaN(n)) {
            return isMoneyKey(key) ? formatRp(n) : n.toLocaleString('id-ID');
        }
    }
    return String(value);
}

onMounted(async () => {
    if (!module.value?.endpoint) {
        loading.value = false;
        return;
    }
    loading.value = true;
    try {
        const { data } = await api.get(module.value.endpoint);
        const payload = data.data;
        if (Array.isArray(payload)) {
            rows.value = payload;
            isObject.value = false;
        } else if (payload && typeof payload === 'object') {
            kpis.value = payload;
            isObject.value = true;
        } else {
            rows.value = [];
        }
    } catch {
        rows.value = [];
    }
    loading.value = false;
});
</script>
