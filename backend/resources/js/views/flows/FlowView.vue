<template>
    <AppLayout :page-title="flow?.label || 'Alur Bisnis'" :page-subtitle="flow ? (flow.summary || '') : 'Diagram alur proses bisnis'">
        <div v-if="!flow" class="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-400">
            <i class="pi pi-info-circle text-3xl mb-2 block" /> Alur tidak ditemukan.
        </div>

        <template v-else>
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium bg-primary-50 text-primary-700">
                    <i :class="['pi', flow.icon]" /> {{ flow.label }}
                </span>
                <span class="px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-600">Modul {{ flow.group }}</span>
            </div>

            <FlowDiagram :steps="flow.steps" />

            <div class="mt-6 bg-white rounded-xl border border-gray-200 p-5 text-sm text-gray-500">
                <span class="font-semibold text-vueheading block mb-1">Catatan</span>
                Diagram di atas merupakan ringkasan alur dari dokumen
                <router-link to="/flows" class="text-primary-600 underline">Alur Bisnis</router-link>.
                Setiap tahap tercatat di log audit status terkait.
            </div>
        </template>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppLayout from '../../layouts/AppLayout.vue';
import FlowDiagram from '../../components/shared/FlowDiagram.vue';
import { findFlow } from '../../config/flows';

const route = useRoute();
const flow = computed(() => findFlow(route.params.key));
</script>
