<template>
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <!-- Legend -->
        <div class="flex flex-wrap items-center gap-4 mb-6 text-xs text-gray-500">
            <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-primary-100 border border-primary-300" /> Proses</span>
            <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-gray-100 border border-gray-300" /> Keputusan / Percabangan</span>
            <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-green-100 border border-green-300" /> Selesai</span>
        </div>

        <div class="flex flex-col items-center">
            <template v-for="(step, i) in steps" :key="i">
                <!-- Step card -->
                <div class="w-full max-w-xl">
                    <div
                        class="rounded-lg border p-4"
                        :class="boxClass(step)"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold shrink-0" :class="numClass(step)">
                                {{ i + 1 }}
                            </div>
                            <div class="min-w-0">
                                <div class="font-semibold text-vueheading text-sm">{{ step.title }}</div>
                                <div v-if="step.description" class="text-xs text-gray-500 mt-0.5">{{ step.description }}</div>
                            </div>
                            <span v-if="step.role" class="ml-auto shrink-0 text-[11px] px-2 py-0.5 rounded-full bg-gray-50 border border-gray-200 text-gray-500">
                                {{ step.role }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Connector arrow -->
                <div v-if="i < steps.length - 1" class="my-1.5 flex flex-col items-center">
                    <div class="h-4 w-0.5 bg-primary-300"></div>
                    <i class="pi pi-arrow-down text-xs text-primary-400 my-0.5" />
                </div>
            </template>

            <div class="mt-5 inline-flex items-center gap-2 px-4 py-2 rounded-full bg-green-50 border border-green-200 text-green-700 text-sm font-medium">
                <i class="pi pi-check-circle" /> Selesai
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    steps: { type: Array, default: () => [] },
});

function boxClass(step) {
    if (step.type === 'decision') return 'bg-gray-50 border-gray-300';
    if (step.type === 'end') return 'bg-green-50 border-green-300';
    return 'bg-primary-50 border-primary-200';
}

function numClass(step) {
    if (step.type === 'decision') return 'bg-gray-200 text-gray-600';
    if (step.type === 'end') return 'bg-green-600 text-white';
    return 'bg-primary-600 text-white';
}
</script>
