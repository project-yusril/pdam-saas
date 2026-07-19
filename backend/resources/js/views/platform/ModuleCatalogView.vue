<template>
    <PlatformLayout page-title="Marketplace Modul"
        page-subtitle="Aktif/nonaktifkan modul sesuai kebutuhan PDAM. Beberapa modul saling terhubung — untuk mengaktifkan sebuah modul, modul yang menjadi prasyaratnya harus aktif lebih dulu.">

        <!-- Filter tier -->
        <div class="flex flex-wrap gap-2 mb-6">
            <button v-for="t in tierFilters" :key="t.key" @click="activeTier = t.key"
                :class="['px-5 py-2 rounded-full text-sm font-medium transition-colors border',
                    activeTier === t.key ? 'bg-emerald-500 text-white border-emerald-500 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:border-emerald-300']">
                {{ t.label }}
                <span :class="['ml-1.5 text-xs', activeTier === t.key ? 'text-emerald-100' : 'text-slate-400']">{{ countFor(t.key) }}</span>
            </button>
        </div>

        <div v-if="loading" class="text-slate-400 py-12 text-center">Memuat modul...</div>
        <div v-else-if="!filtered.length" class="text-slate-400 py-12 text-center">Tidak ada modul pada kategori ini.</div>

        <!-- Grid kartu -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            <div v-for="m in filtered" :key="m.code"
                class="group bg-white rounded-2xl border border-slate-200 p-6 flex flex-col shadow-sm hover:shadow-lg hover:border-emerald-200 transition-all">

                <!-- Header: icon + tier badge -->
                <div class="flex items-start justify-between mb-3">
                    <div :class="['w-11 h-11 rounded-xl flex items-center justify-center text-lg', tierStyle(m.tier).icon]">
                        {{ tierStyle(m.tier).emoji }}
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <span v-if="m.is_default" class="px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide bg-blue-100 text-blue-700">MODUL INTI</span>
                        <span :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold tracking-wide', tierStyle(m.tier).badge]">{{ tierStyle(m.tier).label }}</span>
                    </div>
                </div>

                <!-- Nama + kode -->
                <div class="mb-2">
                    <h3 class="text-lg font-bold text-slate-800 leading-snug">{{ m.name }}</h3>
                    <span class="text-xs font-mono text-slate-400">{{ m.code }}</span>
                </div>

                <!-- Deskripsi -->
                <p class="text-sm text-slate-600 leading-relaxed mb-4">{{ m.description || 'Tidak ada deskripsi.' }}</p>

                <!-- Dependency / integrasi -->
                <div class="mt-auto">
                    <div v-if="deps(m).length" class="mb-4 rounded-xl bg-amber-50 border border-amber-100 p-3">
                        <p class="text-xs font-semibold text-amber-700 mb-2 flex items-center gap-1">
                            <span>🔗</span> Butuh modul ini aktif lebih dulu
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="d in deps(m)" :key="d.code"
                                class="px-2 py-1 rounded-lg bg-white border border-amber-200 text-xs font-medium text-amber-800">
                                {{ d.name }}
                            </span>
                        </div>
                    </div>
                    <div v-else class="mb-4 rounded-xl bg-emerald-50 border border-emerald-100 p-3">
                        <p class="text-xs font-medium text-emerald-700 flex items-center gap-1">
                            <span>✓</span> Modul mandiri — bisa aktif tanpa prasyarat
                        </p>
                    </div>

                    <!-- Modul lain yang membutuhkan modul ini -->
                    <p v-if="requiredBy(m).length" class="text-xs text-slate-400 mb-4 leading-relaxed">
                        Menjadi prasyarat untuk: <span class="text-slate-500 font-medium">{{ requiredBy(m).map(x => x.name).join(', ') }}</span>
                    </p>

                    <!-- Harga + aksi -->
                    <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                        <div>
                            <p class="text-[11px] text-slate-400 uppercase tracking-wide">Harga langganan</p>
                            <span class="text-base font-bold text-slate-800">{{ formatPrice(m.base_price_year) }}</span>
                        </div>
                        <span :class="['px-3 py-1.5 rounded-lg text-xs font-semibold',
                            m.is_default ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600']">
                            {{ m.is_default ? 'Termasuk Paket Dasar' : 'Add-on' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </PlatformLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../api';
import PlatformLayout from '../../layouts/PlatformLayout.vue';

const modules = ref([]);
const loading = ref(true);
const activeTier = ref('all');

const tierFilters = [
    { key: 'all', label: 'Semua' },
    { key: 1, label: 'Core Operations' },
    { key: 2, label: 'Enterprise' },
    { key: 3, label: 'Smart Utility' },
];

const TIER_MAP = {
    1: { label: 'Core Operations', emoji: '🧱', icon: 'bg-emerald-50', badge: 'bg-emerald-100 text-emerald-700' },
    2: { label: 'Enterprise', emoji: '🏢', icon: 'bg-indigo-50', badge: 'bg-indigo-100 text-indigo-700' },
    3: { label: 'Smart Utility', emoji: '🛰️', icon: 'bg-purple-50', badge: 'bg-purple-100 text-purple-700' },
};

function tierStyle(tier) {
    return TIER_MAP[Number(tier)] || { label: 'Lainnya', emoji: '📦', icon: 'bg-slate-100', badge: 'bg-slate-100 text-slate-600' };
}

const filtered = computed(() => {
    if (activeTier.value === 'all') return modules.value;
    return modules.value.filter((m) => Number(m.tier) === activeTier.value);
});

function countFor(key) {
    if (key === 'all') return modules.value.length;
    return modules.value.filter((m) => Number(m.tier) === key).length;
}

// Modul yang menjadi prasyarat (dependencies) dari modul m
function deps(m) {
    const list = m.dependencies || [];
    return list.map((code) => modules.value.find((x) => x.code === code) || { code, name: code });
}

// Modul lain yang bergantung pada modul m
function requiredBy(m) {
    return modules.value.filter((x) => (x.dependencies || []).includes(m.code));
}

function formatPrice(v) {
    const n = Number(v || 0);
    if (!n) return 'Gratis';
    return 'Rp ' + n.toLocaleString('id-ID') + '/th';
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/platform/modules');
        modules.value = data.data ?? data;
    } catch (e) { /* interceptor 401 */ } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>
