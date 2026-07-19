<template>
    <div class="min-h-screen bg-white text-slate-700 antialiased overflow-x-hidden">
        <!-- ===== NAVBAR ===== -->
        <header :class="['fixed top-0 inset-x-0 z-50 transition-all duration-300',
            scrolled ? 'bg-white/90 backdrop-blur border-b border-sky-100 shadow-sm' : 'bg-transparent']">
            <nav class="max-w-7xl mx-auto px-5 lg:px-8 h-16 flex items-center justify-between">
                <a href="#top" class="flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-sky-400 to-blue-500 flex items-center justify-center text-white text-lg shadow-md shadow-sky-200">💧</span>
                    <span class="font-extrabold text-lg tracking-tight text-slate-800">PDAM<span class="text-sky-500">SaaS</span></span>
                </a>
                <div class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-600">
                    <a href="#tentang" class="hover:text-sky-600 transition">Tentang</a>
                    <a href="#keunggulan" class="hover:text-sky-600 transition">Keunggulan</a>
                    <a href="#modul" class="hover:text-sky-600 transition">Modul</a>
                    <a href="#alur" class="hover:text-sky-600 transition">Cara Kerja</a>
                </div>
                <button @click="goLogin"
                    class="px-5 py-2 rounded-full bg-gradient-to-r from-sky-500 to-blue-500 text-white text-sm font-semibold shadow-md shadow-sky-200 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    Masuk
                </button>
            </nav>
        </header>

        <!-- ===== HERO ===== -->
        <section id="top" class="relative pt-32 pb-24 lg:pt-40 lg:pb-32 overflow-hidden">
            <!-- background dekor -->
            <div class="absolute inset-0 bg-gradient-to-b from-sky-50 via-cyan-50/40 to-white"></div>
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-sky-200/40 rounded-full blur-3xl"></div>
            <div class="absolute top-40 -left-24 w-80 h-80 bg-cyan-200/40 rounded-full blur-3xl"></div>

            <div class="relative max-w-7xl mx-auto px-5 lg:px-8 grid lg:grid-cols-2 gap-12 items-center">
                <div class="text-center lg:text-left">
                    <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-sky-100 text-sky-700 text-xs font-semibold mb-6">
                        <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                        Platform SaaS Manajemen PDAM Modern
                    </span>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold leading-tight text-slate-900">
                        Kelola Air Bersih<br />
                        <span class="bg-gradient-to-r from-sky-500 to-blue-600 bg-clip-text text-transparent">Lebih Cerdas & Terpadu</span>
                    </h1>
                    <p class="mt-6 text-lg text-slate-600 max-w-xl mx-auto lg:mx-0 leading-relaxed">
                        Satu sistem untuk seluruh operasional PDAM — mulai dari pencatatan meter, billing, keuangan,
                        pengaduan pelanggan, hingga analitik cerdas. Modular, aman, dan siap tumbuh bersama PDAM Anda.
                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
                        <button @click="goLogin"
                            class="px-7 py-3.5 rounded-full bg-gradient-to-r from-sky-500 to-blue-500 text-white font-semibold shadow-lg shadow-sky-200 hover:-translate-y-0.5 transition-all">
                            Mulai Sekarang →
                        </button>
                        <a href="#modul"
                            class="px-7 py-3.5 rounded-full bg-white text-slate-700 font-semibold border border-slate-200 hover:border-sky-300 hover:text-sky-600 transition-all">
                            Lihat Modul
                        </a>
                    </div>
                    <div class="mt-8 flex items-center gap-6 justify-center lg:justify-start text-sm text-slate-500">
                        <span class="flex items-center gap-1.5"><span class="text-sky-500">✓</span> Multi-tenant</span>
                        <span class="flex items-center gap-1.5"><span class="text-sky-500">✓</span> Cloud &amp; aman</span>
                        <span class="flex items-center gap-1.5"><span class="text-sky-500">✓</span> Aplikasi mobile</span>
                    </div>
                </div>

                <!-- Hero card / mock dashboard -->
                <div class="relative">
                    <div class="relative bg-white rounded-3xl shadow-2xl shadow-sky-100 border border-sky-100 p-6 animate-float">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <p class="text-xs text-slate-400">Pendapatan Bulan Ini</p>
                                <p class="text-2xl font-extrabold text-slate-800">Rp 4,8 M</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 text-xs font-semibold">▲ 12,4%</span>
                        </div>
                        <!-- mini bar chart -->
                        <div class="flex items-end gap-2 h-32">
                            <div v-for="(h, i) in bars" :key="i"
                                class="flex-1 rounded-t-lg bg-gradient-to-t from-sky-400 to-cyan-300 transition-all"
                                :style="{ height: h + '%' }"></div>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-3">
                            <div class="rounded-xl bg-sky-50 p-3">
                                <p class="text-[11px] text-slate-400">Pelanggan</p>
                                <p class="font-bold text-slate-800">128.540</p>
                            </div>
                            <div class="rounded-xl bg-cyan-50 p-3">
                                <p class="text-[11px] text-slate-400">Meter Terbaca</p>
                                <p class="font-bold text-slate-800">98,6%</p>
                            </div>
                            <div class="rounded-xl bg-blue-50 p-3">
                                <p class="text-[11px] text-slate-400">NRW</p>
                                <p class="font-bold text-slate-800">18,2%</p>
                            </div>
                        </div>
                    </div>
                    <!-- floating chips -->
                    <div class="absolute -top-5 -left-5 bg-white rounded-2xl shadow-lg border border-sky-100 px-4 py-3 flex items-center gap-2 animate-float-slow">
                        <span class="text-xl">📊</span>
                        <div>
                            <p class="text-[10px] text-slate-400">Tagihan Terbit</p>
                            <p class="text-sm font-bold text-slate-800">Otomatis</p>
                        </div>
                    </div>
                    <div class="absolute -bottom-5 -right-4 bg-white rounded-2xl shadow-lg border border-sky-100 px-4 py-3 flex items-center gap-2 animate-float">
                        <span class="text-xl">📱</span>
                        <div>
                            <p class="text-[10px] text-slate-400">Petugas Lapangan</p>
                            <p class="text-sm font-bold text-slate-800">Realtime</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===== STATS BAR ===== -->
        <section class="relative -mt-6 mb-8">
            <div class="max-w-6xl mx-auto px-5 lg:px-8">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 bg-white rounded-3xl border border-sky-100 shadow-lg shadow-sky-50 p-6 lg:p-8">
                    <div v-for="s in stats" :key="s.label" class="text-center">
                        <p class="text-3xl lg:text-4xl font-extrabold bg-gradient-to-r from-sky-500 to-blue-600 bg-clip-text text-transparent">{{ s.value }}</p>
                        <p class="text-sm text-slate-500 mt-1">{{ s.label }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===== TENTANG ===== -->
        <section id="tentang" class="py-20 lg:py-28">
            <div class="max-w-7xl mx-auto px-5 lg:px-8 grid lg:grid-cols-2 gap-14 items-center">
                <div class="order-2 lg:order-1 grid grid-cols-2 gap-4">
                    <div v-for="(c, i) in aboutCards" :key="i"
                        :class="['rounded-2xl p-5 border', c.bg, i % 2 ? 'mt-6' : '']">
                        <span class="text-2xl">{{ c.icon }}</span>
                        <p class="mt-3 font-bold text-slate-800">{{ c.title }}</p>
                        <p class="text-sm text-slate-500 mt-1 leading-relaxed">{{ c.desc }}</p>
                    </div>
                </div>
                <div class="order-1 lg:order-2">
                    <span class="text-sky-600 font-semibold text-sm">TENTANG PLATFORM</span>
                    <h2 class="mt-3 text-3xl lg:text-4xl font-extrabold text-slate-900 leading-tight">
                        Apa itu PDAM SaaS?
                    </h2>
                    <p class="mt-5 text-slate-600 leading-relaxed">
                        PDAM SaaS adalah platform manajemen berbasis cloud yang dirancang khusus untuk
                        Perusahaan Daerah Air Minum. Kami menyatukan seluruh proses bisnis air bersih —
                        dari hulu ke hilir — dalam satu sistem yang saling terhubung.
                    </p>
                    <p class="mt-4 text-slate-600 leading-relaxed">
                        Dengan arsitektur <strong class="text-slate-800">multi-tenant</strong>, setiap PDAM
                        memiliki ruang datanya sendiri yang terisolasi dan aman. Anda cukup mengaktifkan
                        modul yang dibutuhkan, dan menambah modul lain seiring pertumbuhan tanpa mengganti sistem.
                    </p>
                    <ul class="mt-6 space-y-3">
                        <li v-for="p in aboutPoints" :key="p" class="flex items-start gap-3">
                            <span class="mt-0.5 w-5 h-5 rounded-full bg-sky-100 text-sky-600 flex items-center justify-center text-xs flex-shrink-0">✓</span>
                            <span class="text-slate-600">{{ p }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ===== KEUNGGULAN ===== -->
        <section id="keunggulan" class="py-20 lg:py-28 bg-gradient-to-b from-sky-50/60 to-white">
            <div class="max-w-7xl mx-auto px-5 lg:px-8">
                <div class="text-center max-w-2xl mx-auto">
                    <span class="text-sky-600 font-semibold text-sm">KENAPA MEMILIH KAMI</span>
                    <h2 class="mt-3 text-3xl lg:text-4xl font-extrabold text-slate-900">Dibangun untuk kebutuhan nyata PDAM</h2>
                    <p class="mt-4 text-slate-600">Bukan sekadar software akuntansi umum — setiap fitur dirancang dari alur kerja operasional PDAM sehari-hari.</p>
                </div>
                <div class="mt-14 grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="f in features" :key="f.title"
                        class="group bg-white rounded-2xl border border-slate-100 p-7 shadow-sm hover:shadow-xl hover:shadow-sky-100 hover:-translate-y-1 transition-all">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-sky-100 to-cyan-100 flex items-center justify-center text-2xl mb-5 group-hover:scale-110 transition-transform">
                            {{ f.icon }}
                        </div>
                        <h3 class="font-bold text-lg text-slate-800">{{ f.title }}</h3>
                        <p class="mt-2 text-sm text-slate-500 leading-relaxed">{{ f.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===== MODUL ===== -->
        <section id="modul" class="py-20 lg:py-28">
            <div class="max-w-7xl mx-auto px-5 lg:px-8">
                <div class="text-center max-w-2xl mx-auto">
                    <span class="text-sky-600 font-semibold text-sm">YANG KAMI TAWARKAN</span>
                    <h2 class="mt-3 text-3xl lg:text-4xl font-extrabold text-slate-900">Modul lengkap, aktifkan sesuai kebutuhan</h2>
                    <p class="mt-4 text-slate-600">Mulai dari paket dasar, lalu kembangkan ke modul enterprise dan smart utility. Bayar hanya untuk yang Anda pakai.</p>
                </div>

                <div class="mt-14 grid lg:grid-cols-3 gap-6">
                    <div v-for="(tier, idx) in tiers" :key="tier.name"
                        :class="['rounded-3xl border p-8 flex flex-col', tier.highlight ? 'border-sky-300 shadow-xl shadow-sky-100 bg-white relative' : 'border-slate-100 bg-white shadow-sm']">
                        <span v-if="tier.highlight" class="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1 rounded-full bg-gradient-to-r from-sky-500 to-blue-500 text-white text-xs font-semibold shadow">Paling Populer</span>
                        <div :class="['w-14 h-14 rounded-2xl flex items-center justify-center text-3xl mb-5', tier.iconBg]">{{ tier.icon }}</div>
                        <h3 class="font-extrabold text-xl text-slate-800">{{ tier.name }}</h3>
                        <p class="text-sm text-slate-500 mt-2 leading-relaxed">{{ tier.tagline }}</p>
                        <div class="mt-5 mb-5 border-t border-slate-100"></div>
                        <ul class="space-y-3 flex-1">
                            <li v-for="m in tier.modules" :key="m" class="flex items-start gap-2.5 text-sm">
                                <span :class="['mt-0.5 w-4 h-4 rounded-full flex items-center justify-center text-[10px] flex-shrink-0', tier.checkBg]">✓</span>
                                <span class="text-slate-600">{{ m }}</span>
                            </li>
                        </ul>
                        <button @click="goLogin"
                            :class="['mt-7 py-3 rounded-xl font-semibold text-sm transition-all',
                                tier.highlight ? 'bg-gradient-to-r from-sky-500 to-blue-500 text-white shadow-md shadow-sky-200 hover:-translate-y-0.5' : 'bg-slate-50 text-slate-700 hover:bg-sky-50 hover:text-sky-600']">
                            Pelajari Modul
                        </button>
                    </div>
                </div>

                <p class="text-center text-sm text-slate-400 mt-8">
                    🔗 Modul saling terintegrasi — beberapa modul memerlukan modul prasyarat aktif lebih dulu (mis. Meter Analytics butuh Baca Meter Digital).
                </p>
            </div>
        </section>

        <!-- ===== ALUR / CARA KERJA ===== -->
        <section id="alur" class="py-20 lg:py-28 bg-gradient-to-b from-white to-sky-50/60">
            <div class="max-w-7xl mx-auto px-5 lg:px-8">
                <div class="text-center max-w-2xl mx-auto">
                    <span class="text-sky-600 font-semibold text-sm">CARA KERJA</span>
                    <h2 class="mt-3 text-3xl lg:text-4xl font-extrabold text-slate-900">Empat langkah untuk mulai</h2>
                </div>
                <div class="mt-14 grid md:grid-cols-4 gap-6">
                    <div v-for="(step, i) in steps" :key="i" class="relative text-center">
                        <div class="w-16 h-16 mx-auto rounded-2xl bg-white border border-sky-100 shadow-md shadow-sky-50 flex items-center justify-center text-2xl">{{ step.icon }}</div>
                        <span class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-3 w-6 h-6 rounded-full bg-sky-500 text-white text-xs font-bold flex items-center justify-center">{{ i + 1 }}</span>
                        <h3 class="mt-5 font-bold text-slate-800">{{ step.title }}</h3>
                        <p class="mt-2 text-sm text-slate-500 leading-relaxed">{{ step.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===== CTA ===== -->
        <section class="py-16 lg:py-20">
            <div class="max-w-6xl mx-auto px-5 lg:px-8">
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-sky-500 to-blue-600 px-8 py-14 lg:px-16 text-center shadow-2xl shadow-sky-200">
                    <div class="absolute -top-16 -right-10 w-72 h-72 bg-white/10 rounded-full blur-2xl"></div>
                    <div class="absolute -bottom-20 -left-10 w-72 h-72 bg-cyan-300/20 rounded-full blur-2xl"></div>
                    <h2 class="relative text-3xl lg:text-4xl font-extrabold text-white">Siap memodernisasi PDAM Anda?</h2>
                    <p class="relative mt-4 text-sky-50 max-w-xl mx-auto">Masuk ke sistem dan mulai kelola operasional air bersih dalam satu platform terpadu.</p>
                    <button @click="goLogin"
                        class="relative mt-8 px-8 py-3.5 rounded-full bg-white text-sky-600 font-bold shadow-lg hover:-translate-y-0.5 transition-all">
                        Masuk ke Sistem →
                    </button>
                </div>
            </div>
        </section>

        <!-- ===== FOOTER ===== -->
        <footer class="border-t border-slate-100 py-10">
            <div class="max-w-7xl mx-auto px-5 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-sky-400 to-blue-500 flex items-center justify-center text-white">💧</span>
                    <span class="font-extrabold text-slate-800">PDAM<span class="text-sky-500">SaaS</span></span>
                </div>
                <p class="text-sm text-slate-400">© {{ year }} PDAM SaaS. Platform manajemen air bersih terpadu.</p>
                <div class="flex gap-5 text-sm text-slate-500">
                    <a href="#tentang" class="hover:text-sky-600">Tentang</a>
                    <a href="#modul" class="hover:text-sky-600">Modul</a>
                    <button @click="goLogin" class="hover:text-sky-600">Masuk</button>
                </div>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const scrolled = ref(false);
const year = new Date().getFullYear();
const bars = [45, 62, 50, 78, 68, 90, 72];

const stats = [
    { value: '27+', label: 'Modul Terintegrasi' },
    { value: '3', label: 'Tingkatan Paket' },
    { value: '99,9%', label: 'Uptime Layanan' },
    { value: '24/7', label: 'Akses Cloud' },
];

const aboutCards = [
    { icon: '🏢', title: 'Multi-Tenant', desc: 'Setiap PDAM punya data terisolasi & aman.', bg: 'bg-sky-50 border-sky-100' },
    { icon: '🔒', title: 'Keamanan Data', desc: 'Enkripsi & kontrol akses berbasis peran.', bg: 'bg-cyan-50 border-cyan-100' },
    { icon: '📱', title: 'Aplikasi Mobile', desc: 'Petugas & pelanggan terhubung realtime.', bg: 'bg-blue-50 border-blue-100' },
    { icon: '⚡', title: 'Modular', desc: 'Aktifkan modul sesuai kebutuhan PDAM.', bg: 'bg-sky-50 border-sky-100' },
];

const aboutPoints = [
    'Menggantikan proses manual & aplikasi terpisah dengan satu sistem terpadu.',
    'Skalabel dari PDAM kecil hingga besar tanpa migrasi sistem.',
    'Laporan keuangan & operasional otomatis dan real-time.',
];

const features = [
    { icon: '💳', title: 'Billing & Pembayaran', desc: 'Terbitkan tagihan otomatis dari pembacaan meter, kelola pembayaran, denda, dan angsuran dengan akurat.' },
    { icon: '📊', title: 'Keuangan Akurat', desc: 'Akuntansi setara Accurate: jurnal, buku besar, neraca, laba rugi, dan arus kas dalam satu sistem.' },
    { icon: '📷', title: 'Baca Meter Digital', desc: 'Petugas mencatat angka meter dengan foto bukti & validasi GPS langsung dari lapangan.' },
    { icon: '🎧', title: 'Pengaduan & CRM', desc: 'Kelola keluhan pelanggan dengan tiket, SLA, dan riwayat interaksi yang tercatat rapi.' },
    { icon: '🗺️', title: 'GIS & Distribusi', desc: 'Pemetaan jaringan pipa, tekanan air, dan District Metered Area untuk kendali distribusi.' },
    { icon: '🤖', title: 'Analitik & AI', desc: 'Prediksi pemakaian, deteksi kebocoran & kecurangan meter, hingga chatbot pelanggan.' },
];

const tiers = [
    {
        name: 'Core Operations', icon: '🧱', iconBg: 'bg-emerald-50', checkBg: 'bg-emerald-100 text-emerald-600',
        tagline: 'Fondasi operasional harian PDAM. Wajib dimiliki setiap organisasi.',
        modules: ['Paket Dasar (pelanggan, tarif, billing, buku besar)', 'Baca Meter Digital + Route', 'Survey & Pemasangan', 'Gudang & Inventory', 'Pengaduan & CRM', 'Portal & Mobile Pelanggan'],
        highlight: false,
    },
    {
        name: 'Enterprise', icon: '🏢', iconBg: 'bg-indigo-50', checkBg: 'bg-indigo-100 text-indigo-600',
        tagline: 'Untuk PDAM yang berkembang dengan proses lebih kompleks.',
        modules: ['Keuangan Advance (Accurate-like)', 'Field Service + Work Order', 'Procurement & Vendor', 'GIS Water Network', 'Business Intelligence', 'HR & Document Management'],
        highlight: true,
    },
    {
        name: 'Smart Utility', icon: '🛰️', iconBg: 'bg-purple-50', checkBg: 'bg-purple-100 text-purple-600',
        tagline: 'Teknologi terdepan untuk PDAM modern & efisien.',
        modules: ['Smart Meter / IoT (AMR/AMI)', 'Water Production (SCADA IPA)', 'Distribution (DMA/pressure)', 'Non-Revenue Water Management', 'AI/ML (prediksi, chatbot, fraud)'],
        highlight: false,
    },
];

const steps = [
    { icon: '📝', title: 'Registrasi PDAM', desc: 'PDAM didaftarkan sebagai tenant dengan ruang data sendiri.' },
    { icon: '🧩', title: 'Pilih Modul', desc: 'Aktifkan modul yang dibutuhkan, mulai dari paket dasar.' },
    { icon: '👥', title: 'Kelola Tim', desc: 'Atur pengguna & hak akses berdasarkan peran.' },
    { icon: '🚀', title: 'Operasional', desc: 'Mulai kelola pelanggan, meter, billing & keuangan.' },
];

function goLogin() {
    router.push('/login');
}

function onScroll() {
    scrolled.value = window.scrollY > 20;
}

onMounted(() => window.addEventListener('scroll', onScroll));
onUnmounted(() => window.removeEventListener('scroll', onScroll));
</script>

<style scoped>
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
@keyframes float-slow {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-14px); }
}
.animate-float { animation: float 5s ease-in-out infinite; }
.animate-float-slow { animation: float-slow 7s ease-in-out infinite; }
html { scroll-behavior: smooth; }
</style>
