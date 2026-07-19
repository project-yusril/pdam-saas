<template>
  <div class="bg-white rounded-xl border border-gray-200 p-6">
    <div class="flex items-center gap-3 mb-4">
      <div
        v-if="icon"
        class="w-10 h-10 rounded-lg flex items-center justify-center"
        :class="iconBg"
      >
        <i :class="[icon, iconColor]" class="text-lg" />
      </div>
      <div>
        <div class="text-sm text-gray-500">{{ label }}</div>
        <div class="text-2xl font-bold text-gray-800">{{ value }}</div>
      </div>
    </div>
    <div v-if="trend !== undefined" class="flex items-center gap-1 text-sm">
      <span :class="trend >= 0 ? 'text-green-600' : 'text-red-600'">
        {{ trend >= 0 ? '+' : '' }}{{ trend }}%
      </span>
      <span class="text-gray-400">vs periode lalu</span>
    </div>
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
