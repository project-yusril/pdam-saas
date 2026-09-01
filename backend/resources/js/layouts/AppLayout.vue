<template>
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-260 shrink-0 bg-white border-e border-gray-200 flex flex-col">
            <div class="h-16 flex items-center gap-3 px-5">
                <div class="w-9 h-9 rounded-lg bg-primary-600 flex items-center justify-center text-white">
                    <i class="pi pi-water text-lg" />
                </div>
                <span class="text-lg font-semibold text-vueheading">PDAM SaaS</span>
                <button
                    class="ml-auto p-1.5 rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    @click="collapsed = !collapsed"
                >
                    <i :class="['pi', collapsed ? 'pi-angle-right' : 'pi-angle-left']" class="text-xs" />
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-3">
                <template v-for="item in menu" :key="item.to">
                    <!-- Group -->
                    <div v-if="item.children && item.children.length" class="mb-1">
                        <div
                            class="mx-1 my-3 px-3 text-[11px] font-medium uppercase tracking-wider text-gray-400"
                            v-if="!collapsed"
                        >
                            {{ item.label }}
                        </div>
                        <div v-if="collapsed" class="my-3 px-1">
                            <div class="h-px bg-gray-100" />
                        </div>

                        <template v-if="collapsed">
                            <div
                                class="flex items-center justify-center cursor-pointer p-2 mb-1 rounded-lg text-gray-500 hover:bg-gray-100"
                                @mouseenter="hoverMenu = item.to"
                                @mouseleave="hoverMenu = null"
                            >
                                <i :class="['pi', item.icon]" class="text-base" />
                            </div>
                            <div
                                v-if="hoverMenu === item.to"
                                class="fixed left-16 z-50 bg-white border border-gray-200 rounded-lg shadow-lg px-2 py-2 w-56"
                            >
                                <router-link
                                    v-for="child in item.children"
                                    :key="child.to"
                                    :to="child.to"
                                    class="block px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50"
                                    @click="hoverMenu = null"
                                >
                                    {{ child.label }}
                                </router-link>
                            </div>
                        </template>

                        <template v-else>
                            <button
                                @click="toggle(item.to)"
                                class="w-full flex items-center justify-between px-3 py-2 mb-0.5 rounded-lg text-sm font-medium transition-colors"
                                :class="groupActive(item) ? 'bg-primary-50 text-primary-600' : 'text-vuetext hover:bg-gray-100'"
                            >
                                <span class="flex items-center gap-3"><i :class="['pi', item.icon]" class="text-[15px]" /> {{ item.label }}</span>
                                <i :class="['pi', expanded.has(item.to) ? 'pi-chevron-down' : 'pi-chevron-right']" class="text-xs" />
                            </button>
                            <div v-if="expanded.has(item.to)" class="mb-2 ps-3 space-y-0.5">
                                <router-link
                                    v-for="child in item.children"
                                    :key="child.to"
                                    :to="child.to"
                                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm"
                                    :class="isActive(child.to) ? 'bg-primary-600 text-white' : 'text-vuetext hover:bg-gray-100'"
                                >
                                    <i :class="['pi', child.icon]" class="text-[13px]" /> {{ child.label }}
                                </router-link>
                            </div>
                        </template>
                    </div>

                    <!-- Plain link -->
                    <router-link
                        v-else
                        :to="item.to"
                        class="flex items-center gap-3 px-3 py-2 mb-0.5 rounded-lg text-sm font-medium"
                        :class="isActive(item.to) ? 'bg-primary-600 text-white' : 'text-vuetext hover:bg-gray-100'"
                        :title="collapsed ? item.label : ''"
                    >
                        <i :class="['pi', item.icon]" class="text-[15px]" /> <span v-if="!collapsed">{{ item.label }}</span>
                    </router-link>
                </template>
            </nav>

            <!-- Footer user -->
            <div class="p-3 border-t border-gray-100">
                <div class="flex items-center gap-3 px-2 py-2">
                    <div class="w-9 h-9 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center font-semibold text-sm shrink-0">
                        {{ avatarInitial }}
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-vueheading truncate">{{ auth.user?.name }}</div>
                        <div class="text-xs text-gray-400 truncate">{{ auth.organization?.name }}</div>
                    </div>
                    <button class="ml-auto p-1.5 rounded-md text-gray-400 hover:bg-gray-100 hover:text-red-600" @click="handleLogout" title="Logout">
                        <i class="pi pi-sign-out text-sm" />
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Topbar -->
            <header class="h-16 shrink-0 bg-white border-b border-gray-200 flex items-center gap-4 px-6">
                <div class="hidden md:flex items-center gap-2 w-72">
                    <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 w-full">
                        <i class="pi pi-search text-gray-400 text-sm" />
                        <input class="bg-transparent outline-none text-sm text-gray-700 w-full" placeholder="Search…" />
                    </div>
                </div>

                <div class="ml-auto flex items-center gap-1">
                    <button class="p-2 rounded-md text-gray-500 hover:bg-gray-100" title="Toggle theme" @click="toggleTheme">
                        <i class="pi pi-moon text-base" />
                    </button>
                    <button class="p-2 rounded-md text-gray-500 hover:bg-gray-100" title="Notifications">
                        <i class="pi pi-bell text-base" />
                    </button>
                    <button class="p-2 rounded-md text-gray-500 hover:bg-gray-100" title="Help">
                        <i class="pi pi-question text-base" />
                    </button>
                    <div class="mx-2 h-6 w-px bg-gray-200" />
                    <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-50 cursor-pointer" @click="handleLogout">
                        <div class="w-8 h-8 rounded-full bg-primary-600 text-white flex items-center justify-center font-semibold text-sm">
                            {{ avatarInitial }}
                        </div>
                        <span class="hidden sm:block text-sm font-medium text-vueheading">{{ auth.user?.name }}</span>
                        <i class="pi pi-chevron-down text-xs text-gray-400" />
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="flex-1 overflow-y-auto">
                <div class="px-6 py-5">
                    <h1 class="text-xl font-semibold text-vueheading mb-1">{{ pageTitle }}</h1>
                    <p v-if="pageSubtitle" class="text-sm text-gray-400 mb-5">{{ pageSubtitle }}</p>
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { MODULE_CATALOG, TIER_LABELS } from '../config/modules';

const props = defineProps({ pageTitle: String, pageSubtitle: String });
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const collapsed = ref(false);
const hoverMenu = ref(null);

const avatarInitial = computed(() => (auth.user?.name || 'U').charAt(0).toUpperCase());

const QUICK_MENU = [
    { to: '/dashboard', label: 'Dashboard', icon: 'pi-home', module: 'CORE' },
    {
        to: '/finance', label: 'Keuangan', icon: 'pi-chart-bar', module: 'FIN+',
        children: [
            { to: '/dashboard/finance', label: 'Ringkasan Keuangan', icon: 'pi-chart-line' },
            { to: '/finance/general-ledger', label: 'Buku Jurnal', icon: 'pi-book' },
            { to: '/finance/trial-balance', label: 'Neraca Saldo', icon: 'pi-table' },
            { to: '/finance/income-statement', label: 'Laba Rugi', icon: 'pi-chart-line' },
            { to: '/finance/balance-sheet', label: 'Neraca', icon: 'pi-building' },
            { to: '/finance/cash-flow', label: 'Arus Kas', icon: 'pi-wallet' },
        ],
    },
    { to: '/marketplace', label: 'Marketplace', icon: 'pi-shopping-cart' },
];

const TIER_ICONS = { 1: 'pi-box', 2: 'pi-briefcase', 3: 'pi-bolt' };

function buildModuleGroups(active) {
    const tiers = {};
    MODULE_CATALOG.forEach((m) => {
        if (m.code === 'FIN+' || m.code === 'CORE') return;
        if (!active.has(m.code)) return;
        if (!tiers[m.tier]) tiers[m.tier] = [];
        tiers[m.tier].push({ to: m.route, label: m.name, icon: 'pi-box' });
    });
    return Object.entries(tiers).map(([tier, children]) => ({
        to: `/modules/tier-${tier}`,
        label: TIER_LABELS[tier],
        icon: TIER_ICONS[tier],
        children,
    }));
}

const menu = computed(() => {
    const active = new Set(auth.activeModules ?? []);
    const quick = QUICK_MENU.filter((item) => !item.module || active.has(item.module));
    return [...quick, ...buildModuleGroups(active)];
});

const expanded = ref(
    new Set(
        menu.value
            .filter((item) => item.children?.some((c) => route.path === c.to || route.path.startsWith(c.to + '/')))
            .map((item) => item.to),
    ),
);

function toggle(key) {
    const next = new Set(expanded.value);
    next.has(key) ? next.delete(key) : next.add(key);
    expanded.value = next;
}

function isActive(to) {
    return route.path === to || route.path.startsWith(to + '/');
}

function groupActive(item) {
    return item.children?.some((c) => isActive(c.to));
}

function toggleTheme() {
    document.documentElement.classList.toggle('dark');
}

async function handleLogout() {
    await auth.logout();
    router.push('/login');
}

onMounted(() => {
    // Vuexy default: light theme
});
</script>

<style scoped>
.w-260 {
    width: 260px;
}
</style>
