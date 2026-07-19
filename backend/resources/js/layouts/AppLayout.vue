<template>
    <div>
        <div class="flex h-screen overflow-hidden">
            <aside class="w-64 bg-white border-r border-gray-200 flex flex-col flex-shrink-0">
                <div class="h-16 flex items-center px-6 border-b border-gray-100">
                    <span class="text-lg font-semibold text-blue-700">PDAM SaaS</span>
                </div>
                <nav class="flex-1 overflow-y-auto p-3 space-y-1">
                    <router-link v-for="item in menu" :key="item.to" :to="item.to" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium" :class="route.path === item.to || route.path.startsWith(item.to + '/') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100'"><i :class="['pi', item.icon]" /> {{ item.label }}</router-link>
                </nav>
                <div class="p-3 border-t border-gray-100">
                    <div class="text-sm text-gray-600 px-3 py-1">{{ auth.organization?.name }}</div>
                    <div class="text-xs text-gray-400 px-3">{{ auth.user?.name }}</div>
                    <button @click="handleLogout" class="mt-2 w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg">Logout</button>
                </div>
            </aside>
            <main class="flex-1 overflow-y-auto bg-gray-50">
                <div class="p-6">
                    <p class="text-lg font-bold text-gray-800 mb-2">{{ pageTitle }}</p>
                    <p v-if="pageSubtitle" class="text-sm text-gray-500 mb-6">{{ pageSubtitle }}</p>
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const props = defineProps({ pageTitle: String, pageSubtitle: String });
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const menu = [
    { to: '/dashboard', label: 'Dashboard', icon: 'pi-home' },
    { to: '/zones', label: 'Wilayah', icon: 'pi-map' },
    { to: '/prospects', label: 'Pemasangan Baru', icon: 'pi-user-plus' },
    { to: '/meter-routes', label: 'Rute Baca Meter', icon: 'pi-compass' },
    { to: '/complaints', label: 'Pengaduan', icon: 'pi-comments' },
    { to: '/assets', label: 'Aset', icon: 'pi-box' },
    { to: '/gis', label: 'GIS', icon: 'pi-globe' },
    { to: '/employees', label: 'Pegawai', icon: 'pi-users' },
    { to: '/call-center', label: 'Call Center', icon: 'pi-phone' },
    { to: '/tenders', label: 'Tender', icon: 'pi-briefcase' },
    { to: '/scheduled-reports', label: 'Laporan Terjadwal', icon: 'pi-calendar' },
    { to: '/marketplace', label: 'Marketplace', icon: 'pi-shopping-cart' },
];

async function handleLogout() {
    await auth.logout();
    router.push('/login');
}
</script>
