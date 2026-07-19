<template>
    <AppLayout page-title="Marketplace Modul" page-subtitle="Harga tahunan dan status akses untuk organisasi Anda">
        <div v-if="loading" class="text-sm text-gray-500">Memuat katalog...</div>
        <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="module in modules" :key="module.code" class="rounded-xl border bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div><p class="font-semibold text-gray-900">{{ module.name }}</p><p class="text-xs font-medium text-blue-600">{{ module.code }}</p></div>
                    <span class="rounded-full px-2 py-1 text-xs" :class="module.entitled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">{{ module.entitled ? 'Aktif' : module.entitlement_status }}</span>
                </div>
                <p class="mt-3 min-h-10 text-sm text-gray-500">{{ module.description }}</p>
                <p class="mt-4 text-lg font-bold text-gray-900">{{ money(module.price_year) }}<span class="text-xs font-normal text-gray-400"> / tahun</span></p>
                <p v-if="!module.dependency_satisfied" class="mt-2 text-xs text-amber-700">Butuh: {{ module.missing_dependencies.join(', ') }}</p>
                <button class="mt-4 w-full rounded-lg bg-blue-700 px-3 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:bg-gray-300" :disabled="module.entitled || !module.dependency_satisfied || purchasing === module.code" @click="purchase(module)">
                    {{ purchasing === module.code ? 'Membuat pesanan...' : 'Ajukan Pembelian' }}
                </button>
            </article>
        </div>
        <div v-if="order" class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Pesanan {{ order.order_number }} dibuat dengan status <strong>{{ order.status }}</strong>. Modul belum aktif sampai pembayaran diverifikasi.
            <a v-if="order.payment_url" :href="order.payment_url" class="ml-2 font-semibold underline" rel="noopener noreferrer">Lanjutkan pembayaran</a>
        </div>
    </AppLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../api';
import AppLayout from '../../layouts/AppLayout.vue';

const loading = ref(true);
const modules = ref([]);
const purchasing = ref(null);
const order = ref(null);

async function load() {
    const response = await api.get('/marketplace/catalog');
    modules.value = response.data.data.modules;
    loading.value = false;
}

async function purchase(module) {
    purchasing.value = module.code;
    try {
        const response = await api.post('/marketplace/purchases', { items: [{ type: 'module', code: module.code }] }, { headers: { 'Idempotency-Key': crypto.randomUUID() } });
        order.value = response.data.data;
    } finally {
        purchasing.value = null;
    }
}

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
}

onMounted(load);
</script>
