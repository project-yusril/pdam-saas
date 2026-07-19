<template>
    <div v-if="error" role="alert" class="fixed top-4 right-4 z-[1000] max-w-md rounded-lg border border-red-200 bg-red-50 p-4 text-red-800 shadow-lg">
        <div class="flex items-start gap-3">
            <i class="pi pi-exclamation-triangle mt-0.5" />
            <div class="flex-1">
                <p class="font-semibold">{{ error.title }}</p>
                <p class="mt-1 text-sm">{{ error.message }}</p>
            </div>
            <button type="button" aria-label="Tutup pesan" class="text-red-500 hover:text-red-800" @click="error = null">&times;</button>
        </div>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { API_ERROR_EVENT } from '../../api/errors.js';

const error = ref(null);
let timer;

function showError(event) {
    error.value = event.detail;
    clearTimeout(timer);
    timer = setTimeout(() => { error.value = null; }, 8000);
}

onMounted(() => window.addEventListener(API_ERROR_EVENT, showError));
onBeforeUnmount(() => {
    clearTimeout(timer);
    window.removeEventListener(API_ERROR_EVENT, showError);
});
</script>
