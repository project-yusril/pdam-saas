<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">
          <slot name="title" />
        </h1>
        <p v-if="$slots.subtitle" class="text-sm text-gray-500 mt-1">
          <slot name="subtitle" />
        </p>
      </div>
      <div class="flex items-center gap-3">
        <slot name="actions" />
      </div>
    </div>
    <slot />
  </div>
</template>
