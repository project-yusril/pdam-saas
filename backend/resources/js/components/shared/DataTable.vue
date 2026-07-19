<template>
    <div>
        <div v-if="$slots.header" class="flex items-center justify-between mb-4">
            <slot name="header" />
        </div>
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
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
                    <template v-else-if="!rows || rows.length === 0">
                        <tr>
                            <td :colspan="colCount" class="px-4 py-12 text-center text-gray-400">
                                <i class="pi pi-inbox text-3xl mb-2 block" />
                                <slot name="empty">Tidak ada data</slot>
                            </td>
                        </tr>
                    </template>
                    <template v-else>
                        <tr v-for="(row, i) in rows" :key="'r-' + i" class="hover:bg-gray-50 transition-colors">
                            <slot name="row" :row="row" :index="i" />
                        </tr>
                    </template>
                </tbody>
            </table>
            <div
                v-if="total > perPage && !loading"
                class="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50"
            >
                <div class="text-sm text-gray-500">
                    Menampilkan {{ from }}–{{ to }} dari {{ total }}
                </div>
                <div class="flex items-center gap-2">
                    <select
                        v-model.number="perPageModel"
                        class="text-sm border border-gray-300 rounded-lg px-2 py-1"
                    >
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="75">75</option>
                        <option :value="100">100</option>
                    </select>
                    <div class="flex gap-1">
                        <button
                            :disabled="page <= 1"
                            @click="$emit('page-change', page - 1)"
                            class="px-3 py-1 text-sm border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100"
                        >
                            Prev
                        </button>
                        <button
                            :disabled="page >= lastPage"
                            @click="$emit('page-change', page + 1)"
                            class="px-3 py-1 text-sm border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    page: { type: Number, default: 1 },
    perPage: { type: Number, default: 25 },
    total: { type: Number, default: 0 },
    lastPage: { type: Number, default: 1 },
    from: { type: Number, default: 0 },
    to: { type: Number, default: 0 },
});

defineEmits(['page-change', 'per-page-change']);

const colCount = 20;

const perPageModel = computed({
    get: () => props.perPage,
    set: () => {},
});
</script>
