// Katalog 27 modul (sinkron dengan database/seeders/ModuleSeeder.php).
// `route`  : tujuan halaman frontend (halaman khusus bila ada, atau /modules/<code>).
// `endpoint`: path API list yang dipakai halaman generik /modules/<code> bila tidak ada halaman khusus.
// `kind`   : 'page' (halaman khusus) | 'list' (tabel generik) | 'kpi' (dashboard KPI) | 'info' (hanya katalog).

export const MODULE_CATALOG = [
    { code: 'CORE', name: 'Paket Dasar', tier: 1, route: '/dashboard', endpoint: '', kind: 'page' },
    { code: 'WH', name: 'Gudang & Inventory', tier: 1, route: '/modules/WH', endpoint: '/purchase-orders', kind: 'list' },
    { code: 'MTR', name: 'Baca Meter Digital + Route', tier: 1, route: '/meter-routes', endpoint: '', kind: 'page' },
    { code: 'SRV', name: 'Survey & Pemasangan', tier: 1, route: '/prospects', endpoint: '', kind: 'page' },
    { code: 'FIN+', name: 'Keuangan Advance', tier: 1, route: '/dashboard/finance', endpoint: '', kind: 'page' },
    { code: 'CRM', name: 'Pengaduan & CRM', tier: 1, route: '/complaints', endpoint: '', kind: 'page' },
    { code: 'AST', name: 'Aset Tetap & Penyusutan', tier: 1, route: '/assets', endpoint: '', kind: 'page' },
    { code: 'ZONE', name: 'Multi-Wilayah / Cabang', tier: 1, route: '/zones', endpoint: '', kind: 'page' },
    { code: 'APP', name: 'Portal & Mobile Pelanggan', tier: 1, route: '/modules/APP', endpoint: '/chats', kind: 'list' },
    { code: 'C360', name: 'Customer 360 View', tier: 1, route: '/customers', endpoint: '', kind: 'page' },
    { code: 'BILL+', name: 'Advanced Billing', tier: 1, route: '/modules/BILL+', endpoint: '/bill-adjustments', kind: 'list' },
    { code: 'METX', name: 'Meter Analytics', tier: 1, route: '/modules/METX', endpoint: '/meter-anomalies', kind: 'list' },
    { code: 'FSM', name: 'Field Service + Work Order', tier: 2, route: '/modules/FSM', endpoint: '/work-orders', kind: 'list' },
    { code: 'PROC', name: 'Procurement, Tender & Vendor', tier: 2, route: '/tenders', endpoint: '', kind: 'page' },
    { code: 'MNT', name: 'Maintenance (preventive)', tier: 2, route: '/modules/MNT', endpoint: '/maintenance/records', kind: 'list' },
    { code: 'HR', name: 'HR Management', tier: 2, route: '/employees', endpoint: '', kind: 'page' },
    { code: 'DMS', name: 'Document Management System', tier: 2, route: '/modules/DMS', endpoint: '/documents', kind: 'list' },
    { code: 'GIS', name: 'GIS Water Network', tier: 2, route: '/gis', endpoint: '', kind: 'page' },
    { code: 'CC', name: 'Call Center', tier: 2, route: '/call-center', endpoint: '', kind: 'page' },
    { code: 'BI', name: 'BI + Report Builder', tier: 2, route: '/scheduled-reports', endpoint: '', kind: 'page' },
    { code: 'INT', name: 'Integration Platform', tier: 2, route: '/modules/INT', endpoint: '/integrations', kind: 'list' },
    { code: 'CHEM', name: 'Chemical Management (IPA)', tier: 2, route: '/modules/CHEM', endpoint: '/chem/chemicals', kind: 'list' },
    { code: 'IOT', name: 'Smart Meter / IoT (AMR)', tier: 3, route: '/modules/IOT', endpoint: '/iot/dashboard', kind: 'kpi' },
    { code: 'PROD', name: 'Water Production (SCADA)', tier: 3, route: '/modules/PROD', endpoint: '/production/dashboard', kind: 'kpi' },
    { code: 'DIST', name: 'Distribution (DMA/pressure)', tier: 3, route: '/modules/DIST', endpoint: '/distribution/dma-dashboard', kind: 'kpi' },
    { code: 'NRW', name: 'Non-Revenue Water', tier: 3, route: '/modules/NRW', endpoint: '/nrw/dashboard', kind: 'kpi' },
    { code: 'AI', name: 'AI / ML (prediksi, chatbot)', tier: 3, route: '/modules/AI', endpoint: '', kind: 'info' },
];

export const TIER_LABELS = { 1: 'Modul Inti (Tier 1)', 2: 'Enterprise (Tier 2)', 3: 'Smart Utility (Tier 3)' };

export function findModule(code) {
    return MODULE_CATALOG.find((m) => m.code === code);
}
