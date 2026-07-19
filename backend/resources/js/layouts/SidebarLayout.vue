<template>
  <div class="flex h-screen overflow-hidden">
    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col flex-shrink-0">
      <div class="h-16 flex items-center px-6 border-b border-gray-100">
        <span class="text-lg font-semibold text-pdam-blue-700">PDAM SaaS</span>
      </div>
      <nav class="flex-1 overflow-y-auto p-3 space-y-1">
        <slot name="menu" />
      </nav>
      <div class="p-3 border-t border-gray-100">
        <div class="text-sm text-gray-600 px-3 py-1">{{ auth.organization?.name }}</div>
        <div class="text-xs text-gray-400 px-3">{{ auth.user?.name }}</div>
        <button
          @click="logout"
          class="mt-2 w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors"
        >
          Logout
        </button>
      </div>
    </aside>
    <main class="flex-1 overflow-y-auto bg-gray-50">
      <div class="p-6">
        <slot />
      </div>
    </main>
  </div>
</template>

<script setup >
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>
