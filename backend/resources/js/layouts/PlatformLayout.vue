<template>
    <div class="flex h-screen overflow-hidden bg-slate-50">
        <!-- Sidebar cerah -->
        <aside :class="['bg-white border-r border-slate-200 flex flex-col flex-shrink-0 transition-all duration-200', collapsed ? 'w-20' : 'w-64']">
            <div class="h-16 flex items-center gap-3 px-5 border-b border-slate-100">
                <div class="w-9 h-9 rounded-lg bg-emerald-500 text-white flex items-center justify-center font-bold flex-shrink-0">P</div>
                <span v-if="!collapsed" class="text-base font-bold text-slate-800 truncate">PDAM SaaS</span>
            </div>

            <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-6">
                <div v-for="group in menu" :key="group.title">
                    <p v-if="!collapsed" class="px-3 mb-2 text-xs font-semibold tracking-wider text-slate-400 uppercase">{{ group.title }}</p>
                    <div class="space-y-1">
                        <router-link v-for="item in group.items" :key="item.to" :to="item.to"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
                            :class="isActive(item) ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100'"
                            :title="collapsed ? item.label : ''">
                            <i :class="[item.icon, 'text-base flex-shrink-0']" />
                            <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
                        </router-link>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Konten kanan -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header / topbar -->
            <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-5 flex-shrink-0">
                <button @click="collapsed = !collapsed" class="w-10 h-10 rounded-lg flex items-center justify-center text-slate-500 hover:bg-slate-100">
                    <i class="pi pi-bars text-lg" />
                </button>

                <div class="flex items-center gap-3">
                    <span class="hidden sm:inline-flex px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold">Panel Super Admin</span>
                    <button class="w-10 h-10 rounded-lg flex items-center justify-center text-slate-500 hover:bg-slate-100 relative">
                        <i class="pi pi-bell text-lg" />
                    </button>
                    <button class="w-10 h-10 rounded-lg flex items-center justify-center text-slate-500 hover:bg-slate-100">
                        <i class="pi pi-cog text-lg" />
                    </button>
                    <div class="hidden md:block text-right pl-2">
                        <p class="text-sm font-semibold text-slate-700 leading-tight">{{ auth.admin?.full_name || 'Super Administrator' }}</p>
                        <p class="text-xs text-slate-400">{{ auth.admin?.email }}</p>
                    </div>
                    <button @click="handleLogout" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-600 text-sm font-medium hover:bg-red-50 hover:text-red-600 transition-colors">Keluar</button>
                </div>
            </header>

            <!-- Isi halaman -->
            <main class="flex-1 overflow-y-auto p-6">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-slate-800">{{ pageTitle }}</h1>
                    <p v-if="pageSubtitle" class="text-sm text-slate-500 mt-1 max-w-3xl">{{ pageSubtitle }}</p>
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

defineProps({ pageTitle: String, pageSubtitle: String });
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const collapsed = ref(false);

const menu = [
    {
        title: 'Utama',
        items: [
            { to: '/platform', label: 'Dashboard', icon: 'pi pi-th-large', exact: true },
        ],
    },
    {
        title: 'Sistem',
        items: [
            { to: '/platform/tenants', label: 'Kelola Tenant', icon: 'pi pi-building' },
            { to: '/platform/modules', label: 'Marketplace Modul', icon: 'pi pi-shopping-bag' },
        ],
    },
];

function isActive(item) {
    if (item.exact) return route.path === item.to;
    return route.path.startsWith(item.to);
}

async function handleLogout() {
    await auth.logout();
    router.push('/login');
}
</script>
