import { createRouter, createWebHashHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

export const routes = [
    { path: '/', name: 'landing', component: () => import('../views/LandingView.vue'), meta: { public: true } },
    { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { guest: true } },
    { path: '/dashboard', name: 'dashboard', component: () => import('../views/DashboardView.vue'), meta: { requiresAuth: true } },
    { path: '/customers', name: 'customers', component: () => import('../views/customers/CustomerListView.vue'), meta: { requiresAuth: true } },
    { path: '/tariffs', name: 'tariffs', component: () => import('../views/tariffs/TariffListView.vue'), meta: { requiresAuth: true } },
    { path: '/streets', name: 'streets', component: () => import('../views/streets/StreetListView.vue'), meta: { requiresAuth: true } },
    { path: '/address', name: 'address', component: () => import('../views/address/AddressManagementView.vue'), meta: { requiresAuth: true } },
    { path: '/prospects', name: 'prospects', component: () => import('../views/prospects/ProspectListView.vue'), meta: { requiresAuth: true } },
    { path: '/zones', name: 'zones', component: () => import('../views/zones/ZoneListView.vue'), meta: { requiresAuth: true } },
    { path: '/zones/:id', name: 'zone-detail', component: () => import('../views/zones/ZoneDetailView.vue'), meta: { requiresAuth: true } },
    { path: '/meter-routes', name: 'meter-routes', component: () => import('../views/meter/MeterRouteListView.vue'), meta: { requiresAuth: true } },
    { path: '/meter-routes/dashboard', name: 'meter-routes-dashboard', component: () => import('../views/meter/MeterRouteDashboardView.vue'), meta: { requiresAuth: true } },
    { path: '/meter-routes/:id', name: 'meter-route-detail', component: () => import('../views/meter/MeterRouteDetailView.vue'), meta: { requiresAuth: true } },
    { path: '/complaints', name: 'complaints', component: () => import('../views/complaints/ComplaintListView.vue'), meta: { requiresAuth: true } },
    { path: '/assets', name: 'assets', component: () => import('../views/assets/AssetListView.vue'), meta: { requiresAuth: true } },
    { path: '/gis', name: 'gis', component: () => import('../views/gis/GisMapView.vue'), meta: { requiresAuth: true } },
    { path: '/employees', name: 'employees', component: () => import('../views/hr/EmployeeListView.vue'), meta: { requiresAuth: true } },
    { path: '/employee-self-service', name: 'employee-self-service', component: () => import('../views/hr/EmployeeSelfService.vue'), meta: { requiresAuth: true } },
    { path: '/call-center', name: 'call-center', component: () => import('../views/cc/CallCenterView.vue'), meta: { requiresAuth: true } },
    { path: '/tenders', name: 'tenders', component: () => import('../views/procurement/TenderListView.vue'), meta: { requiresAuth: true } },
    { path: '/marketplace', name: 'marketplace', component: () => import('../views/marketplace/MarketplaceView.vue'), meta: { requiresAuth: true } },
    { path: '/scheduled-reports', name: 'scheduled-reports', component: () => import('../views/bi/ScheduledReportsView.vue'), meta: { requiresAuth: true } },
    { path: '/dashboard/director', name: 'dashboard-director', component: () => import('../views/roles/DirectorDashboard.vue'), meta: { requiresAuth: true } },
    { path: '/dashboard/finance', name: 'dashboard-finance', component: () => import('../views/roles/FinanceDashboard.vue'), meta: { requiresAuth: true } },
    { path: '/finance/general-ledger', name: 'finance-general-ledger', component: () => import('../views/finance/GeneralLedgerView.vue'), meta: { requiresAuth: true } },
    { path: '/finance/trial-balance', name: 'finance-trial-balance', component: () => import('../views/finance/TrialBalanceView.vue'), meta: { requiresAuth: true } },
    { path: '/finance/income-statement', name: 'finance-income-statement', component: () => import('../views/finance/IncomeStatementView.vue'), meta: { requiresAuth: true } },
    { path: '/finance/balance-sheet', name: 'finance-balance-sheet', component: () => import('../views/finance/BalanceSheetView.vue'), meta: { requiresAuth: true } },
    { path: '/finance/cash-flow', name: 'finance-cash-flow', component: () => import('../views/finance/CashFlowView.vue'), meta: { requiresAuth: true } },
    { path: '/modules/:code', name: 'module-index', component: () => import('../views/modules/ModuleIndexView.vue'), meta: { requiresAuth: true } },
    { path: '/flows', name: 'flow-index', component: () => import('../views/flows/FlowIndexView.vue'), meta: { requiresAuth: true } },
    { path: '/flows/:key', name: 'flow-show', component: () => import('../views/flows/FlowView.vue'), meta: { requiresAuth: true } },
    { path: '/dashboard/technical', name: 'dashboard-technical', component: () => import('../views/roles/TechnicalDashboard.vue'), meta: { requiresAuth: true } },
    { path: '/dashboard/warehouse', name: 'dashboard-warehouse', component: () => import('../views/roles/WarehouseDashboard.vue'), meta: { requiresAuth: true } },
    { path: '/platform', name: 'platform', component: () => import('../views/platform/PlatformDashboardView.vue'), meta: { requiresAuth: true, platform: true } },
    { path: '/platform/tenants', name: 'platform-tenants', component: () => import('../views/platform/TenantListView.vue'), meta: { requiresAuth: true, platform: true } },
    { path: '/platform/tenants/:id', name: 'platform-tenant-detail', component: () => import('../views/platform/TenantDetailView.vue'), meta: { requiresAuth: true, platform: true } },
    { path: '/platform/modules', name: 'platform-modules', component: () => import('../views/platform/ModuleCatalogView.vue'), meta: { requiresAuth: true, platform: true } },
    { path: '/:pathMatch(.*)*', redirect: '/' },

];


const router = createRouter({
    history: createWebHashHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    await auth.initialize();

    // Belum login → paksa ke halaman login
    if (to.meta.requiresAuth && !auth.isAuthenticated) return '/login';

    // Sudah login tapi buka halaman guest (login) → arahkan sesuai peran
    if (to.meta.guest && auth.isAuthenticated) return auth.isPlatform ? '/platform' : '/dashboard';

    // Super-admin hanya boleh di area platform
    if (auth.isPlatform && to.meta.requiresAuth && !to.meta.platform) return '/platform';

    // User tenant tidak boleh masuk area platform
    if (auth.isAuthenticated && !auth.isPlatform && to.meta.platform) return '/dashboard';
});


export default router;
