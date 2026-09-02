<template>
    <div class="flex items-center justify-end gap-1">
        <button
            v-if="showEdit"
            @click="$emit('edit', row)"
            class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-500 hover:text-primary-600 hover:bg-primary-50 transition-colors"
            title="Edit"
        >
            <i class="pi pi-pencil text-sm" />
        </button>
        <button
            v-if="showDelete"
            @click="$emit('delete', row)"
            class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-500 hover:text-red-600 hover:bg-red-50 transition-colors"
            title="Hapus"
        >
            <i class="pi pi-trash text-sm" />
        </button>
    </div>
</template>

<script setup>
defineProps({
    row: { type: Object, required: true },
    showEdit: { type: Boolean, default: true },
    showDelete: { type: Boolean, default: true },
});
defineEmits(['edit', 'delete']);
</script>