<template>
    <div>
        <div v-if="$slots.header" class="mb-4">
            <slot name="header" />
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <!-- Toolbar: Show X entries (kiri) + Search (kanan) -->
            <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-100">
                <div class="flex items-center gap-2 text-sm text-vuetext">
                    <span>Show</span>
                    <select v-model.number="entriesModel" class="text-sm border border-gray-300 rounded-lg px-2 py-1 bg-white">
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                    <span>entries</span>
                </div>

                <div v-if="!server" class="flex items-center gap-2 text-sm">
                    <div class="flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-1.5 bg-white">
                        <i class="pi pi-search text-gray-400 text-sm" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search..."
                            class="bg-transparent outline-none text-sm text-gray-700 w-52"
                        />
                    </div>
                </div>
                <div v-else class="flex items-center gap-2 text-sm">
                    <div class="flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-1.5 bg-white">
                        <i class="pi pi-search text-gray-400 text-sm" />
                        <input
                            :value="externalSearch"
                            @input="$emit('search', $event.target.value)"
                            type="text"
                            placeholder="Search..."
                            class="bg-transparent outline-none text-sm text-gray-700 w-52"
                        />
                    </div>
                </div>
            </div>

            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <slot name="columns" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template v-if="loading">
                        <tr v-for="i in 3" :key="'sk-' + i">
                            <td v-for="j in colCount" :key="'skc-' + j" class="px-4 py-3">
                                <div class="h-4 bg-gray-200 rounded animate-pulse" />
                            </td>
                        </tr>
                    </template>
                    <template v-else-if="displayRows.length === 0">
                        <tr>
                            <td :colspan="colCount" class="px-4 py-12 text-center text-gray-400">
                                <i class="pi pi-inbox text-3xl mb-2 block" />
                                <slot name="empty">Tidak ada data</slot>
                            </td>
                        </tr>
                    </template>
                    <template v-else>
                        <tr v-for="(row, i) in displayRows" :key="'r-' + i" class="hover:bg-gray-50 transition-colors">
                            <slot name="row" :row="row" :index="i" />
                        </tr>
                    </template>
                </tbody>
            </table>

            <!-- Footer: Showing X to Y of Z entries (kiri) + Pagination (kanan) -->
            <div class="flex items-center justify-between gap-3 px-4 py-3 border-t border-gray-100 bg-gray-50">
                <div class="text-sm text-gray-600">
                    Showing {{ fromIndex }} to {{ toIndex }} of {{ totalCount }} entries
                </div>

                <div class="flex items-center gap-1">
                    <button
                        :disabled="page <= 1"
                        @click="goPage(page - 1)"
                        class="px-3 py-1 text-sm border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100"
                    >
                        Previous
                    </button>
                    <span
                        v-for="p in pageNumbers"
                        :key="p"
                        @click="goPage(p)"
                        class="min-w-[32px] h-8 inline-flex items-center justify-center px-2 text-sm border rounded-lg cursor-pointer"
                        :class="p === page ? 'bg-primary-600 text-white border-primary-600' : 'border-gray-300 text-gray-600 hover:bg-gray-100'"
                    >
                        {{ p }}
                    </span>
                    <button
                        :disabled="page >= lastPage"
                        @click="goPage(page + 1)"
                        class="px-3 py-1 text-sm border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    // Server-side mode (hanya untuk list yang fetch per halaman, mis. Pegawai)
    server: { type: Boolean, default: false },
    page: { type: Number, default: 1 },
    perPage: { type: Number, default: 10 },
    total: { type: Number, default: 0 },
    lastPage: { type: Number, default: 1 },
    from: { type: Number, default: 0 },
    to: { type: Number, default: 0 },
    externalSearch: { type: String, default: '' },
});

const emit = defineEmits(['page-change', 'per-page-change', 'search']);

const colCount = 20;

// ── Client mode: search + pagination over `rows` ──────────────────────
const search = ref('');
const page = ref(1);
const pageSize = ref(10);

watch([search, pageSize], () => {
    page.value = 1;
});

function rowSearchText(row) {
    return Object.values(row)
        .filter((v) => v !== null && v !== undefined && typeof v !== 'object')
        .join(' ').toLowerCase();
}

const filteredRows = computed(() => {
    if (!search.value.trim()) return props.rows;
    const q = search.value.trim().toLowerCase();
    return props.rows.filter((r) => rowSearchText(r).includes(q));
});

const totalCount = computed(() => (props.server ? props.total : filteredRows.value.length));
const lastPage = computed(() => (props.server ? props.lastPage : Math.max(1, Math.ceil(totalCount.value / pageSize.value))));
const currentPage = computed(() => (props.server ? props.page : Math.min(page.value, lastPage.value)));
const fromIndex = computed(() => {
    if (props.server) return props.from;
    return totalCount.value === 0 ? 0 : (currentPage.value - 1) * pageSize.value + 1;
});
const toIndex = computed(() => {
    if (props.server) return props.to;
    return Math.min(currentPage.value * pageSize.value, totalCount.value);
});

const displayRows = computed(() => {
    if (props.server) return props.rows;
    const start = (currentPage.value - 1) * pageSize.value;
    return filteredRows.value.slice(start, start + pageSize.value);
});

const pageNumbers = computed(() => {
    const total = lastPage.value;
    const cur = currentPage.value;
    const pages = new Set();
    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || Math.abs(i - cur) <= 1) pages.add(i);
    }
    const arr = [...pages].sort((a, b) => a - b);
    const out = [];
    let prev = 0;
    for (const p of arr) {
        if (p - prev > 1) out.push('...');
        out.push(p);
        prev = p;
    }
    return out;
});

const entriesModel = computed({
    get: () => (props.server ? props.perPage : pageSize.value),
    set: (v) => {
        if (props.server) {
            emit('per-page-change', v);
        } else {
            pageSize.value = v;
        }
    },
});

function goPage(p) {
    if (p < 1 || p > lastPage.value || p === currentPage.value) return;
    if (props.server) {
        emit('page-change', p);
    } else {
        page.value = p;
    }
}
</script>
