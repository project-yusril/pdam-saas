<template>
  <div class="bg-white rounded-l border border-gray-200 p-5">
    <div class="flex items-start justify-between mb-3">
      <div
        v-if="icon"
        class="w-11 h-11 rounded-lg flex items-center justify-center shrink-0"
        :class="iconBg"
      >
        <i :class="[icon, iconColor]" class="text-xl" />
      </div>
      <span v-if="trend !== undefined" class="flex items-center gap-1 text-xs">
        <i
          :class="trend >= 0 ? 'pi pi-arrow-up-right text-green-600' : 'pi pi-arrow-down-right text-red-600'"
        />
        <span :class="trend >= 0 ? 'text-green-600' : 'text-red-600'">
          {{ trend >= 0 ? '+' : '' }}{{ trend }}%
        </span>
      </span>
    </div>
    <div class="text-2xl font-semibold text-vueheading">{{ value }}</div>
    <div class="text-sm text-gray-400 mt-1">{{ label }}</div>
  </div>
</template>

<script setup>
const props = defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], required: true },
  icon: { type: String, default: null },
  iconBg: { type: String, default: null },
  iconColor: { type: String, default: null },
  trend: { type: Number, default: undefined },
})
</script>
