<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="w-full max-w-md mx-auto p-6">
      <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-pdam-blue-700">PDAM SaaS</h1>
        <p class="text-gray-500 mt-1">Sistem Manajemen PDAM</p>
      </div>
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <slot />
      </div>
    </div>
  </div>
</template>
