<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\MfaController;
use App\Http\Controllers\Api\MobileDropdownController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\Platform\MarketplaceController;
use App\Http\Controllers\Api\Platform\ModuleCatalogController;
use App\Http\Controllers\Api\Platform\PlatformAuthController;
use App\Http\Controllers\Api\Platform\TenantController;
use App\Http\Controllers\Api\Platform\TenantModuleController;
use App\Http\Controllers\Api\Tenant\AccountingReportController;
use App\Http\Controllers\Api\Tenant\AddressController;
use App\Http\Controllers\Api\Tenant\AssetCategoryController;
use App\Http\Controllers\Api\Tenant\AssetOpnameController;
use App\Http\Controllers\Api\Tenant\AttendanceController;
use App\Http\Controllers\Api\Tenant\BiController;
use App\Http\Controllers\Api\Tenant\BillAdjustmentController;
use App\Http\Controllers\Api\Tenant\BillingController;
use App\Http\Controllers\Api\Tenant\BillRecController;
use App\Http\Controllers\Api\Tenant\CallCenterController;
use App\Http\Controllers\Api\Tenant\ChatController;
use App\Http\Controllers\Api\Tenant\ChemController;
use App\Http\Controllers\Api\Tenant\ComplaintController;
use App\Http\Controllers\Api\Tenant\CustomerController;
use App\Http\Controllers\Api\Tenant\CustomerLifecycleController;
use App\Http\Controllers\Api\Tenant\CustomerPortalController;
use App\Http\Controllers\Api\Tenant\CustomerViewController;
use App\Http\Controllers\Api\Tenant\DashboardController;
use App\Http\Controllers\Api\Tenant\DataPrivacyController;
use App\Http\Controllers\Api\Tenant\DistController;
use App\Http\Controllers\Api\Tenant\DocumentController;
use App\Http\Controllers\Api\Tenant\ExportController;
use App\Http\Controllers\Api\Tenant\FinAdvanceController;
use App\Http\Controllers\Api\Tenant\FinController;
use App\Http\Controllers\Api\Tenant\FixedAssetController;
use App\Http\Controllers\Api\Tenant\GisController;
use App\Http\Controllers\Api\Tenant\GisEditorController;
use App\Http\Controllers\Api\Tenant\HrAdvanceController;
use App\Http\Controllers\Api\Tenant\HrEmployeeController;
use App\Http\Controllers\Api\Tenant\HrTrainingController;
use App\Http\Controllers\Api\Tenant\InstallationController;
use App\Http\Controllers\Api\Tenant\IntegrationController;
use App\Http\Controllers\Api\Tenant\InventoryController;
use App\Http\Controllers\Api\Tenant\IotController;
use App\Http\Controllers\Api\Tenant\MaintenanceController;
use App\Http\Controllers\Api\Tenant\MarketplaceController as TenantMarketplaceController;
use App\Http\Controllers\Api\Tenant\MeterAnomalyController;
use App\Http\Controllers\Api\Tenant\MeterController;
use App\Http\Controllers\Api\Tenant\MeterReadingController;
use App\Http\Controllers\Api\Tenant\MeterRouteController;
use App\Http\Controllers\Api\Tenant\NotificationController;
use App\Http\Controllers\Api\Tenant\NrwController;
use App\Http\Controllers\Api\Tenant\PaymentController;
use App\Http\Controllers\Api\Tenant\PayrollController;
use App\Http\Controllers\Api\Tenant\ProductionController;
use App\Http\Controllers\Api\Tenant\ProspectController;
use App\Http\Controllers\Api\Tenant\PurchaseOrderController;
use App\Http\Controllers\Api\Tenant\RoleController;
use App\Http\Controllers\Api\Tenant\ScheduledReportController;
use App\Http\Controllers\Api\Tenant\StockTransferController;
use App\Http\Controllers\Api\Tenant\SyncController;
use App\Http\Controllers\Api\Tenant\TariffController;
use App\Http\Controllers\Api\Tenant\TenderController;
use App\Http\Controllers\Api\Tenant\UserController;
use App\Http\Controllers\Api\Tenant\VendorController;
use App\Http\Controllers\Api\Tenant\WarehouseController;
use App\Http\Controllers\Api\Tenant\WorkOrderController;
use App\Http\Controllers\Api\Tenant\ZoneController;
use App\Http\Controllers\Api\Tenant\ZoneDashboardController;
use App\Http\Controllers\Api\XenditWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes v1 — PDAM SaaS
|--------------------------------------------------------------------------
| Prefix global `api/v1` diset di bootstrap/app.php.
| Konvensi response: App\Support\ApiResponse (success/error standar).
*/

// ── PLATFORM (Super-Admin) ────────────────────────────────────────────
Route::prefix('platform')->group(function () {
    Route::post('login', [PlatformAuthController::class, 'login'])
        ->middleware('throttle:5,1'); // anti brute force: 5/menit per IP

    Route::middleware(['auth:sanctum', 'ability:platform'])->group(function () {
        Route::post('logout', [PlatformAuthController::class, 'logout']);

        // Katalog modul (global) + kelola langganan per tenant
        Route::get('modules', [ModuleCatalogController::class, 'index']);

        Route::get('tenants', [TenantController::class, 'index']);
        Route::post('tenants', [TenantController::class, 'store']);
        Route::get('tenants/{tenant}', [TenantController::class, 'show']);
        Route::post('tenants/{tenant}/toggle-status', [TenantController::class, 'toggleStatus']);

        // Marketplace: lock/unlock modul untuk tenant tertentu
        Route::get('tenants/{tenant}/modules', [TenantModuleController::class, 'index']);
        Route::post('tenants/{tenant}/modules/{moduleCode}/activate', [TenantModuleController::class, 'activate']);
        Route::post('tenants/{tenant}/modules/{moduleCode}/lock', [TenantModuleController::class, 'lock']);

        // Fase 28: Marketplace, Promo, Bundle, Dashboard SaaS
        Route::get('marketplace/catalog', [MarketplaceController::class, 'catalog']);
        Route::post('marketplace/purchase', [MarketplaceController::class, 'purchase']);
        Route::get('marketplace/promos', [MarketplaceController::class, 'promos']);
        Route::post('marketplace/promos', [MarketplaceController::class, 'createPromo']);
        Route::get('marketplace/dashboard', [MarketplaceController::class, 'superAdminDashboard']);
        Route::post('marketplace/prices', [MarketplaceController::class, 'managePrice']);
        Route::get('marketplace/bundles', [MarketplaceController::class, 'bundles']);
        Route::post('marketplace/bundles', [MarketplaceController::class, 'createBundle']);
    });
});

// ── WEBHOOK PUBLIK (Midtrans) ─────────────────────────────────────────
// Tanpa auth Sanctum; diverifikasi via signature_key di controller.
Route::post('webhooks/midtrans', [PaymentWebhookController::class, 'handle']);

// ── WEBHOOK PUBLIK (Xendit — provider kedua, PRD §23) ───────────────
// Tanpa auth; diverifikasi via x-callback-token (shared secret).
Route::post('webhooks/xendit', [XenditWebhookController::class, 'handle']);

// ── MOBILE DROPDOWNS (no tenant context needed) ──────────────────────
// Ringan, tanpa paginasi — dipakai form daftar pelanggan mobile.
Route::get('mobile/provinces', [MobileDropdownController::class, 'provinces']);
Route::get('mobile/cities', [MobileDropdownController::class, 'cities']);
Route::get('mobile/districts', [MobileDropdownController::class, 'districts']);
Route::get('mobile/villages', [MobileDropdownController::class, 'villages']);
Route::get('mobile/streets', [MobileDropdownController::class, 'streets']);

// ── TENANT (User PDAM) ────────────────────────────────────────────────
Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');
Route::get('session', [AuthController::class, 'session'])
    ->middleware('auth:sanctum');
Route::post('refresh-token', [AuthController::class, 'refreshToken'])
    ->middleware(['auth:sanctum', 'tenant']);

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::put('profile/update', [AuthController::class, 'updateProfile']);
    Route::put('change-password', [AuthController::class, 'changePassword']);
    Route::get('marketplace/catalog', [TenantMarketplaceController::class, 'catalog']);
    Route::post('marketplace/purchases', [TenantMarketplaceController::class, 'purchase']);
    Route::post('fcm-token', [AuthController::class, 'registerFcmToken']);

    // ── File privat — signed URL ──────────────────────────────────────
    Route::post('files/upload', [FileController::class, 'upload']);
    Route::post('files/signed-url', [FileController::class, 'signedUrl']);

    // ── Master Alamat Berjenjang (CRUD + dropdown) ────────────────────
    Route::get('address/provinces', [AddressController::class, 'provinces']);
    Route::get('address/cities', [AddressController::class, 'cities']);
    Route::get('address/districts', [AddressController::class, 'districts']);
    Route::get('address/villages', [AddressController::class, 'villages']);
    Route::get('address/streets', [AddressController::class, 'streets']);
    Route::post('address/streets', [AddressController::class, 'storeStreet']);
    Route::put('address/streets/{street}', [AddressController::class, 'updateStreet']);

    // ── CRUD master alamat berjenjang (global, dipakai semua PDAM) ──────
    Route::post('address/provinces', [AddressController::class, 'storeProvince']);
    Route::put('address/provinces/{province}', [AddressController::class, 'updateProvince']);
    Route::post('address/cities', [AddressController::class, 'storeCity']);
    Route::put('address/cities/{city}', [AddressController::class, 'updateCity']);
    Route::post('address/districts', [AddressController::class, 'storeDistrict']);
    Route::put('address/districts/{district}', [AddressController::class, 'updateDistrict']);
    Route::post('address/villages', [AddressController::class, 'storeVillage']);
    Route::put('address/villages/{village}', [AddressController::class, 'updateVillage']);
    Route::delete('address/provinces/{province}', [AddressController::class, 'destroyProvince']);
    Route::delete('address/cities/{city}', [AddressController::class, 'destroyCity']);
    Route::delete('address/districts/{district}', [AddressController::class, 'destroyDistrict']);
    Route::delete('address/villages/{village}', [AddressController::class, 'destroyVillage']);
    Route::delete('address/streets/{street}', [AddressController::class, 'destroyStreet']);
    Route::post('address/provinces/{id}/restore', [AddressController::class, 'restoreProvince']);
    Route::post('address/cities/{id}/restore', [AddressController::class, 'restoreCity']);
    Route::post('address/districts/{id}/restore', [AddressController::class, 'restoreDistrict']);
    Route::post('address/villages/{id}/restore', [AddressController::class, 'restoreVillage']);
    Route::post('address/streets/{id}/restore', [AddressController::class, 'restoreStreet']);

    // ── MFA — Two-Factor Authentication (TOTP) ──────────────────────────
    Route::post('mfa/setup', [MfaController::class, 'setup']);
    Route::post('mfa/enable', [MfaController::class, 'enable']);
    Route::post('mfa/disable', [MfaController::class, 'disable']);
    Route::post('mfa/verify', [MfaController::class, 'verify']);
    Route::get('mfa/recovery-codes', [MfaController::class, 'getRecoveryCodes']);
    Route::post('mfa/recovery-codes/regenerate', [MfaController::class, 'regenerateRecoveryCodes']);

    // Manajemen user & role dalam tenant (admin_tenant / berizin)
    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('permission:iam.role.view');
    Route::post('roles', [RoleController::class, 'store'])
        ->middleware('permission:iam.role.manage');
    Route::put('roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:iam.role.manage');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:iam.role.manage');

    Route::get('users', [UserController::class, 'index'])
        ->middleware('permission:iam.user.view');
    Route::post('users', [UserController::class, 'store'])
        ->middleware('permission:iam.user.manage');
    Route::put('users/{user}', [UserController::class, 'update'])
        ->middleware('permission:iam.user.manage');
    Route::post('users/{user}/roles', [UserController::class, 'syncRoles'])
        ->middleware('permission:iam.user.manage');
    Route::post('users/{user}/zone', [UserController::class, 'assignZone'])
        ->middleware('permission:zone.employee.assign');

    // ── Fase 2: Manajemen Wilayah/Zona (ZONE) ────────────────────────
    Route::get('zones', [ZoneController::class, 'index'])
        ->middleware('permission:zone.zone.view');
    Route::post('zones', [ZoneController::class, 'store'])
        ->middleware('permission:zone.zone.create');
    Route::get('zones/dashboard', [ZoneDashboardController::class, 'all'])
        ->middleware('permission:zone.dashboard.view');
    Route::get('zones/{zone}', [ZoneController::class, 'show'])
        ->middleware('permission:zone.zone.view');
    Route::put('zones/{zone}', [ZoneController::class, 'update'])
        ->middleware('permission:zone.zone.update');
    Route::post('zones/{zone}/deactivate', [ZoneController::class, 'deactivate'])
        ->middleware('permission:zone.zone.deactivate');
    Route::delete('zones/{zone}', [ZoneController::class, 'destroy'])
        ->middleware('permission:zone.zone.update');
    Route::get('zones/{zone}/dashboard', [ZoneDashboardController::class, 'show'])
        ->middleware('permission:zone.dashboard.view');

    // ── Fase 1.1–1.4: Master Alamat, Tarif, Pelanggan ──────────────────
    Route::get('tariffs', [TariffController::class, 'index'])
        ->middleware('permission:core.tariff.view');
    Route::post('tariffs', [TariffController::class, 'store'])
        ->middleware('permission:core.tariff.manage');
    Route::get('tariffs/{tariffCategory}', [TariffController::class, 'show'])
        ->middleware('permission:core.tariff.view');
    Route::put('tariffs/{tariffCategory}', [TariffController::class, 'update'])
        ->middleware('permission:core.tariff.manage');
    Route::delete('tariffs/{tariffCategory}', [TariffController::class, 'destroy'])
        ->middleware('permission:core.tariff.manage');

    Route::get('customers', [CustomerController::class, 'index'])
        ->middleware('permission:core.customer.view');
    Route::post('customers', [CustomerController::class, 'store'])
        ->middleware('permission:core.customer.create');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])
        ->middleware('permission:core.customer.view');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])
        ->middleware('permission:core.customer.update');
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])
        ->middleware('permission:core.customer.update');
    Route::get('customers/{customer}/status-history', [CustomerController::class, 'statusHistory'])
        ->middleware('permission:core.customer.view');
    Route::post('customers/{customer}/manual-reading', [CustomerController::class, 'manualReading'])
        ->middleware('permission:core.customer.update');

    // ── Portal Pelanggan (mobile) — hanya untuk akun pelanggan ────────
    Route::prefix('portal')->middleware('module:APP')->group(function () {
        Route::get('dashboard', [CustomerPortalController::class, 'dashboard']);
        Route::get('bills', [CustomerPortalController::class, 'bills']);
        Route::get('bills/{bill}', [CustomerPortalController::class, 'billDetail']);
        Route::get('consumption-chart', [CustomerPortalController::class, 'consumptionChart']);
        Route::get('usage-history', [CustomerPortalController::class, 'consumptionChart']);
        Route::get('complaints', [CustomerPortalController::class, 'complaints']);
        Route::get('complaints/{complaint}', [CustomerPortalController::class, 'complaint']);
        Route::post('complaints', [CustomerPortalController::class, 'createComplaint']);
        Route::get('profile', [CustomerPortalController::class, 'profile']);
        Route::put('profile', [CustomerPortalController::class, 'updateProfile']);
        Route::get('notifications', [CustomerPortalController::class, 'notifications']);
        Route::post('notifications/{notificationId}/read', [CustomerPortalController::class, 'markNotificationRead']);
        Route::post('notifications/read-all', [CustomerPortalController::class, 'markAllNotificationsRead']);
        Route::post('bills/{bill}/pay', [CustomerPortalController::class, 'payBill']);
    });

    // ── Notifikasi — preferensi + push test ──────────────────────────
    Route::middleware('module:APP')->group(function () {
        Route::get('notification-preferences', [NotificationController::class, 'preferences']);
        Route::put('notification-preferences', [NotificationController::class, 'updatePreferences']);
        Route::post('notification-preferences/test-push', [NotificationController::class, 'testPush']);
    });

    // ── Dashboard per Role ─────────────────────────────────────────────
    Route::get('dashboard/director', [DashboardController::class, 'director']);
    Route::get('dashboard/finance', [DashboardController::class, 'finance']);
    Route::get('dashboard/operations', [DashboardController::class, 'operations']);
    Route::get('dashboard/metx', [DashboardController::class, 'metx'])->middleware('module:METX');
    Route::get('dashboard/executive-kpi', [DashboardController::class, 'executiveKpi']);

    // ── Penagihan (Kabag Keuangan) ────────────────────────────────────
    Route::get('bills', [BillingController::class, 'index'])
        ->middleware('permission:core.bill.view');
    Route::post('bills/generate', [BillingController::class, 'generate'])
        ->middleware('permission:core.bill.generate');
    Route::get('bills/{bill}', [BillingController::class, 'show'])
        ->middleware('permission:core.bill.view');

    // ── Pembayaran (Payment Gateway + Tunai Loket) ─────────────────────
    Route::post('payments', [PaymentController::class, 'createPayment'])
        ->middleware('permission:core.payment.create');
    Route::post('payments/cash', [PaymentController::class, 'cashPayment'])
        ->middleware('permission:core.payment.create');
    Route::get('payments/check/{orderId}', [PaymentController::class, 'checkStatus'])
        ->middleware('permission:core.payment.view');
    Route::get('payments/history', [PaymentController::class, 'paymentHistory']);
    Route::get('payments/{payment}/receipt', [PaymentController::class, 'downloadReceipt']);
    Route::get('payments/{payment}/refunds', [PaymentController::class, 'refunds'])->middleware('permission:core.payment.view');
    Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])->middleware('permission:core.payment.refund');

    // ── Laporan Akuntansi (Keuangan & Direktur, view) ─────────────────
    Route::prefix('reports/accounting')->middleware('permission:core.report.view')->group(function () {

        Route::get('general-ledger', [AccountingReportController::class, 'generalLedger']);
        Route::get('trial-balance', [AccountingReportController::class, 'trialBalance']);
        Route::get('income-statement', [AccountingReportController::class, 'incomeStatement']);
        Route::get('balance-sheet', [AccountingReportController::class, 'balanceSheet']);
        Route::get('cash-flow', [AccountingReportController::class, 'cashFlow']);
    });

    // ── Fase 2: Pendaftaran & Survey (SRV) ────────────────────────────
    Route::get('prospects', [ProspectController::class, 'index'])
        ->middleware('permission:srv.prospect.view');
    Route::post('prospects/parse-ktp', [ProspectController::class, 'parseKtp'])
        ->middleware('permission:srv.prospect.create');
    Route::post('prospects/upload-ktp', [ProspectController::class, 'uploadKtp'])
        ->middleware('permission:srv.prospect.create');
    Route::post('prospects', [ProspectController::class, 'store'])
        ->middleware('permission:srv.prospect.create');
    Route::get('prospects/{prospect}', [ProspectController::class, 'show'])
        ->middleware('permission:srv.prospect.view');
    Route::delete('prospects/{prospect}', [ProspectController::class, 'destroy'])
        ->middleware('permission:srv.prospect.create');
    Route::put('prospects/{prospect}', [ProspectController::class, 'update'])
        ->middleware('permission:srv.prospect.create');
    Route::post('prospects/{prospect}/assign-surveyor', [ProspectController::class, 'assignSurveyor'])
        ->middleware('permission:srv.prospect.assign');
    Route::post('prospects/{prospect}/survey', [ProspectController::class, 'submitSurvey'])
        ->middleware('permission:srv.survey.submit');
    Route::post('survey-reports/{report}/review', [ProspectController::class, 'reviewSurvey'])
        ->middleware('permission:srv.survey.approve');
    Route::post('prospects/{prospect}/pay-installation', [ProspectController::class, 'payInstallation'])
        ->middleware('permission:srv.prospect.pay');

    // ── Fase 2.6: Penjadwalan & Aktivasi Pemasangan ──────────────────────
    Route::post('prospects/{prospect}/order-materials', [InstallationController::class, 'orderMaterials'])
        ->middleware('permission:srv.installation.schedule');
    Route::post('prospects/{prospect}/schedule-installation', [InstallationController::class, 'schedule'])
        ->middleware('permission:srv.installation.schedule');
    Route::post('installations/{schedule}/complete', [InstallationController::class, 'complete'])
        ->middleware('permission:srv.installation.complete');
    Route::post('prospects/{prospect}/activate', [InstallationController::class, 'activate'])
        ->middleware('permission:srv.installation.activate');

    // Material order pemasangan: daftar, stock-out manual, cancel (P1/M-10)
    Route::get('prospects/{prospect}/material-orders', [InstallationController::class, 'materialOrders'])
        ->middleware('permission:srv.installation.schedule');
    Route::post('material-orders/{order}/issue', [InstallationController::class, 'issueMaterials'])
        ->middleware('permission:wh.material.update');
    Route::post('material-orders/{order}/cancel', [InstallationController::class, 'cancelMaterials'])
        ->middleware('permission:srv.installation.schedule');

    // ── Fase 2.6: Lifecycle Pelanggan (isolir/sambung/balik nama) ─────
    Route::post('customers/{customer}/disconnect', [CustomerLifecycleController::class, 'disconnect'])
        ->middleware('permission:core.lifecycle.disconnect');
    Route::post('customers/{customer}/reconnect', [CustomerLifecycleController::class, 'reconnect'])
        ->middleware('permission:core.lifecycle.reconnect');
    Route::post('customers/{customer}/transfer-ownership', [CustomerLifecycleController::class, 'transferOwnership'])
        ->middleware('permission:core.lifecycle.transfer_ownership');

    // ── Fase 4: Baca Meter (MTR) ──────────────────────────────────────
    // Rute baca meter (CRUD + assign jalan/petugas + auto-map pelanggan)
    Route::get('meter-routes', [MeterRouteController::class, 'index'])
        ->middleware('permission:mtr.route.view');
    Route::post('meter-routes', [MeterRouteController::class, 'store'])
        ->middleware('permission:mtr.route.create');
    Route::post('meter-routes/auto-map', [MeterRouteController::class, 'autoMap'])
        ->middleware('permission:mtr.route.assign');
    Route::get('meter-routes/{route}', [MeterRouteController::class, 'show'])
        ->middleware('permission:mtr.route.view');
    Route::put('meter-routes/{route}', [MeterRouteController::class, 'update'])
        ->middleware('permission:mtr.route.update');
    Route::delete('meter-routes/{route}', [MeterRouteController::class, 'destroy'])
        ->middleware('permission:mtr.route.update');
    Route::post('meter-routes/{route}/streets', [MeterRouteController::class, 'syncStreets'])
        ->middleware('permission:mtr.route.assign');
    Route::post('meter-routes/{route}/officer', [MeterRouteController::class, 'assignOfficer'])
        ->middleware('permission:mtr.route.assign');

    // Periode & pembacaan
    Route::post('reading-periods', [MeterReadingController::class, 'openPeriod'])
        ->middleware('permission:mtr.period.open');
    Route::post('reading-periods/{period}/close', [MeterReadingController::class, 'closePeriod'])
        ->middleware('permission:mtr.period.close');
    Route::post('meter-readings/parse', [MeterReadingController::class, 'parseMeter'])
        ->middleware('permission:mtr.reading.create');
    Route::post('meter-readings', [MeterReadingController::class, 'store'])
        ->middleware('permission:mtr.reading.create');
    Route::post('meter-readings/{reading}/verify', [MeterReadingController::class, 'verify'])
        ->middleware('permission:mtr.reading.verify');

    // Dashboard progress baca per rute
    Route::get('meter-readings/route-progress', [MeterReadingController::class, 'routeProgress'])
        ->middleware('permission:mtr.dashboard.view');
    Route::get('meter-readings/report', [MeterReadingController::class, 'report'])
        ->middleware('permission:mtr.reading.view');

    // ── Fase 6: Meter Analytics (METX) ────────────────────────────────
    // Gated modul METX (add-on berbayar) + permission granular.
    Route::middleware('module:METX')->group(function () {
        // Master meter fisik + lifecycle
        Route::get('meters', [MeterController::class, 'index'])
            ->middleware('permission:metx.meter.view');
        Route::post('meters', [MeterController::class, 'store'])
            ->middleware('permission:metx.meter.create');
        Route::get('meters/{meter}', [MeterController::class, 'show'])
            ->middleware('permission:metx.meter.view');
        Route::put('meters/{meter}', [MeterController::class, 'update'])
            ->middleware('permission:metx.meter.update');
        Route::post('meters/{meter}/assign', [MeterController::class, 'assign'])
            ->middleware('permission:metx.meter.assign');
        Route::post('meters/{meter}/remove', [MeterController::class, 'remove'])
            ->middleware('permission:metx.meter.lifecycle');
        Route::post('meters/{meter}/recalibrate', [MeterController::class, 'recalibrate'])
            ->middleware('permission:metx.meter.lifecycle');
        Route::post('meters/{meter}/scrap', [MeterController::class, 'scrap'])
            ->middleware('permission:metx.meter.lifecycle');

        // Deteksi & tindak lanjut anomali
        Route::get('meter-anomalies', [MeterAnomalyController::class, 'index'])
            ->middleware('permission:metx.anomaly.view');
        Route::post('meter-anomalies/scan', [MeterAnomalyController::class, 'scan'])
            ->middleware('permission:metx.anomaly.scan');
        Route::post('meter-anomalies/{anomaly}/review', [MeterAnomalyController::class, 'review'])
            ->middleware('permission:metx.anomaly.review');
        Route::post('meter-anomalies/{anomaly}/confirm', [MeterAnomalyController::class, 'confirm'])
            ->middleware('permission:metx.anomaly.confirm');
        Route::post('meter-anomalies/{anomaly}/dismiss', [MeterAnomalyController::class, 'dismiss'])
            ->middleware('permission:metx.anomaly.dismiss');

        // Dashboard analytics + rekomendasi ganti meter
        Route::get('metx/dashboard', [MeterAnomalyController::class, 'dashboard'])
            ->middleware('permission:metx.dashboard.view');
    });

    // ── Fase 7: Aset Tetap & Penyusutan (AST) ─────────────────────────
    // Gated modul AST (add-on) + permission granular.
    Route::middleware('module:AST')->group(function () {
        // Kategori aset
        Route::get('asset-categories', [AssetCategoryController::class, 'index'])
            ->middleware('permission:ast.asset.view');
        Route::post('asset-categories', [AssetCategoryController::class, 'store'])
            ->middleware('permission:ast.asset.manage');
        Route::put('asset-categories/{assetCategory}', [AssetCategoryController::class, 'update'])
            ->middleware('permission:ast.asset.manage');

        // Register aset + kartu + mutasi + disposal
        Route::get('assets', [FixedAssetController::class, 'index'])
            ->middleware('permission:ast.asset.view');
        Route::post('assets', [FixedAssetController::class, 'store'])
            ->middleware('permission:ast.asset.create');
        Route::post('assets/capitalize', [FixedAssetController::class, 'capitalize'])
            ->middleware('permission:ast.asset.create');
        Route::get('assets/dashboard', [FixedAssetController::class, 'dashboard'])
            ->middleware('permission:ast.dashboard.view');
        Route::get('assets/{fixedAsset}', [FixedAssetController::class, 'show'])
            ->middleware('permission:ast.asset.view');
        Route::get('assets/{fixedAsset}/card', [FixedAssetController::class, 'card'])
            ->middleware('permission:ast.asset.view');
        Route::post('assets/{fixedAsset}/move', [FixedAssetController::class, 'move'])
            ->middleware('permission:ast.asset.manage');
        Route::post('assets/{fixedAsset}/dispose', [FixedAssetController::class, 'dispose'])
            ->middleware('permission:ast.asset.dispose');

        // Penyusutan (jalankan manual selain cron)
        Route::post('assets/depreciation/run', [FixedAssetController::class, 'runDepreciation'])
            ->middleware('permission:ast.depreciation.run');
    });

    // ── Fase 5: Pengadaan & Gudang (WH) ───────────────────────────────
    Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])
        ->middleware('permission:wh.po.view');
    Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])
        ->middleware('permission:wh.po.create');
    Route::post('purchase-orders/{po}/approve', [PurchaseOrderController::class, 'approve'])
        ->middleware('permission:wh.po.approve');
    Route::post('purchase-orders/{po}/purchase', [PurchaseOrderController::class, 'purchase'])
        ->middleware('permission:wh.po.approve');
    Route::post('purchase-orders/{po}/receive', [PurchaseOrderController::class, 'receive'])
        ->middleware('permission:wh.material.update');

    Route::get('stock-transfers', [StockTransferController::class, 'index'])
        ->middleware('permission:wh.transfer.view');
    Route::post('stock-transfers', [StockTransferController::class, 'store'])
        ->middleware('permission:wh.transfer.create');
    Route::post('stock-transfers/{transfer}/approve', [StockTransferController::class, 'approve'])
        ->middleware('permission:wh.transfer.approve');
    Route::post('stock-transfers/{transfer}/receive', [StockTransferController::class, 'receive'])
        ->middleware('permission:wh.transfer.create');

    // ── Fase 9: Pengaduan & CRM ────────────────────────────────────────
    Route::get('complaints', [ComplaintController::class, 'index'])
        ->middleware('permission:crm.complaint.view');
    Route::post('complaints', [ComplaintController::class, 'store'])
        ->middleware('permission:crm.complaint.create');
    Route::get('complaints/dashboard', [ComplaintController::class, 'dashboard'])
        ->middleware('permission:crm.complaint.view');
    Route::get('complaints/{complaint}', [ComplaintController::class, 'show'])
        ->middleware('permission:crm.complaint.view');
    Route::post('complaints/{complaint}/assign', [ComplaintController::class, 'assign'])
        ->middleware('permission:crm.complaint.assign');
    Route::post('complaints/{complaint}/resolve', [ComplaintController::class, 'resolve'])
        ->middleware('permission:crm.complaint.resolve');

    // ── Fase 10: Customer 360 View ─────────────────────────────────────
    Route::get('customer-view/quick-search', [CustomerViewController::class, 'quickSearch'])
        ->middleware('permission:c360.view');
    Route::get('customer-view/{customerId}', [CustomerViewController::class, 'show'])
        ->middleware('permission:c360.view');
    Route::get('customer-view/{customerId}/aggregation', [CustomerViewController::class, 'aggregation'])
        ->middleware('permission:c360.view');

    // ── Fase 11: Advanced Billing (adjustment + rekonsiliasi) ─────────
    Route::get('bill-adjustments', [BillAdjustmentController::class, 'index'])
        ->middleware('permission:bill.adjust');
    Route::post('bill-adjustments', [BillAdjustmentController::class, 'store'])
        ->middleware('permission:bill.adjust');
    Route::post('bill-adjustments/{adjustment}/approve', [BillAdjustmentController::class, 'approve'])
        ->middleware('permission:bill.adjust');
    Route::post('bill-adjustments/{adjustment}/reject', [BillAdjustmentController::class, 'reject'])
        ->middleware('permission:bill.adjust');
    Route::post('bill-adjustments/{adjustment}/post', [BillAdjustmentController::class, 'post'])
        ->middleware('permission:bill.adjust');
    Route::post('bill-reconciliation', [BillRecController::class, 'reconcile'])
        ->middleware('permission:bill.audit');
    Route::post('bill-audit', [BillRecController::class, 'audit'])
        ->middleware('permission:bill.audit');

    // ── Fase 19: GIS — Water Network ──────────────────────────────────
    Route::get('gis/customers', [GisController::class, 'customerGeoJson'])->middleware('module:GIS');
    Route::get('gis/customers/status-summary', [GisController::class, 'statusSummary'])->middleware('module:GIS');

    // ── Export GLOBAL (CSV/HTML) ───────────────────────────────────────
    Route::post('export', [ExportController::class, 'export'])
        ->middleware('permission:core.report.export');

    // ── Fase 15: FSM — Work Order ──────────────────────────────────────
    Route::get('work-orders', [WorkOrderController::class, 'index'])
        ->middleware('permission:fsm.wo.view');
    Route::post('work-orders', [WorkOrderController::class, 'store'])
        ->middleware('permission:fsm.wo.create');
    Route::get('work-orders/{workOrder}', [WorkOrderController::class, 'show'])
        ->middleware('permission:fsm.wo.view');
    Route::post('work-orders/{workOrder}/assign', [WorkOrderController::class, 'assign'])
        ->middleware('permission:fsm.wo.assign');
    Route::post('work-orders/{workOrder}/start', [WorkOrderController::class, 'start'])
        ->middleware('permission:fsm.wo.assign');
    Route::post('work-orders/{workOrder}/complete', [WorkOrderController::class, 'complete'])
        ->middleware('permission:fsm.wo.complete');
    Route::get('work-orders/technician/dashboard', [WorkOrderController::class, 'technicianDashboard'])
        ->middleware('permission:fsm.dashboard.view');

    // ── Fase 14: PROC — Vendor ────────────────────────────────────────
    Route::get('vendors', [VendorController::class, 'index'])
        ->middleware('permission:proc.vendor.view');
    Route::post('vendors', [VendorController::class, 'store'])
        ->middleware('permission:proc.vendor.create');
    Route::get('vendors/{vendor}', [VendorController::class, 'show'])
        ->middleware('permission:proc.vendor.view');
    Route::put('vendors/{vendor}', [VendorController::class, 'update'])
        ->middleware('permission:proc.vendor.update');
    Route::post('vendors/{vendor}/evaluate', [VendorController::class, 'evaluate'])
        ->middleware('permission:proc.vendor.evaluate');

    // ── Fase 17: HR — Employee ────────────────────────────────────────
    Route::get('employees', [HrEmployeeController::class, 'index'])
        ->middleware('permission:hr.employee.view');
    Route::post('employees', [HrEmployeeController::class, 'store'])
        ->middleware('permission:hr.employee.create');
    Route::get('employees/{employee}', [HrEmployeeController::class, 'show'])
        ->middleware('permission:hr.employee.view');
    Route::put('employees/{employee}', [HrEmployeeController::class, 'update'])
        ->middleware('permission:hr.employee.update');
    Route::get('employees/dashboard/summary', [HrEmployeeController::class, 'dashboard'])
        ->middleware('permission:hr.employee.view');

    // ── Fase 8: FIN+ Keuangan Advance ──────────────────────────────────
    Route::prefix('fin')->group(function () {
        Route::get('sales-invoices', [FinController::class, 'salesInvoices'])->middleware('permission:fin.ar.view');
        Route::post('sales-invoices', [FinController::class, 'createSalesInvoice'])->middleware('permission:fin.ar.manage');
        Route::get('ar-aging-report', [FinController::class, 'arAgingReport'])->middleware('permission:fin.ar.view');

        Route::get('budgets', [FinController::class, 'budgets'])->middleware('permission:fin.budget.view');
        Route::post('budgets', [FinController::class, 'createBudget'])->middleware('permission:fin.budget.manage');

        Route::get('projects', [FinController::class, 'projects'])->middleware('permission:fin.budget.view');
        Route::post('projects', [FinController::class, 'createProject'])->middleware('permission:fin.budget.manage');

        Route::get('tax-records', [FinController::class, 'taxRecords'])->middleware('permission:fin.tax.view');

        Route::get('bank-accounts', [FinController::class, 'bankAccounts'])->middleware('permission:fin.bank.view');
        Route::post('bank-accounts', [FinController::class, 'createBankAccount'])->middleware('permission:fin.bank.manage');

        Route::get('currencies', [FinController::class, 'currencies'])->middleware('permission:fin.bank.view');

        Route::get('recurring-transactions', [FinController::class, 'recurringTransactions'])->middleware('permission:fin.budget.view');
        Route::post('recurring-transactions', [FinController::class, 'createRecurring'])->middleware('permission:fin.budget.manage');
    });

    // ── Fase 5: WH Gudang (stok keluar + opname + dashboard) ────────
    Route::post('warehouse/stock-out', [WarehouseController::class, 'stockOut'])->middleware('permission:wh.stock.view');
    Route::get('warehouse/dashboard', [WarehouseController::class, 'stockDashboard'])->middleware('permission:wh.stock.view');
    Route::post('warehouse/adjustment', [WarehouseController::class, 'stockAdjustment'])->middleware('permission:wh.stock.adjust');

    // ── Fase 13: CHEM Chemical Management ──────────────────────────
    Route::get('chem/chemicals', [ChemController::class, 'chemicals'])->middleware('permission:chem.chemical.view');
    Route::post('chem/chemicals', [ChemController::class, 'storeChemical'])->middleware('permission:chem.chemical.manage');
    Route::get('chem/receipts', [ChemController::class, 'receipts'])->middleware('permission:chem.receipt.view');
    Route::post('chem/receipts', [ChemController::class, 'createReceipt'])->middleware('permission:chem.receipt.create');
    Route::post('chem/receipts/{receipt}/qc', [ChemController::class, 'qcTest'])->middleware('permission:chem.qc.test');
    Route::post('chem/usage', [ChemController::class, 'usage'])->middleware('permission:chem.usage.record');
    Route::get('chem/dashboard', [ChemController::class, 'dashboard'])->middleware('permission:chem.dashboard.view');

    // ── Fase 14: PROC Tender ───────────────────────────────────────
    Route::get('tenders', [TenderController::class, 'index'])->middleware('permission:proc.tender.view');
    Route::post('tenders', [TenderController::class, 'store'])->middleware('permission:proc.tender.create');
    Route::get('tenders/{tender}', [TenderController::class, 'show'])->middleware('permission:proc.tender.view');
    Route::post('tenders/{tender}/bid', [TenderController::class, 'submitBid'])->middleware('permission:proc.tender.evaluate');
    Route::post('tenders/{tender}/evaluate', [TenderController::class, 'evaluate'])->middleware('permission:proc.tender.evaluate');
    Route::post('tenders/{tender}/award', [TenderController::class, 'award'])->middleware('permission:proc.tender.award');

    // ── Fase 18: DMS Document Management ───────────────────────────
    Route::get('documents', [DocumentController::class, 'index'])->middleware('permission:dms.document.view');
    Route::post('documents', [DocumentController::class, 'store'])->middleware('permission:dms.document.upload');
    Route::get('documents/{document}', [DocumentController::class, 'show'])->middleware('permission:dms.document.view');
    Route::post('documents/{document}/approve', [DocumentController::class, 'approve'])->middleware('permission:dms.document.approve');

    // ── Fase 12: CHAT live chat ────────────────────────────────────
    Route::middleware('module:APP')->group(function () {
        Route::get('chats', [ChatController::class, 'index'])->middleware('permission:core.user');
        Route::post('chats', [ChatController::class, 'store'])->middleware('permission:core.user');
        Route::get('chats/{chat}/messages', [ChatController::class, 'messages'])->middleware('permission:core.user');
        Route::post('chats/{chat}/messages', [ChatController::class, 'sendMessage'])->middleware('permission:core.user');
    });

    // ── Fase 16: MNT — Maintenance ────────────────────────────────────
    Route::get('maintenance/schedules', [MaintenanceController::class, 'schedules'])->middleware('permission:mnt.schedule.view');
    Route::post('maintenance/schedules', [MaintenanceController::class, 'createSchedule'])->middleware('permission:mnt.schedule.manage');
    Route::put('maintenance/schedules/{schedule}', [MaintenanceController::class, 'updateSchedule'])->middleware('permission:mnt.schedule.manage');
    Route::get('maintenance/records', [MaintenanceController::class, 'records'])->middleware('permission:mnt.record.view');
    Route::post('maintenance/records', [MaintenanceController::class, 'createRecord'])->middleware('permission:mnt.record.create');

    // ── FIN+ Advance: Tax, Reconciliation, Transfer ─────────────────
    Route::post('fin/tax/calculate', [FinAdvanceController::class, 'taxCalculate'])->middleware('permission:fin.tax.manage');
    Route::post('fin/tax/record', [FinAdvanceController::class, 'taxRecord'])->middleware('permission:fin.tax.manage');
    Route::post('fin/tax/efaktur-export', [FinAdvanceController::class, 'exportEfaktur'])->middleware('permission:fin.tax.view');
    Route::post('fin/bank/{account}/reconcile', [FinAdvanceController::class, 'reconcileBank'])->middleware('permission:fin.bank.manage');
    Route::post('fin/bank/transfer', [FinAdvanceController::class, 'bankTransfer'])->middleware('permission:fin.bank.manage');
    Route::get('fin/bank/{account}/summary', [FinAdvanceController::class, 'bankSummary'])->middleware('permission:fin.bank.view');
    Route::get('fin/reconciliations', [FinAdvanceController::class, 'reconciliations'])->middleware('permission:fin.bank.view');

    // ── Inventory Valuation + Manual Journal ─────────────────────────
    Route::get('inventory/valuation', [InventoryController::class, 'valuation'])->middleware('permission:wh.stock.view');
    Route::get('inventory/balance-sheet', [InventoryController::class, 'balanceSheet'])->middleware('permission:wh.stock.view');
    Route::post('journal/manual', [InventoryController::class, 'manualJournal'])->middleware('permission:core.journal.create');

    // ── HR: Payroll ──────────────────────────────────────────────────
    Route::post('payroll/calculate', [PayrollController::class, 'calculate'])->middleware('permission:hr.payroll.view');
    Route::post('payroll/batch-run', [PayrollController::class, 'batchRun'])->middleware('permission:hr.payroll.run');
    Route::post('payroll/slip', [PayrollController::class, 'slip'])->middleware('permission:hr.payroll.view');

    // ── HR: Attendance + Leave + Overtime + Shift ───────────────────
    Route::prefix('hr')->group(function () {
        Route::get('attendances', [AttendanceController::class, 'index'])->middleware('permission:hr.attendance.view');
        Route::post('attendances/check-in', [AttendanceController::class, 'checkIn'])->middleware('permission:hr.attendance.manage');
        Route::post('attendances/check-out', [AttendanceController::class, 'checkOut'])->middleware('permission:hr.attendance.manage');
        Route::get('leaves', [AttendanceController::class, 'leaves'])->middleware('permission:hr.leave.view');
        Route::post('leaves', [AttendanceController::class, 'requestLeave'])->middleware('permission:hr.leave.request');
        Route::post('leaves/{leave}/approve', [AttendanceController::class, 'approveLeave'])->middleware('permission:hr.leave.approve');
        Route::get('overtime', [AttendanceController::class, 'overtimeRequests'])->middleware('permission:hr.overtime.view');
        Route::post('overtime', [AttendanceController::class, 'requestOvertime'])->middleware('permission:hr.overtime.request');
        Route::post('overtime/{overtime}/approve', [AttendanceController::class, 'approveOvertime'])->middleware('permission:hr.overtime.approve');
        Route::get('shifts', [AttendanceController::class, 'shifts'])->middleware('permission:hr.shift.view');
        Route::post('shifts', [AttendanceController::class, 'createShift'])->middleware('permission:hr.shift.manage');
    });

    // ── Tier 3: IOT / PROD / DIST / NRW ──────────────────────────────
    Route::prefix('iot')->group(function () {
        Route::post('ingest', [IotController::class, 'ingest'])->middleware('permission:iot.ingest');
        Route::get('dashboard', [IotController::class, 'dashboard'])->middleware('permission:iot.view');
    });
    Route::prefix('production')->group(function () {
        Route::post('ingest', [ProductionController::class, 'ingest'])->middleware('permission:prod.ingest');
        Route::get('metrics', [ProductionController::class, 'metrics'])->middleware('permission:prod.view');
        Route::get('dashboard', [ProductionController::class, 'dashboard'])->middleware('permission:prod.view');
    });
    Route::prefix('distribution')->group(function () {
        Route::post('ingest', [DistController::class, 'ingest'])->middleware('permission:dist.ingest');
        Route::get('dma-dashboard', [DistController::class, 'dmaDashboard'])->middleware('permission:dist.view');
    });
    Route::prefix('nrw')->group(function () {
        Route::post('calculate', [NrwController::class, 'calculate'])->middleware('permission:nrw.manage');
        Route::get('dashboard', [NrwController::class, 'dashboard'])->middleware('permission:nrw.view');
    });

    // ── Fase 20: CC Call Center ──────────────────────────────────────
    Route::get('call-logs', [CallCenterController::class, 'index'])->middleware('permission:cc.call.view');
    Route::post('call-logs', [CallCenterController::class, 'log'])->middleware('permission:cc.call.log');
    Route::get('call-logs/dashboard', [CallCenterController::class, 'dashboard'])->middleware('permission:cc.call.view');

    // ── HR Advance: Kontrak + Pensiun/Terminasi ──────────────────────
    Route::get('hr/contracts', [HrAdvanceController::class, 'contracts'])->middleware('permission:hr.contract.view');
    Route::post('hr/contracts', [HrAdvanceController::class, 'createContract'])->middleware('permission:hr.contract.manage');
    Route::get('hr/contracts/terminating-soon', [HrAdvanceController::class, 'terminatingSoon'])->middleware('permission:hr.contract.view');
    Route::get('hr/terminations', [HrAdvanceController::class, 'terminations'])->middleware('permission:hr.termination.view');
    Route::post('hr/terminations', [HrAdvanceController::class, 'createTermination'])->middleware('permission:hr.termination.manage');
    Route::post('hr/terminations/{termination}/approve', [HrAdvanceController::class, 'approveTermination'])->middleware('permission:hr.termination.manage');

    // ── AST: Revaluasi + Stock Opname Aset ──────────────────────────
    Route::post('assets/{asset}/revaluation', [AssetOpnameController::class, 'revaluation'])->middleware('permission:ast.asset.manage');
    Route::post('assets/opname', [AssetOpnameController::class, 'opname'])->middleware('permission:ast.asset.manage');

    // ── Fase 22: INT — Integration Platform ─────────────────────────
    Route::get('integrations', [IntegrationController::class, 'index'])->middleware('permission:int.integration.view');
    Route::post('integrations', [IntegrationController::class, 'store'])->middleware('permission:int.integration.manage');
    Route::post('integrations/{integration}/toggle', [IntegrationController::class, 'toggle'])->middleware('permission:int.integration.manage');
    Route::get('integration-logs', [IntegrationController::class, 'logs'])->middleware('permission:int.integration.view');
    Route::post('integrations/test-send', [IntegrationController::class, 'testSend'])->middleware('permission:int.integration.manage');
    Route::get('api-keys', [IntegrationController::class, 'apiKeys'])->middleware('permission:int.apikey.view');
    Route::post('api-keys', [IntegrationController::class, 'createApiKey'])->middleware('permission:int.apikey.create');
    Route::post('api-keys/{apiKey}/revoke', [IntegrationController::class, 'revokeApiKey'])->middleware('permission:int.apikey.revoke');
    Route::post('webhook', [IntegrationController::class, 'webhook'])->middleware('permission:int.integration.manage');

    // ── UU PDP — Data Privacy ─────────────────────────────────────────
    Route::get('privacy/export-my-data', [DataPrivacyController::class, 'exportMyData']);
    Route::post('privacy/delete-my-data', [DataPrivacyController::class, 'deleteMyData']);
    Route::get('privacy/retention-status', [DataPrivacyController::class, 'retentionStatus']);
    Route::post('privacy/purge-old-data', [DataPrivacyController::class, 'purgeOldData'])
        ->middleware('permission:core.privacy.purge');
    Route::post('privacy/purge-old-data/{purgeRequest}/approve', [DataPrivacyController::class, 'approvePurge'])
        ->middleware('permission:core.privacy.purge');

    // ── Fase 21: BI — Report Builder + KPI ────────────────────────────
    Route::get('bi/kpi-eksekutif', [BiController::class, 'kpiEkskutif'])->middleware('permission:bi.view.view');
    Route::post('bi/report-builder', [BiController::class, 'reportBuilder'])->middleware('permission:bi.view.view');
    Route::get('bi/scheduled-reports', [ScheduledReportController::class, 'index'])->middleware('permission:bi.schedule.view');
    Route::post('bi/scheduled-reports', [ScheduledReportController::class, 'store'])->middleware('permission:bi.schedule.manage');
    Route::put('bi/scheduled-reports/{scheduledReport}', [ScheduledReportController::class, 'update'])->middleware('permission:bi.schedule.manage');
    Route::delete('bi/scheduled-reports/{scheduledReport}', [ScheduledReportController::class, 'destroy'])->middleware('permission:bi.schedule.manage');
    Route::post('bi/scheduled-reports/{scheduledReport}/run', [ScheduledReportController::class, 'runNow'])->middleware('permission:bi.schedule.run');
    Route::get('bi/scheduled-reports/{scheduledReport}/runs', [ScheduledReportController::class, 'history'])->middleware('permission:bi.schedule.view');
    Route::get('bi/scheduled-reports/{scheduledReport}/runs/{run}/download', [ScheduledReportController::class, 'download'])->middleware('permission:bi.schedule.download');

    // ── HR: Training + Appraisal + Certification ──────────────────────
    Route::get('hr/trainings', [HrTrainingController::class, 'trainings'])->middleware('permission:hr.training.view');
    Route::post('hr/trainings', [HrTrainingController::class, 'createTraining'])->middleware('permission:hr.training.manage');
    Route::post('hr/trainings/{training}/participants', [HrTrainingController::class, 'addParticipant'])->middleware('permission:hr.training.manage');
    Route::put('hr/trainings/{training}/participants/{participant}', [HrTrainingController::class, 'updateParticipant'])->middleware('permission:hr.training.manage');
    Route::get('hr/certifications', [HrTrainingController::class, 'certifications'])->middleware('permission:hr.training.view');
    Route::post('hr/certifications', [HrTrainingController::class, 'createCertification'])->middleware('permission:hr.training.manage');
    Route::get('hr/certifications/expiring', [HrTrainingController::class, 'expiringCertifications'])->middleware('permission:hr.training.view');
    Route::get('hr/appraisals', [HrTrainingController::class, 'appraisals'])->middleware('permission:hr.appraisal.view');
    Route::post('hr/appraisals', [HrTrainingController::class, 'createAppraisal'])->middleware('permission:hr.appraisal.manage');
    Route::post('hr/appraisals/{appraisal}/approve', [HrTrainingController::class, 'approveAppraisal'])->middleware('permission:hr.appraisal.manage');

    // ── GIS: Editor Pipa + Network Edges ───────────────────────────────
    Route::get('gis/pipes', [GisEditorController::class, 'pipes'])->middleware('permission:gis.feature.view');
    Route::post('gis/pipes', [GisEditorController::class, 'createPipe'])->middleware('permission:gis.feature.create');
    Route::put('gis/pipes/{pipe}', [GisEditorController::class, 'updatePipe'])->middleware('permission:gis.feature.update');
    Route::get('gis/features', [GisEditorController::class, 'features'])->middleware('permission:gis.feature.view');
    Route::post('gis/features', [GisEditorController::class, 'createFeature'])->middleware('permission:gis.feature.create');
    Route::delete('gis/features/{feature}', [GisEditorController::class, 'destroyFeature'])->middleware('permission:gis.feature.delete');
    Route::get('gis/network-edges', [GisEditorController::class, 'networkEdges'])->middleware('permission:gis.feature.view');
    Route::post('gis/network-edges', [GisEditorController::class, 'createEdge'])->middleware('permission:gis.feature.create');
    Route::get('gis/dmas', [GisEditorController::class, 'dmas'])->middleware('permission:gis.feature.view');
    Route::post('gis/dmas', [GisEditorController::class, 'createDma'])->middleware('permission:gis.feature.create');
    Route::patch('gis/dmas/{dma}', [GisEditorController::class, 'updateDma'])->middleware('permission:gis.feature.update');
    Route::post('gis/dmas/{dma}/nrw-auto', [GisEditorController::class, 'autoNrw'])->middleware('permission:gis.feature.update');

    // ── GIS: Analisis Kebocoran + Insiden darurat ─────────────────────
    Route::post('gis/isolate', [GisEditorController::class, 'isolate'])->middleware('permission:gis.feature.view');
    Route::post('gis/incidents', [GisEditorController::class, 'storeIncident'])->middleware('permission:fsm.wo.create');

    // ── Offline Sync ───────────────────────────────────────────
    Route::post('sync/upload', [SyncController::class, 'upload']);
    Route::get('sync/download', [SyncController::class, 'download']);
    Route::get('sync/status', [SyncController::class, 'status']);
});
