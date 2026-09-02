<template>
    <AppLayout page-title="Alur Bisnis" page-subtitle="Diagram alur setiap proses bisnis PDAM SaaS">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <router-link
                v-for="f in flows"
                :key="f.key"
                :to="'/flows/' + f.key"
                class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md hover:border-primary-300 transition-all"
            >
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-11 h-11 rounded-lg flex items-center justify-center bg-primary-50 text-primary-600 shrink-0">
                        <i :class="['pi', f.icon]" class="text-xl" />
                    </div>
                    <div>
                        <div class="font-semibold text-vueheading">{{ f.label }}</div>
                        <div class="text-xs text-gray-400">Modul {{ f.group }} · {{ f.steps.length }} tahap</div>
                    </div>
                </div>
                <p class="text-sm text-gray-500 line-clamp-2">{{ f.summary }}</p>
                <div class="mt-3 text-sm text-primary-600 font-medium flex items-center gap-1">
                    Lihat diagram <i class="pi pi-arrow-right text-xs" />
                </div>
            </router-link>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { BUSINESS_FLOWS } from '../../config/flows';

const flows = BUSINESS_FLOWS;
</script>
