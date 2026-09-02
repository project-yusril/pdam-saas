// Konfigurasi sidebar terpusat — dikelompokkan per DOMAIN BISNIS (bukan tier).
// Setiap `module` pada item anak menentukan entitlement yang harus aktif agar
// submenu tampil (mengikuti `active_modules` tenant). Item tanpa `module` selalu tampil.

import { BUSINESS_FLOWS } from './flows';

export const SIDEBAR = [
    { to: '/dashboard', label: 'Dashboard', icon: 'pi-home', module: 'CORE' },
    {
        to: '/customers-group', label: 'Pelanggan & Layanan', icon: 'pi-users',
        children: [
            { to: '/customers', label: 'Data Pelanggan', icon: 'pi-users', module: 'CORE' },
            { to: '/prospects', label: 'Pemasangan Baru', icon: 'pi-user-plus', module: 'SRV' },
            { to: '/complaints', label: 'Pengaduan', icon: 'pi-comments', module: 'CRM' },
            { to: '/call-center', label: 'Call Center', icon: 'pi-phone', module: 'CC' },
            { to: '/modules/APP', label: 'Portal & Mobile', icon: 'pi-mobile', module: 'APP' },
        ],
    },
    {
        to: '/master-group', label: 'Master Data', icon: 'pi-database',
        children: [
            { to: '/address', label: 'Master Alamat', icon: 'pi-sitemap', module: 'CORE' },
            { to: '/zones', label: 'Wilayah', icon: 'pi-map-marker', module: 'ZONE' },
            { to: '/meter-routes', label: 'Rute Baca Meter', icon: 'pi-directions', module: 'MTR' },
            { to: '/streets', label: 'Jalan', icon: 'pi-map', module: 'CORE' },
            { to: '/tariffs', label: 'Golongan Tarif', icon: 'pi-tags', module: 'CORE' },
        ],
    },
    {
        to: '/finance', label: 'Keuangan', icon: 'pi-chart-bar',
        children: [
            { to: '/dashboard/finance', label: 'Ringkasan Keuangan', icon: 'pi-chart-line', module: 'FIN+' },
            { to: '/finance/general-ledger', label: 'Buku Jurnal', icon: 'pi-book', module: 'FIN+' },
            { to: '/finance/trial-balance', label: 'Neraca Saldo', icon: 'pi-table', module: 'FIN+' },
            { to: '/finance/income-statement', label: 'Laba Rugi', icon: 'pi-chart-line', module: 'FIN+' },
            { to: '/finance/balance-sheet', label: 'Neraca', icon: 'pi-building', module: 'FIN+' },
            { to: '/finance/cash-flow', label: 'Arus Kas', icon: 'pi-wallet', module: 'FIN+' },
            { to: '/modules/BILL+', label: 'Advanced Billing', icon: 'pi-credit-card', module: 'BILL+' },
        ],
    },
    {
        to: '/meter-group', label: 'Baca Meter & Metering', icon: 'pi-compass',
        children: [
            { to: '/meter-routes/dashboard', label: 'Dashboard Rute', icon: 'pi-chart-bar', module: 'MTR' },
            { to: '/meter-reading-report', label: 'Laporan Baca Meter', icon: 'pi-file-excel', module: 'MTR' },
            { to: '/modules/METX', label: 'Meter Analytics', icon: 'pi-chart-line', module: 'METX' },
            { to: '/modules/IOT', label: 'Smart Meter / IoT', icon: 'pi-wifi', module: 'IOT' },
        ],
    },
    {
        to: '/warehouse-group', label: 'Gudang & Aset', icon: 'pi-box',
        children: [
            { to: '/modules/WH', label: 'Gudang & Inventory', icon: 'pi-box', module: 'WH' },
            { to: '/assets', label: 'Aset Tetap', icon: 'pi-box', module: 'AST' },
            { to: '/tenders', label: 'Procurement & Tender', icon: 'pi-briefcase', module: 'PROC' },
            { to: '/modules/MNT', label: 'Maintenance', icon: 'pi-cog', module: 'MNT' },
        ],
    },
    {
        to: '/field-group', label: 'Teknis Lapangan & GIS', icon: 'pi-bolt',
        children: [
            { to: '/modules/FSM', label: 'Field Service / Work Order', icon: 'pi-briefcase', module: 'FSM' },
            { to: '/gis', label: 'GIS Water Network', icon: 'pi-globe', module: 'GIS' },
        ],
    },
    {
        to: '/people-group', label: 'SDM & Dokumen', icon: 'pi-users',
        children: [
            { to: '/employees', label: 'Data Pegawai', icon: 'pi-users', module: 'HR' },
            { to: '/modules/DMS', label: 'Document Management', icon: 'pi-file', module: 'DMS' },
        ],
    },
    {
        to: '/production-group', label: 'Produksi Air', icon: 'pi-flag',
        children: [
            { to: '/modules/CHEM', label: 'Chemical (IPA)', icon: 'pi-flag', module: 'CHEM' },
            { to: '/modules/PROD', label: 'Water Production', icon: 'pi-cog', module: 'PROD' },
            { to: '/modules/DIST', label: 'Distribution DMA', icon: 'pi-map', module: 'DIST' },
            { to: '/modules/NRW', label: 'Non-Revenue Water', icon: 'pi-exclamation-circle', module: 'NRW' },
        ],
    },
    {
        to: '/integration-group', label: 'Integrasi & Analitik', icon: 'pi-chart-bar',
        children: [
            { to: '/modules/INT', label: 'Integration Platform', icon: 'pi-link', module: 'INT' },
            { to: '/scheduled-reports', label: 'BI + Report Builder', icon: 'pi-calendar', module: 'BI' },
            { to: '/modules/AI', label: 'AI / ML', icon: 'pi-cpu', module: 'AI' },
        ],
    },
    {
        to: '/flows-group', label: 'Alur Bisnis', icon: 'pi-sitemap',
        children: [
            { to: '/flows', label: 'Semua Alur', icon: 'pi-th-large' },
            ...BUSINESS_FLOWS.map((f) => ({ to: '/flows/' + f.key, label: f.label, icon: f.icon })),
        ],
    },
    { to: '/marketplace', label: 'Marketplace', icon: 'pi-shopping-cart' },
];

// Filter sidebar berdasarkan modul aktif tenant.
export function buildSidebar(activeModules) {
    const active = new Set(activeModules ?? []);
    return SIDEBAR.map((item) => {
        if (!item.children) {
            return !item.module || active.has(item.module) ? item : null;
        }
        const children = item.children.filter((c) => !c.module || active.has(c.module));
        return children.length ? { ...item, children } : null;
    }).filter(Boolean);
}
