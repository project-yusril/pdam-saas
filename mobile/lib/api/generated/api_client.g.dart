// GENERATED dari docs/openapi.json oleh tools/generate_dio_client.py. JANGAN edit manual.
// Regenerate: cd backend && php artisan pdam:openapi --out=../docs/openapi.json && python tools/generate_dio_client.py
// Kontrak = route registry Laravel (H-10); skema response generik `{success,data,meta}`.
// ignore_for_file: type=lint, prefer_single_quotes, directives_ordering

import 'package:dio/dio.dart';

/// Satu operasi; `params` = path `{id}` yang sudah dipetakan ke arg.
class ApiEndpoint {
  const ApiEndpoint(this.verb, this.path, this.params, this.summary, this.tag);
  final String verb;
  final String path;
  final List<String> params;
  final String summary;
  final String tag;
}

/// Manifest seluruh endpoint (sinkron dengan Laravel route:list & test
/// mobile/test/generated/dio_client_sync_test.dart).
const List<ApiEndpoint> allEndpoints = <ApiEndpoint>[
  ApiEndpoint('DELETE', '/address/cities/{city}', <String>['city'], 'AddressController@destroyCity', 'Cities'),
  ApiEndpoint('DELETE', '/address/districts/{district}', <String>['district'], 'AddressController@destroyDistrict', 'Districts'),
  ApiEndpoint('DELETE', '/address/provinces/{province}', <String>['province'], 'AddressController@destroyProvince', 'Provinces'),
  ApiEndpoint('DELETE', '/address/streets/{street}', <String>['street'], 'AddressController@destroyStreet', 'Streets'),
  ApiEndpoint('DELETE', '/address/villages/{village}', <String>['village'], 'AddressController@destroyVillage', 'Villages'),
  ApiEndpoint('DELETE', '/bi/scheduled-reports/{scheduledReport}', <String>['scheduledReport'], 'ScheduledReportController@destroy', 'Scheduled Reports'),
  ApiEndpoint('DELETE', '/customers/{customer}', <String>['customer'], 'CustomerController@destroy', 'Customer'),
  ApiEndpoint('DELETE', '/meter-routes/{route}', <String>['route'], 'MeterRouteController@destroy', 'Route'),
  ApiEndpoint('DELETE', '/prospects/{prospect}', <String>['prospect'], 'ProspectController@destroy', 'Prospect'),
  ApiEndpoint('DELETE', '/roles/{role}', <String>['role'], 'RoleController@destroy', 'Role'),
  ApiEndpoint('DELETE', '/tariffs/{tariffCategory}', <String>['tariffCategory'], 'TariffController@destroy', 'TariffCategory'),
  ApiEndpoint('DELETE', '/zones/{zone}', <String>['zone'], 'ZoneController@destroy', 'Zone'),
  ApiEndpoint('GET', '/address/cities', <String>[], 'AddressController@cities', 'Cities'),
  ApiEndpoint('GET', '/address/districts', <String>[], 'AddressController@districts', 'Districts'),
  ApiEndpoint('GET', '/address/provinces', <String>[], 'AddressController@provinces', 'Provinces'),
  ApiEndpoint('GET', '/address/streets', <String>[], 'AddressController@streets', 'Streets'),
  ApiEndpoint('GET', '/address/villages', <String>[], 'AddressController@villages', 'Villages'),
  ApiEndpoint('GET', '/api-keys', <String>[], 'IntegrationController@apiKeys', 'Root'),
  ApiEndpoint('GET', '/asset-categories', <String>[], 'AssetCategoryController@index', 'Root'),
  ApiEndpoint('GET', '/assets', <String>[], 'FixedAssetController@index', 'Root'),
  ApiEndpoint('GET', '/assets/dashboard', <String>[], 'FixedAssetController@dashboard', 'Dashboard'),
  ApiEndpoint('GET', '/assets/{fixedAsset}', <String>['fixedAsset'], 'FixedAssetController@show', 'FixedAsset'),
  ApiEndpoint('GET', '/assets/{fixedAsset}/card', <String>['fixedAsset'], 'FixedAssetController@card', 'FixedAsset'),
  ApiEndpoint('GET', '/bi/kpi-eksekutif', <String>[], 'BiController@kpiEkskutif', 'Kpi Eksekutif'),
  ApiEndpoint('GET', '/bi/scheduled-reports', <String>[], 'ScheduledReportController@index', 'Scheduled Reports'),
  ApiEndpoint('GET', '/bi/scheduled-reports/{scheduledReport}/runs', <String>['scheduledReport'], 'ScheduledReportController@history', 'Scheduled Reports'),
  ApiEndpoint('GET', '/bi/scheduled-reports/{scheduledReport}/runs/{run}/download', <String>['scheduledReport', 'run'], 'ScheduledReportController@download', 'Scheduled Reports'),
  ApiEndpoint('GET', '/bill-adjustments', <String>[], 'BillAdjustmentController@index', 'Root'),
  ApiEndpoint('GET', '/bills', <String>[], 'BillingController@index', 'Root'),
  ApiEndpoint('GET', '/bills/{bill}', <String>['bill'], 'BillingController@show', 'Bill'),
  ApiEndpoint('GET', '/call-logs', <String>[], 'CallCenterController@index', 'Root'),
  ApiEndpoint('GET', '/call-logs/dashboard', <String>[], 'CallCenterController@dashboard', 'Dashboard'),
  ApiEndpoint('GET', '/chats', <String>[], 'ChatController@index', 'Root'),
  ApiEndpoint('GET', '/chats/{chat}/messages', <String>['chat'], 'ChatController@messages', 'Chat'),
  ApiEndpoint('GET', '/chem/chemicals', <String>[], 'ChemController@chemicals', 'Chemicals'),
  ApiEndpoint('GET', '/chem/dashboard', <String>[], 'ChemController@dashboard', 'Dashboard'),
  ApiEndpoint('GET', '/chem/receipts', <String>[], 'ChemController@receipts', 'Receipts'),
  ApiEndpoint('GET', '/complaints', <String>[], 'ComplaintController@index', 'Root'),
  ApiEndpoint('GET', '/complaints/dashboard', <String>[], 'ComplaintController@dashboard', 'Dashboard'),
  ApiEndpoint('GET', '/complaints/{complaint}', <String>['complaint'], 'ComplaintController@show', 'Complaint'),
  ApiEndpoint('GET', '/customer-view/quick-search', <String>[], 'CustomerViewController@quickSearch', 'Quick Search'),
  ApiEndpoint('GET', '/customer-view/{customerId}', <String>['customerId'], 'CustomerViewController@show', 'CustomerId'),
  ApiEndpoint('GET', '/customer-view/{customerId}/aggregation', <String>['customerId'], 'CustomerViewController@aggregation', 'CustomerId'),
  ApiEndpoint('GET', '/customers', <String>[], 'CustomerController@index', 'Root'),
  ApiEndpoint('GET', '/customers/{customer}', <String>['customer'], 'CustomerController@show', 'Customer'),
  ApiEndpoint('GET', '/customers/{customer}/status-history', <String>['customer'], 'CustomerController@statusHistory', 'Customer'),
  ApiEndpoint('GET', '/dashboard/director', <String>[], 'DashboardController@director', 'Director'),
  ApiEndpoint('GET', '/dashboard/executive-kpi', <String>[], 'DashboardController@executiveKpi', 'Executive Kpi'),
  ApiEndpoint('GET', '/dashboard/finance', <String>[], 'DashboardController@finance', 'Finance'),
  ApiEndpoint('GET', '/dashboard/metx', <String>[], 'DashboardController@metx', 'Metx'),
  ApiEndpoint('GET', '/dashboard/operations', <String>[], 'DashboardController@operations', 'Operations'),
  ApiEndpoint('GET', '/distribution/dma-dashboard', <String>[], 'DistController@dmaDashboard', 'Dma Dashboard'),
  ApiEndpoint('GET', '/documentation', <String>[], 'SwaggerController@api', 'Root'),
  ApiEndpoint('GET', '/documents', <String>[], 'DocumentController@index', 'Root'),
  ApiEndpoint('GET', '/documents/{document}', <String>['document'], 'DocumentController@show', 'Document'),
  ApiEndpoint('GET', '/employees', <String>[], 'HrEmployeeController@index', 'Root'),
  ApiEndpoint('GET', '/employees/dashboard/summary', <String>[], 'HrEmployeeController@dashboard', 'Dashboard'),
  ApiEndpoint('GET', '/employees/{employee}', <String>['employee'], 'HrEmployeeController@show', 'Employee'),
  ApiEndpoint('GET', '/fin/ar-aging-report', <String>[], 'FinController@arAgingReport', 'Ar Aging Report'),
  ApiEndpoint('GET', '/fin/bank-accounts', <String>[], 'FinController@bankAccounts', 'Bank Accounts'),
  ApiEndpoint('GET', '/fin/bank/{account}/summary', <String>['account'], 'FinAdvanceController@bankSummary', 'Bank'),
  ApiEndpoint('GET', '/fin/budgets', <String>[], 'FinController@budgets', 'Budgets'),
  ApiEndpoint('GET', '/fin/currencies', <String>[], 'FinController@currencies', 'Currencies'),
  ApiEndpoint('GET', '/fin/projects', <String>[], 'FinController@projects', 'Projects'),
  ApiEndpoint('GET', '/fin/reconciliations', <String>[], 'FinAdvanceController@reconciliations', 'Reconciliations'),
  ApiEndpoint('GET', '/fin/recurring-transactions', <String>[], 'FinController@recurringTransactions', 'Recurring Transactions'),
  ApiEndpoint('GET', '/fin/sales-invoices', <String>[], 'FinController@salesInvoices', 'Sales Invoices'),
  ApiEndpoint('GET', '/fin/tax-records', <String>[], 'FinController@taxRecords', 'Tax Records'),
  ApiEndpoint('GET', '/gis/customers', <String>[], 'GisController@customerGeoJson', 'Customers'),
  ApiEndpoint('GET', '/gis/customers/status-summary', <String>[], 'GisController@statusSummary', 'Customers'),
  ApiEndpoint('GET', '/gis/features', <String>[], 'GisEditorController@features', 'Features'),
  ApiEndpoint('GET', '/gis/network-edges', <String>[], 'GisEditorController@networkEdges', 'Network Edges'),
  ApiEndpoint('GET', '/gis/pipes', <String>[], 'GisEditorController@pipes', 'Pipes'),
  ApiEndpoint('GET', '/hr/appraisals', <String>[], 'HrTrainingController@appraisals', 'Appraisals'),
  ApiEndpoint('GET', '/hr/attendances', <String>[], 'AttendanceController@index', 'Attendances'),
  ApiEndpoint('GET', '/hr/certifications', <String>[], 'HrTrainingController@certifications', 'Certifications'),
  ApiEndpoint('GET', '/hr/certifications/expiring', <String>[], 'HrTrainingController@expiringCertifications', 'Certifications'),
  ApiEndpoint('GET', '/hr/contracts', <String>[], 'HrAdvanceController@contracts', 'Contracts'),
  ApiEndpoint('GET', '/hr/contracts/terminating-soon', <String>[], 'HrAdvanceController@terminatingSoon', 'Contracts'),
  ApiEndpoint('GET', '/hr/leaves', <String>[], 'AttendanceController@leaves', 'Leaves'),
  ApiEndpoint('GET', '/hr/overtime', <String>[], 'AttendanceController@overtimeRequests', 'Overtime'),
  ApiEndpoint('GET', '/hr/shifts', <String>[], 'AttendanceController@shifts', 'Shifts'),
  ApiEndpoint('GET', '/hr/terminations', <String>[], 'HrAdvanceController@terminations', 'Terminations'),
  ApiEndpoint('GET', '/hr/trainings', <String>[], 'HrTrainingController@trainings', 'Trainings'),
  ApiEndpoint('GET', '/integration-logs', <String>[], 'IntegrationController@logs', 'Root'),
  ApiEndpoint('GET', '/integrations', <String>[], 'IntegrationController@index', 'Root'),
  ApiEndpoint('GET', '/inventory/balance-sheet', <String>[], 'InventoryController@balanceSheet', 'Balance Sheet'),
  ApiEndpoint('GET', '/inventory/valuation', <String>[], 'InventoryController@valuation', 'Valuation'),
  ApiEndpoint('GET', '/iot/dashboard', <String>[], 'IotController@dashboard', 'Dashboard'),
  ApiEndpoint('GET', '/maintenance/records', <String>[], 'MaintenanceController@records', 'Records'),
  ApiEndpoint('GET', '/maintenance/schedules', <String>[], 'MaintenanceController@schedules', 'Schedules'),
  ApiEndpoint('GET', '/marketplace/catalog', <String>[], 'MarketplaceController@catalog', 'Catalog'),
  ApiEndpoint('GET', '/me', <String>[], 'AuthController@me', 'Root'),
  ApiEndpoint('GET', '/meter-anomalies', <String>[], 'MeterAnomalyController@index', 'Root'),
  ApiEndpoint('GET', '/meter-readings/report', <String>[], 'MeterReadingController@report', 'Report'),
  ApiEndpoint('GET', '/meter-readings/route-progress', <String>[], 'MeterReadingController@routeProgress', 'Route Progress'),
  ApiEndpoint('GET', '/meter-routes', <String>[], 'MeterRouteController@index', 'Root'),
  ApiEndpoint('GET', '/meter-routes/{route}', <String>['route'], 'MeterRouteController@show', 'Route'),
  ApiEndpoint('GET', '/meters', <String>[], 'MeterController@index', 'Root'),
  ApiEndpoint('GET', '/meters/{meter}', <String>['meter'], 'MeterController@show', 'Meter'),
  ApiEndpoint('GET', '/metx/dashboard', <String>[], 'MeterAnomalyController@dashboard', 'Dashboard'),
  ApiEndpoint('GET', '/mfa/recovery-codes', <String>[], 'MfaController@getRecoveryCodes', 'Recovery Codes'),
  ApiEndpoint('GET', '/mobile/cities', <String>[], 'MobileDropdownController@cities', 'Cities'),
  ApiEndpoint('GET', '/mobile/districts', <String>[], 'MobileDropdownController@districts', 'Districts'),
  ApiEndpoint('GET', '/mobile/provinces', <String>[], 'MobileDropdownController@provinces', 'Provinces'),
  ApiEndpoint('GET', '/mobile/streets', <String>[], 'MobileDropdownController@streets', 'Streets'),
  ApiEndpoint('GET', '/mobile/villages', <String>[], 'MobileDropdownController@villages', 'Villages'),
  ApiEndpoint('GET', '/notification-preferences', <String>[], 'NotificationController@preferences', 'Root'),
  ApiEndpoint('GET', '/nrw/dashboard', <String>[], 'NrwController@dashboard', 'Dashboard'),
  ApiEndpoint('GET', '/oauth2-callback', <String>[], 'SwaggerController@oauth2Callback', 'Root'),
  ApiEndpoint('GET', '/payments/check/{orderId}', <String>['orderId'], 'PaymentController@checkStatus', 'Check'),
  ApiEndpoint('GET', '/payments/history', <String>[], 'PaymentController@paymentHistory', 'History'),
  ApiEndpoint('GET', '/payments/{payment}/receipt', <String>['payment'], 'PaymentController@downloadReceipt', 'Payment'),
  ApiEndpoint('GET', '/payments/{payment}/refunds', <String>['payment'], 'PaymentController@refunds', 'Payment'),
  ApiEndpoint('GET', '/platform/marketplace/bundles', <String>[], 'MarketplaceController@bundles', 'Marketplace'),
  ApiEndpoint('GET', '/platform/marketplace/catalog', <String>[], 'MarketplaceController@catalog', 'Marketplace'),
  ApiEndpoint('GET', '/platform/marketplace/dashboard', <String>[], 'MarketplaceController@superAdminDashboard', 'Marketplace'),
  ApiEndpoint('GET', '/platform/marketplace/promos', <String>[], 'MarketplaceController@promos', 'Marketplace'),
  ApiEndpoint('GET', '/platform/modules', <String>[], 'ModuleCatalogController@index', 'Modules'),
  ApiEndpoint('GET', '/platform/tenants', <String>[], 'TenantController@index', 'Tenants'),
  ApiEndpoint('GET', '/platform/tenants/{tenant}', <String>['tenant'], 'TenantController@show', 'Tenants'),
  ApiEndpoint('GET', '/platform/tenants/{tenant}/modules', <String>['tenant'], 'TenantModuleController@index', 'Tenants'),
  ApiEndpoint('GET', '/portal/bills', <String>[], 'CustomerPortalController@bills', 'Bills'),
  ApiEndpoint('GET', '/portal/bills/{bill}', <String>['bill'], 'CustomerPortalController@billDetail', 'Bills'),
  ApiEndpoint('GET', '/portal/complaints', <String>[], 'CustomerPortalController@complaints', 'Complaints'),
  ApiEndpoint('GET', '/portal/complaints/{complaint}', <String>['complaint'], 'CustomerPortalController@complaint', 'Complaints'),
  ApiEndpoint('GET', '/portal/consumption-chart', <String>[], 'CustomerPortalController@consumptionChart', 'Consumption Chart'),
  ApiEndpoint('GET', '/portal/dashboard', <String>[], 'CustomerPortalController@dashboard', 'Dashboard'),
  ApiEndpoint('GET', '/portal/notifications', <String>[], 'CustomerPortalController@notifications', 'Notifications'),
  ApiEndpoint('GET', '/portal/profile', <String>[], 'CustomerPortalController@profile', 'Profile'),
  ApiEndpoint('GET', '/portal/usage-history', <String>[], 'CustomerPortalController@consumptionChart', 'Usage History'),
  ApiEndpoint('GET', '/privacy/export-my-data', <String>[], 'DataPrivacyController@exportMyData', 'Export My Data'),
  ApiEndpoint('GET', '/privacy/retention-status', <String>[], 'DataPrivacyController@retentionStatus', 'Retention Status'),
  ApiEndpoint('GET', '/production/dashboard', <String>[], 'ProductionController@dashboard', 'Dashboard'),
  ApiEndpoint('GET', '/production/metrics', <String>[], 'ProductionController@metrics', 'Metrics'),
  ApiEndpoint('GET', '/prospects', <String>[], 'ProspectController@index', 'Root'),
  ApiEndpoint('GET', '/prospects/{prospect}', <String>['prospect'], 'ProspectController@show', 'Prospect'),
  ApiEndpoint('GET', '/prospects/{prospect}/material-orders', <String>['prospect'], 'InstallationController@materialOrders', 'Prospect'),
  ApiEndpoint('GET', '/purchase-orders', <String>[], 'PurchaseOrderController@index', 'Root'),
  ApiEndpoint('GET', '/reports/accounting/balance-sheet', <String>[], 'AccountingReportController@balanceSheet', 'Accounting'),
  ApiEndpoint('GET', '/reports/accounting/cash-flow', <String>[], 'AccountingReportController@cashFlow', 'Accounting'),
  ApiEndpoint('GET', '/reports/accounting/general-ledger', <String>[], 'AccountingReportController@generalLedger', 'Accounting'),
  ApiEndpoint('GET', '/reports/accounting/income-statement', <String>[], 'AccountingReportController@incomeStatement', 'Accounting'),
  ApiEndpoint('GET', '/reports/accounting/trial-balance', <String>[], 'AccountingReportController@trialBalance', 'Accounting'),
  ApiEndpoint('GET', '/roles', <String>[], 'RoleController@index', 'Root'),
  ApiEndpoint('GET', '/session', <String>[], 'AuthController@session', 'Root'),
  ApiEndpoint('GET', '/stock-transfers', <String>[], 'StockTransferController@index', 'Root'),
  ApiEndpoint('GET', '/sync/download', <String>[], 'SyncController@download', 'Download'),
  ApiEndpoint('GET', '/sync/status', <String>[], 'SyncController@status', 'Status'),
  ApiEndpoint('GET', '/tariffs', <String>[], 'TariffController@index', 'Root'),
  ApiEndpoint('GET', '/tariffs/{tariffCategory}', <String>['tariffCategory'], 'TariffController@show', 'TariffCategory'),
  ApiEndpoint('GET', '/tenders', <String>[], 'TenderController@index', 'Root'),
  ApiEndpoint('GET', '/tenders/{tender}', <String>['tender'], 'TenderController@show', 'Tender'),
  ApiEndpoint('GET', '/users', <String>[], 'UserController@index', 'Root'),
  ApiEndpoint('GET', '/vendors', <String>[], 'VendorController@index', 'Root'),
  ApiEndpoint('GET', '/vendors/{vendor}', <String>['vendor'], 'VendorController@show', 'Vendor'),
  ApiEndpoint('GET', '/warehouse/dashboard', <String>[], 'WarehouseController@stockDashboard', 'Dashboard'),
  ApiEndpoint('GET', '/work-orders', <String>[], 'WorkOrderController@index', 'Root'),
  ApiEndpoint('GET', '/work-orders/technician/dashboard', <String>[], 'WorkOrderController@technicianDashboard', 'Technician'),
  ApiEndpoint('GET', '/work-orders/{workOrder}', <String>['workOrder'], 'WorkOrderController@show', 'WorkOrder'),
  ApiEndpoint('GET', '/zones', <String>[], 'ZoneController@index', 'Root'),
  ApiEndpoint('GET', '/zones/dashboard', <String>[], 'ZoneDashboardController@all', 'Dashboard'),
  ApiEndpoint('GET', '/zones/{zone}', <String>['zone'], 'ZoneController@show', 'Zone'),
  ApiEndpoint('GET', '/zones/{zone}/dashboard', <String>['zone'], 'ZoneDashboardController@show', 'Zone'),
  ApiEndpoint('POST', '/address/cities', <String>[], 'AddressController@storeCity', 'Cities'),
  ApiEndpoint('POST', '/address/cities/{id}/restore', <String>['id'], 'AddressController@restoreCity', 'Cities'),
  ApiEndpoint('POST', '/address/districts', <String>[], 'AddressController@storeDistrict', 'Districts'),
  ApiEndpoint('POST', '/address/districts/{id}/restore', <String>['id'], 'AddressController@restoreDistrict', 'Districts'),
  ApiEndpoint('POST', '/address/provinces', <String>[], 'AddressController@storeProvince', 'Provinces'),
  ApiEndpoint('POST', '/address/provinces/{id}/restore', <String>['id'], 'AddressController@restoreProvince', 'Provinces'),
  ApiEndpoint('POST', '/address/streets', <String>[], 'AddressController@storeStreet', 'Streets'),
  ApiEndpoint('POST', '/address/streets/{id}/restore', <String>['id'], 'AddressController@restoreStreet', 'Streets'),
  ApiEndpoint('POST', '/address/villages', <String>[], 'AddressController@storeVillage', 'Villages'),
  ApiEndpoint('POST', '/address/villages/{id}/restore', <String>['id'], 'AddressController@restoreVillage', 'Villages'),
  ApiEndpoint('POST', '/api-keys', <String>[], 'IntegrationController@createApiKey', 'Root'),
  ApiEndpoint('POST', '/api-keys/{apiKey}/revoke', <String>['apiKey'], 'IntegrationController@revokeApiKey', 'ApiKey'),
  ApiEndpoint('POST', '/asset-categories', <String>[], 'AssetCategoryController@store', 'Root'),
  ApiEndpoint('POST', '/assets', <String>[], 'FixedAssetController@store', 'Root'),
  ApiEndpoint('POST', '/assets/capitalize', <String>[], 'FixedAssetController@capitalize', 'Capitalize'),
  ApiEndpoint('POST', '/assets/depreciation/run', <String>[], 'FixedAssetController@runDepreciation', 'Depreciation'),
  ApiEndpoint('POST', '/assets/opname', <String>[], 'AssetOpnameController@opname', 'Opname'),
  ApiEndpoint('POST', '/assets/{asset}/revaluation', <String>['asset'], 'AssetOpnameController@revaluation', 'Asset'),
  ApiEndpoint('POST', '/assets/{fixedAsset}/dispose', <String>['fixedAsset'], 'FixedAssetController@dispose', 'FixedAsset'),
  ApiEndpoint('POST', '/assets/{fixedAsset}/move', <String>['fixedAsset'], 'FixedAssetController@move', 'FixedAsset'),
  ApiEndpoint('POST', '/bi/report-builder', <String>[], 'BiController@reportBuilder', 'Report Builder'),
  ApiEndpoint('POST', '/bi/scheduled-reports', <String>[], 'ScheduledReportController@store', 'Scheduled Reports'),
  ApiEndpoint('POST', '/bi/scheduled-reports/{scheduledReport}/run', <String>['scheduledReport'], 'ScheduledReportController@runNow', 'Scheduled Reports'),
  ApiEndpoint('POST', '/bill-adjustments', <String>[], 'BillAdjustmentController@store', 'Root'),
  ApiEndpoint('POST', '/bill-adjustments/{adjustment}/approve', <String>['adjustment'], 'BillAdjustmentController@approve', 'Adjustment'),
  ApiEndpoint('POST', '/bill-adjustments/{adjustment}/post', <String>['adjustment'], 'BillAdjustmentController@post', 'Adjustment'),
  ApiEndpoint('POST', '/bill-adjustments/{adjustment}/reject', <String>['adjustment'], 'BillAdjustmentController@reject', 'Adjustment'),
  ApiEndpoint('POST', '/bill-audit', <String>[], 'BillRecController@audit', 'Root'),
  ApiEndpoint('POST', '/bill-reconciliation', <String>[], 'BillRecController@reconcile', 'Root'),
  ApiEndpoint('POST', '/bills/generate', <String>[], 'BillingController@generate', 'Generate'),
  ApiEndpoint('POST', '/call-logs', <String>[], 'CallCenterController@log', 'Root'),
  ApiEndpoint('POST', '/chats', <String>[], 'ChatController@store', 'Root'),
  ApiEndpoint('POST', '/chats/{chat}/messages', <String>['chat'], 'ChatController@sendMessage', 'Chat'),
  ApiEndpoint('POST', '/chem/chemicals', <String>[], 'ChemController@storeChemical', 'Chemicals'),
  ApiEndpoint('POST', '/chem/receipts', <String>[], 'ChemController@createReceipt', 'Receipts'),
  ApiEndpoint('POST', '/chem/receipts/{receipt}/qc', <String>['receipt'], 'ChemController@qcTest', 'Receipts'),
  ApiEndpoint('POST', '/chem/usage', <String>[], 'ChemController@usage', 'Usage'),
  ApiEndpoint('POST', '/complaints', <String>[], 'ComplaintController@store', 'Root'),
  ApiEndpoint('POST', '/complaints/{complaint}/assign', <String>['complaint'], 'ComplaintController@assign', 'Complaint'),
  ApiEndpoint('POST', '/complaints/{complaint}/resolve', <String>['complaint'], 'ComplaintController@resolve', 'Complaint'),
  ApiEndpoint('POST', '/customers', <String>[], 'CustomerController@store', 'Root'),
  ApiEndpoint('POST', '/customers/{customer}/disconnect', <String>['customer'], 'CustomerLifecycleController@disconnect', 'Customer'),
  ApiEndpoint('POST', '/customers/{customer}/manual-reading', <String>['customer'], 'CustomerController@manualReading', 'Customer'),
  ApiEndpoint('POST', '/customers/{customer}/reconnect', <String>['customer'], 'CustomerLifecycleController@reconnect', 'Customer'),
  ApiEndpoint('POST', '/customers/{customer}/transfer-ownership', <String>['customer'], 'CustomerLifecycleController@transferOwnership', 'Customer'),
  ApiEndpoint('POST', '/distribution/ingest', <String>[], 'DistController@ingest', 'Ingest'),
  ApiEndpoint('POST', '/documents', <String>[], 'DocumentController@store', 'Root'),
  ApiEndpoint('POST', '/documents/{document}/approve', <String>['document'], 'DocumentController@approve', 'Document'),
  ApiEndpoint('POST', '/employees', <String>[], 'HrEmployeeController@store', 'Root'),
  ApiEndpoint('POST', '/export', <String>[], 'ExportController@export', 'Root'),
  ApiEndpoint('POST', '/fcm-token', <String>[], 'AuthController@registerFcmToken', 'Root'),
  ApiEndpoint('POST', '/files/signed-url', <String>[], 'FileController@signedUrl', 'Signed Url'),
  ApiEndpoint('POST', '/files/upload', <String>[], 'FileController@upload', 'Upload'),
  ApiEndpoint('POST', '/fin/bank-accounts', <String>[], 'FinController@createBankAccount', 'Bank Accounts'),
  ApiEndpoint('POST', '/fin/bank/transfer', <String>[], 'FinAdvanceController@bankTransfer', 'Bank'),
  ApiEndpoint('POST', '/fin/bank/{account}/reconcile', <String>['account'], 'FinAdvanceController@reconcileBank', 'Bank'),
  ApiEndpoint('POST', '/fin/budgets', <String>[], 'FinController@createBudget', 'Budgets'),
  ApiEndpoint('POST', '/fin/projects', <String>[], 'FinController@createProject', 'Projects'),
  ApiEndpoint('POST', '/fin/recurring-transactions', <String>[], 'FinController@createRecurring', 'Recurring Transactions'),
  ApiEndpoint('POST', '/fin/sales-invoices', <String>[], 'FinController@createSalesInvoice', 'Sales Invoices'),
  ApiEndpoint('POST', '/fin/tax/calculate', <String>[], 'FinAdvanceController@taxCalculate', 'Tax'),
  ApiEndpoint('POST', '/fin/tax/efaktur-export', <String>[], 'FinAdvanceController@exportEfaktur', 'Tax'),
  ApiEndpoint('POST', '/fin/tax/record', <String>[], 'FinAdvanceController@taxRecord', 'Tax'),
  ApiEndpoint('POST', '/gis/features', <String>[], 'GisEditorController@createFeature', 'Features'),
  ApiEndpoint('POST', '/gis/network-edges', <String>[], 'GisEditorController@createEdge', 'Network Edges'),
  ApiEndpoint('POST', '/gis/pipes', <String>[], 'GisEditorController@createPipe', 'Pipes'),
  ApiEndpoint('POST', '/hr/appraisals', <String>[], 'HrTrainingController@createAppraisal', 'Appraisals'),
  ApiEndpoint('POST', '/hr/appraisals/{appraisal}/approve', <String>['appraisal'], 'HrTrainingController@approveAppraisal', 'Appraisals'),
  ApiEndpoint('POST', '/hr/attendances/check-in', <String>[], 'AttendanceController@checkIn', 'Attendances'),
  ApiEndpoint('POST', '/hr/attendances/check-out', <String>[], 'AttendanceController@checkOut', 'Attendances'),
  ApiEndpoint('POST', '/hr/certifications', <String>[], 'HrTrainingController@createCertification', 'Certifications'),
  ApiEndpoint('POST', '/hr/contracts', <String>[], 'HrAdvanceController@createContract', 'Contracts'),
  ApiEndpoint('POST', '/hr/leaves', <String>[], 'AttendanceController@requestLeave', 'Leaves'),
  ApiEndpoint('POST', '/hr/leaves/{leave}/approve', <String>['leave'], 'AttendanceController@approveLeave', 'Leaves'),
  ApiEndpoint('POST', '/hr/overtime', <String>[], 'AttendanceController@requestOvertime', 'Overtime'),
  ApiEndpoint('POST', '/hr/overtime/{overtime}/approve', <String>['overtime'], 'AttendanceController@approveOvertime', 'Overtime'),
  ApiEndpoint('POST', '/hr/shifts', <String>[], 'AttendanceController@createShift', 'Shifts'),
  ApiEndpoint('POST', '/hr/terminations', <String>[], 'HrAdvanceController@createTermination', 'Terminations'),
  ApiEndpoint('POST', '/hr/terminations/{termination}/approve', <String>['termination'], 'HrAdvanceController@approveTermination', 'Terminations'),
  ApiEndpoint('POST', '/hr/trainings', <String>[], 'HrTrainingController@createTraining', 'Trainings'),
  ApiEndpoint('POST', '/hr/trainings/{training}/participants', <String>['training'], 'HrTrainingController@addParticipant', 'Trainings'),
  ApiEndpoint('POST', '/installations/{schedule}/complete', <String>['schedule'], 'InstallationController@complete', 'Schedule'),
  ApiEndpoint('POST', '/integrations', <String>[], 'IntegrationController@store', 'Root'),
  ApiEndpoint('POST', '/integrations/test-send', <String>[], 'IntegrationController@testSend', 'Test Send'),
  ApiEndpoint('POST', '/integrations/{integration}/toggle', <String>['integration'], 'IntegrationController@toggle', 'Integration'),
  ApiEndpoint('POST', '/iot/ingest', <String>[], 'IotController@ingest', 'Ingest'),
  ApiEndpoint('POST', '/journal/manual', <String>[], 'InventoryController@manualJournal', 'Manual'),
  ApiEndpoint('POST', '/login', <String>[], 'AuthController@login', 'Root'),
  ApiEndpoint('POST', '/logout', <String>[], 'AuthController@logout', 'Root'),
  ApiEndpoint('POST', '/maintenance/records', <String>[], 'MaintenanceController@createRecord', 'Records'),
  ApiEndpoint('POST', '/maintenance/schedules', <String>[], 'MaintenanceController@createSchedule', 'Schedules'),
  ApiEndpoint('POST', '/marketplace/purchases', <String>[], 'MarketplaceController@purchase', 'Purchases'),
  ApiEndpoint('POST', '/material-orders/{order}/cancel', <String>['order'], 'InstallationController@cancelMaterials', 'Order'),
  ApiEndpoint('POST', '/material-orders/{order}/issue', <String>['order'], 'InstallationController@issueMaterials', 'Order'),
  ApiEndpoint('POST', '/meter-anomalies/scan', <String>[], 'MeterAnomalyController@scan', 'Scan'),
  ApiEndpoint('POST', '/meter-anomalies/{anomaly}/confirm', <String>['anomaly'], 'MeterAnomalyController@confirm', 'Anomaly'),
  ApiEndpoint('POST', '/meter-anomalies/{anomaly}/dismiss', <String>['anomaly'], 'MeterAnomalyController@dismiss', 'Anomaly'),
  ApiEndpoint('POST', '/meter-anomalies/{anomaly}/review', <String>['anomaly'], 'MeterAnomalyController@review', 'Anomaly'),
  ApiEndpoint('POST', '/meter-readings', <String>[], 'MeterReadingController@store', 'Root'),
  ApiEndpoint('POST', '/meter-readings/parse', <String>[], 'MeterReadingController@parseMeter', 'Parse'),
  ApiEndpoint('POST', '/meter-readings/{reading}/verify', <String>['reading'], 'MeterReadingController@verify', 'Reading'),
  ApiEndpoint('POST', '/meter-routes', <String>[], 'MeterRouteController@store', 'Root'),
  ApiEndpoint('POST', '/meter-routes/auto-map', <String>[], 'MeterRouteController@autoMap', 'Auto Map'),
  ApiEndpoint('POST', '/meter-routes/{route}/officer', <String>['route'], 'MeterRouteController@assignOfficer', 'Route'),
  ApiEndpoint('POST', '/meter-routes/{route}/streets', <String>['route'], 'MeterRouteController@syncStreets', 'Route'),
  ApiEndpoint('POST', '/meters', <String>[], 'MeterController@store', 'Root'),
  ApiEndpoint('POST', '/meters/{meter}/assign', <String>['meter'], 'MeterController@assign', 'Meter'),
  ApiEndpoint('POST', '/meters/{meter}/recalibrate', <String>['meter'], 'MeterController@recalibrate', 'Meter'),
  ApiEndpoint('POST', '/meters/{meter}/remove', <String>['meter'], 'MeterController@remove', 'Meter'),
  ApiEndpoint('POST', '/meters/{meter}/scrap', <String>['meter'], 'MeterController@scrap', 'Meter'),
  ApiEndpoint('POST', '/mfa/disable', <String>[], 'MfaController@disable', 'Disable'),
  ApiEndpoint('POST', '/mfa/enable', <String>[], 'MfaController@enable', 'Enable'),
  ApiEndpoint('POST', '/mfa/recovery-codes/regenerate', <String>[], 'MfaController@regenerateRecoveryCodes', 'Recovery Codes'),
  ApiEndpoint('POST', '/mfa/setup', <String>[], 'MfaController@setup', 'Setup'),
  ApiEndpoint('POST', '/mfa/verify', <String>[], 'MfaController@verify', 'Verify'),
  ApiEndpoint('POST', '/notification-preferences/test-push', <String>[], 'NotificationController@testPush', 'Test Push'),
  ApiEndpoint('POST', '/nrw/calculate', <String>[], 'NrwController@calculate', 'Calculate'),
  ApiEndpoint('POST', '/payments', <String>[], 'PaymentController@createPayment', 'Root'),
  ApiEndpoint('POST', '/payments/cash', <String>[], 'PaymentController@cashPayment', 'Cash'),
  ApiEndpoint('POST', '/payments/{payment}/refund', <String>['payment'], 'PaymentController@refund', 'Payment'),
  ApiEndpoint('POST', '/payroll/batch-run', <String>[], 'PayrollController@batchRun', 'Batch Run'),
  ApiEndpoint('POST', '/payroll/calculate', <String>[], 'PayrollController@calculate', 'Calculate'),
  ApiEndpoint('POST', '/payroll/slip', <String>[], 'PayrollController@slip', 'Slip'),
  ApiEndpoint('POST', '/platform/login', <String>[], 'PlatformAuthController@login', 'Login'),
  ApiEndpoint('POST', '/platform/logout', <String>[], 'PlatformAuthController@logout', 'Logout'),
  ApiEndpoint('POST', '/platform/marketplace/bundles', <String>[], 'MarketplaceController@createBundle', 'Marketplace'),
  ApiEndpoint('POST', '/platform/marketplace/prices', <String>[], 'MarketplaceController@managePrice', 'Marketplace'),
  ApiEndpoint('POST', '/platform/marketplace/promos', <String>[], 'MarketplaceController@createPromo', 'Marketplace'),
  ApiEndpoint('POST', '/platform/marketplace/purchase', <String>[], 'MarketplaceController@purchase', 'Marketplace'),
  ApiEndpoint('POST', '/platform/tenants', <String>[], 'TenantController@store', 'Tenants'),
  ApiEndpoint('POST', '/platform/tenants/{tenant}/modules/{moduleCode}/activate', <String>['tenant', 'moduleCode'], 'TenantModuleController@activate', 'Tenants'),
  ApiEndpoint('POST', '/platform/tenants/{tenant}/modules/{moduleCode}/lock', <String>['tenant', 'moduleCode'], 'TenantModuleController@lock', 'Tenants'),
  ApiEndpoint('POST', '/platform/tenants/{tenant}/toggle-status', <String>['tenant'], 'TenantController@toggleStatus', 'Tenants'),
  ApiEndpoint('POST', '/portal/bills/{bill}/pay', <String>['bill'], 'CustomerPortalController@payBill', 'Bills'),
  ApiEndpoint('POST', '/portal/complaints', <String>[], 'CustomerPortalController@createComplaint', 'Complaints'),
  ApiEndpoint('POST', '/portal/notifications/read-all', <String>[], 'CustomerPortalController@markAllNotificationsRead', 'Notifications'),
  ApiEndpoint('POST', '/portal/notifications/{notificationId}/read', <String>['notificationId'], 'CustomerPortalController@markNotificationRead', 'Notifications'),
  ApiEndpoint('POST', '/privacy/delete-my-data', <String>[], 'DataPrivacyController@deleteMyData', 'Delete My Data'),
  ApiEndpoint('POST', '/privacy/purge-old-data', <String>[], 'DataPrivacyController@purgeOldData', 'Purge Old Data'),
  ApiEndpoint('POST', '/privacy/purge-old-data/{purgeRequest}/approve', <String>['purgeRequest'], 'DataPrivacyController@approvePurge', 'Purge Old Data'),
  ApiEndpoint('POST', '/production/ingest', <String>[], 'ProductionController@ingest', 'Ingest'),
  ApiEndpoint('POST', '/prospects', <String>[], 'ProspectController@store', 'Root'),
  ApiEndpoint('POST', '/prospects/parse-ktp', <String>[], 'ProspectController@parseKtp', 'Parse Ktp'),
  ApiEndpoint('POST', '/prospects/upload-ktp', <String>[], 'ProspectController@uploadKtp', 'Upload Ktp'),
  ApiEndpoint('POST', '/prospects/{prospect}/activate', <String>['prospect'], 'InstallationController@activate', 'Prospect'),
  ApiEndpoint('POST', '/prospects/{prospect}/assign-surveyor', <String>['prospect'], 'ProspectController@assignSurveyor', 'Prospect'),
  ApiEndpoint('POST', '/prospects/{prospect}/order-materials', <String>['prospect'], 'InstallationController@orderMaterials', 'Prospect'),
  ApiEndpoint('POST', '/prospects/{prospect}/pay-installation', <String>['prospect'], 'ProspectController@payInstallation', 'Prospect'),
  ApiEndpoint('POST', '/prospects/{prospect}/schedule-installation', <String>['prospect'], 'InstallationController@schedule', 'Prospect'),
  ApiEndpoint('POST', '/prospects/{prospect}/survey', <String>['prospect'], 'ProspectController@submitSurvey', 'Prospect'),
  ApiEndpoint('POST', '/purchase-orders', <String>[], 'PurchaseOrderController@store', 'Root'),
  ApiEndpoint('POST', '/purchase-orders/{po}/approve', <String>['po'], 'PurchaseOrderController@approve', 'Po'),
  ApiEndpoint('POST', '/purchase-orders/{po}/purchase', <String>['po'], 'PurchaseOrderController@purchase', 'Po'),
  ApiEndpoint('POST', '/purchase-orders/{po}/receive', <String>['po'], 'PurchaseOrderController@receive', 'Po'),
  ApiEndpoint('POST', '/reading-periods', <String>[], 'MeterReadingController@openPeriod', 'Root'),
  ApiEndpoint('POST', '/reading-periods/{period}/close', <String>['period'], 'MeterReadingController@closePeriod', 'Period'),
  ApiEndpoint('POST', '/refresh-token', <String>[], 'AuthController@refreshToken', 'Root'),
  ApiEndpoint('POST', '/roles', <String>[], 'RoleController@store', 'Root'),
  ApiEndpoint('POST', '/stock-transfers', <String>[], 'StockTransferController@store', 'Root'),
  ApiEndpoint('POST', '/stock-transfers/{transfer}/approve', <String>['transfer'], 'StockTransferController@approve', 'Transfer'),
  ApiEndpoint('POST', '/stock-transfers/{transfer}/receive', <String>['transfer'], 'StockTransferController@receive', 'Transfer'),
  ApiEndpoint('POST', '/survey-reports/{report}/review', <String>['report'], 'ProspectController@reviewSurvey', 'Report'),
  ApiEndpoint('POST', '/sync/upload', <String>[], 'SyncController@upload', 'Upload'),
  ApiEndpoint('POST', '/tariffs', <String>[], 'TariffController@store', 'Root'),
  ApiEndpoint('POST', '/tenders', <String>[], 'TenderController@store', 'Root'),
  ApiEndpoint('POST', '/tenders/{tender}/award', <String>['tender'], 'TenderController@award', 'Tender'),
  ApiEndpoint('POST', '/tenders/{tender}/bid', <String>['tender'], 'TenderController@submitBid', 'Tender'),
  ApiEndpoint('POST', '/tenders/{tender}/evaluate', <String>['tender'], 'TenderController@evaluate', 'Tender'),
  ApiEndpoint('POST', '/users', <String>[], 'UserController@store', 'Root'),
  ApiEndpoint('POST', '/users/{user}/roles', <String>['user'], 'UserController@syncRoles', 'User'),
  ApiEndpoint('POST', '/users/{user}/zone', <String>['user'], 'UserController@assignZone', 'User'),
  ApiEndpoint('POST', '/vendors', <String>[], 'VendorController@store', 'Root'),
  ApiEndpoint('POST', '/vendors/{vendor}/evaluate', <String>['vendor'], 'VendorController@evaluate', 'Vendor'),
  ApiEndpoint('POST', '/warehouse/adjustment', <String>[], 'WarehouseController@stockAdjustment', 'Adjustment'),
  ApiEndpoint('POST', '/warehouse/stock-out', <String>[], 'WarehouseController@stockOut', 'Stock Out'),
  ApiEndpoint('POST', '/webhook', <String>[], 'IntegrationController@webhook', 'Root'),
  ApiEndpoint('POST', '/webhooks/midtrans', <String>[], 'PaymentWebhookController@handle', 'Midtrans'),
  ApiEndpoint('POST', '/webhooks/xendit', <String>[], 'XenditWebhookController@handle', 'Xendit'),
  ApiEndpoint('POST', '/work-orders', <String>[], 'WorkOrderController@store', 'Root'),
  ApiEndpoint('POST', '/work-orders/{workOrder}/assign', <String>['workOrder'], 'WorkOrderController@assign', 'WorkOrder'),
  ApiEndpoint('POST', '/work-orders/{workOrder}/complete', <String>['workOrder'], 'WorkOrderController@complete', 'WorkOrder'),
  ApiEndpoint('POST', '/work-orders/{workOrder}/start', <String>['workOrder'], 'WorkOrderController@start', 'WorkOrder'),
  ApiEndpoint('POST', '/zones', <String>[], 'ZoneController@store', 'Root'),
  ApiEndpoint('POST', '/zones/{zone}/deactivate', <String>['zone'], 'ZoneController@deactivate', 'Zone'),
  ApiEndpoint('PUT', '/address/cities/{city}', <String>['city'], 'AddressController@updateCity', 'Cities'),
  ApiEndpoint('PUT', '/address/districts/{district}', <String>['district'], 'AddressController@updateDistrict', 'Districts'),
  ApiEndpoint('PUT', '/address/provinces/{province}', <String>['province'], 'AddressController@updateProvince', 'Provinces'),
  ApiEndpoint('PUT', '/address/streets/{street}', <String>['street'], 'AddressController@updateStreet', 'Streets'),
  ApiEndpoint('PUT', '/address/villages/{village}', <String>['village'], 'AddressController@updateVillage', 'Villages'),
  ApiEndpoint('PUT', '/asset-categories/{assetCategory}', <String>['assetCategory'], 'AssetCategoryController@update', 'AssetCategory'),
  ApiEndpoint('PUT', '/bi/scheduled-reports/{scheduledReport}', <String>['scheduledReport'], 'ScheduledReportController@update', 'Scheduled Reports'),
  ApiEndpoint('PUT', '/change-password', <String>[], 'AuthController@changePassword', 'Root'),
  ApiEndpoint('PUT', '/customers/{customer}', <String>['customer'], 'CustomerController@update', 'Customer'),
  ApiEndpoint('PUT', '/employees/{employee}', <String>['employee'], 'HrEmployeeController@update', 'Employee'),
  ApiEndpoint('PUT', '/gis/pipes/{pipe}', <String>['pipe'], 'GisEditorController@updatePipe', 'Pipes'),
  ApiEndpoint('PUT', '/hr/trainings/{training}/participants/{participant}', <String>['training', 'participant'], 'HrTrainingController@updateParticipant', 'Trainings'),
  ApiEndpoint('PUT', '/maintenance/schedules/{schedule}', <String>['schedule'], 'MaintenanceController@updateSchedule', 'Schedules'),
  ApiEndpoint('PUT', '/meter-routes/{route}', <String>['route'], 'MeterRouteController@update', 'Route'),
  ApiEndpoint('PUT', '/meters/{meter}', <String>['meter'], 'MeterController@update', 'Meter'),
  ApiEndpoint('PUT', '/notification-preferences', <String>[], 'NotificationController@updatePreferences', 'Root'),
  ApiEndpoint('PUT', '/portal/profile', <String>[], 'CustomerPortalController@updateProfile', 'Profile'),
  ApiEndpoint('PUT', '/profile/update', <String>[], 'AuthController@updateProfile', 'Update'),
  ApiEndpoint('PUT', '/prospects/{prospect}', <String>['prospect'], 'ProspectController@update', 'Prospect'),
  ApiEndpoint('PUT', '/roles/{role}', <String>['role'], 'RoleController@update', 'Role'),
  ApiEndpoint('PUT', '/tariffs/{tariffCategory}', <String>['tariffCategory'], 'TariffController@update', 'TariffCategory'),
  ApiEndpoint('PUT', '/users/{user}', <String>['user'], 'UserController@update', 'User'),
  ApiEndpoint('PUT', '/vendors/{vendor}', <String>['vendor'], 'VendorController@update', 'Vendor'),
  ApiEndpoint('PUT', '/zones/{zone}', <String>['zone'], 'ZoneController@update', 'Zone'),
];

/// Dio client per tag (method get/post/put/patch/delete typed).
class PdamApiClient {
  PdamApiClient(this._dio);
  final Dio _dio;

  Future<Response<T>> request<T>(String method, String path, {Map<String, dynamic>? body, Map<String, dynamic>? query}) =>
      _dio.request<T>('/api/v1$path', data: body, queryParameters: query, options: Options(method: method.toUpperCase()));

  Future<Response<T>> getJson<T>(String path, {Map<String, dynamic>? query}) => request<T>('GET', path, query: query);
}

class GeneratedApi {
  GeneratedApi(this._dio);
  final Dio _dio;

  /// [DELETE] /address/cities/{city} - AddressController@destroyCity
  Future<Response<dynamic>> citiesapideleteaddressCitiesId({required String city}) {
    final url = '/api/v1/address/cities/${city}';
    return _dio.request<dynamic>(url, options: Options(method: 'DELETE'));
  }

  /// [DELETE] /address/districts/{district} - AddressController@destroyDistrict
  Future<Response<dynamic>> districtsapideleteaddressDistrictsId({required String district}) {
    final url = '/api/v1/address/districts/${district}';
    return _dio.request<dynamic>(url, options: Options(method: 'DELETE'));
  }

  /// [DELETE] /address/provinces/{province} - AddressController@destroyProvince
  Future<Response<dynamic>> provincesapideleteaddressProvincesId({required String province}) {
    final url = '/api/v1/address/provinces/${province}';
    return _dio.request<dynamic>(url, options: Options(method: 'DELETE'));
  }

  /// [DELETE] /address/streets/{street} - AddressController@destroyStreet
  Future<Response<dynamic>> streetsapideleteaddressStreetsId({required String street}) {
    final url = '/api/v1/address/streets/${street}';
    return _dio.request<dynamic>(url, options: Options(method: 'DELETE'));
  }

  /// [DELETE] /address/villages/{village} - AddressController@destroyVillage
  Future<Response<dynamic>> villagesapideleteaddressVillagesId({required String village}) {
    final url = '/api/v1/address/villages/${village}';
    return _dio.request<dynamic>(url, options: Options(method: 'DELETE'));
  }

  /// [DELETE] /bi/scheduled-reports/{scheduledReport} - ScheduledReportController@destroy
  Future<Response<dynamic>> scheduledreportsapideletebiScheduledReportsId({required String scheduledReport}) {
    final url = '/api/v1/bi/scheduled-reports/${scheduledReport}';
    return _dio.request<dynamic>(url, options: Options(method: 'DELETE'));
  }

  /// [DELETE] /customers/{customer} - CustomerController@destroy
  Future<Response<dynamic>> customerapideletecustomersId({required String customer}) {
    final url = '/api/v1/customers/${customer}';
    return _dio.request<dynamic>(url, options: Options(method: 'DELETE'));
  }

  /// [DELETE] /meter-routes/{route} - MeterRouteController@destroy
  Future<Response<dynamic>> routeapideletemeterRoutesId({required String route}) {
    final url = '/api/v1/meter-routes/${route}';
    return _dio.request<dynamic>(url, options: Options(method: 'DELETE'));
  }

  /// [DELETE] /prospects/{prospect} - ProspectController@destroy
  Future<Response<dynamic>> prospectapideleteprospectsId({required String prospect}) {
    final url = '/api/v1/prospects/${prospect}';
    return _dio.request<dynamic>(url, options: Options(method: 'DELETE'));
  }

  /// [DELETE] /roles/{role} - RoleController@destroy
  Future<Response<dynamic>> roleapideleterolesId({required String role}) {
    final url = '/api/v1/roles/${role}';
    return _dio.request<dynamic>(url, options: Options(method: 'DELETE'));
  }

  /// [DELETE] /tariffs/{tariffCategory} - TariffController@destroy
  Future<Response<dynamic>> tariffcategoryapideletetariffsId({required String tariffCategory}) {
    final url = '/api/v1/tariffs/${tariffCategory}';
    return _dio.request<dynamic>(url, options: Options(method: 'DELETE'));
  }

  /// [DELETE] /zones/{zone} - ZoneController@destroy
  Future<Response<dynamic>> zoneapideletezonesId({required String zone}) {
    final url = '/api/v1/zones/${zone}';
    return _dio.request<dynamic>(url, options: Options(method: 'DELETE'));
  }

  /// [GET] /address/cities - AddressController@cities
  Future<Response<dynamic>> citiesapigetaddressCities({Map<String, dynamic>? query}) {
    const url = '/api/v1/address/cities';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /address/districts - AddressController@districts
  Future<Response<dynamic>> districtsapigetaddressDistricts({Map<String, dynamic>? query}) {
    const url = '/api/v1/address/districts';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /address/provinces - AddressController@provinces
  Future<Response<dynamic>> provincesapigetaddressProvinces({Map<String, dynamic>? query}) {
    const url = '/api/v1/address/provinces';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /address/streets - AddressController@streets
  Future<Response<dynamic>> streetsapigetaddressStreets({Map<String, dynamic>? query}) {
    const url = '/api/v1/address/streets';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /address/villages - AddressController@villages
  Future<Response<dynamic>> villagesapigetaddressVillages({Map<String, dynamic>? query}) {
    const url = '/api/v1/address/villages';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /api-keys - IntegrationController@apiKeys
  Future<Response<dynamic>> rootapigetapiKeys({Map<String, dynamic>? query}) {
    const url = '/api/v1/api-keys';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /asset-categories - AssetCategoryController@index
  Future<Response<dynamic>> rootapigetassetCategories({Map<String, dynamic>? query}) {
    const url = '/api/v1/asset-categories';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /assets - FixedAssetController@index
  Future<Response<dynamic>> rootapigetassets({Map<String, dynamic>? query}) {
    const url = '/api/v1/assets';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /assets/dashboard - FixedAssetController@dashboard
  Future<Response<dynamic>> dashboardapigetassetsDashboard({Map<String, dynamic>? query}) {
    const url = '/api/v1/assets/dashboard';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /assets/{fixedAsset} - FixedAssetController@show
  Future<Response<dynamic>> fixedassetapigetassetsId({required String fixedAsset, Map<String, dynamic>? query}) {
    final url = '/api/v1/assets/${fixedAsset}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /assets/{fixedAsset}/card - FixedAssetController@card
  Future<Response<dynamic>> fixedassetapigetassetsIdCard({required String fixedAsset, Map<String, dynamic>? query}) {
    final url = '/api/v1/assets/${fixedAsset}/card';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /bi/kpi-eksekutif - BiController@kpiEkskutif
  Future<Response<dynamic>> kpieksekutifapigetbiKpiEksekutif({Map<String, dynamic>? query}) {
    const url = '/api/v1/bi/kpi-eksekutif';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /bi/scheduled-reports - ScheduledReportController@index
  Future<Response<dynamic>> scheduledreportsapigetbiScheduledReports({Map<String, dynamic>? query}) {
    const url = '/api/v1/bi/scheduled-reports';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /bi/scheduled-reports/{scheduledReport}/runs - ScheduledReportController@history
  Future<Response<dynamic>> scheduledreportsapigetbiScheduledReportsIdRuns({required String scheduledReport, Map<String, dynamic>? query}) {
    final url = '/api/v1/bi/scheduled-reports/${scheduledReport}/runs';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /bi/scheduled-reports/{scheduledReport}/runs/{run}/download - ScheduledReportController@download
  Future<Response<dynamic>> scheduledreportsapigetbiScheduledReportsIdRunsIdDownload({required String scheduledReport, required String run, Map<String, dynamic>? query}) {
    final url = '/api/v1/bi/scheduled-reports/${scheduledReport}/runs/${run}/download';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /bill-adjustments - BillAdjustmentController@index
  Future<Response<dynamic>> rootapigetbillAdjustments({Map<String, dynamic>? query}) {
    const url = '/api/v1/bill-adjustments';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /bills - BillingController@index
  Future<Response<dynamic>> rootapigetbills({Map<String, dynamic>? query}) {
    const url = '/api/v1/bills';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /bills/{bill} - BillingController@show
  Future<Response<dynamic>> billapigetbillsId({required String bill, Map<String, dynamic>? query}) {
    final url = '/api/v1/bills/${bill}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /call-logs - CallCenterController@index
  Future<Response<dynamic>> rootapigetcallLogs({Map<String, dynamic>? query}) {
    const url = '/api/v1/call-logs';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /call-logs/dashboard - CallCenterController@dashboard
  Future<Response<dynamic>> dashboardapigetcallLogsDashboard({Map<String, dynamic>? query}) {
    const url = '/api/v1/call-logs/dashboard';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /chats - ChatController@index
  Future<Response<dynamic>> rootapigetchats({Map<String, dynamic>? query}) {
    const url = '/api/v1/chats';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /chats/{chat}/messages - ChatController@messages
  Future<Response<dynamic>> chatapigetchatsIdMessages({required String chat, Map<String, dynamic>? query}) {
    final url = '/api/v1/chats/${chat}/messages';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /chem/chemicals - ChemController@chemicals
  Future<Response<dynamic>> chemicalsapigetchemChemicals({Map<String, dynamic>? query}) {
    const url = '/api/v1/chem/chemicals';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /chem/dashboard - ChemController@dashboard
  Future<Response<dynamic>> dashboardapigetchemDashboard({Map<String, dynamic>? query}) {
    const url = '/api/v1/chem/dashboard';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /chem/receipts - ChemController@receipts
  Future<Response<dynamic>> receiptsapigetchemReceipts({Map<String, dynamic>? query}) {
    const url = '/api/v1/chem/receipts';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /complaints - ComplaintController@index
  Future<Response<dynamic>> rootapigetcomplaints({Map<String, dynamic>? query}) {
    const url = '/api/v1/complaints';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /complaints/dashboard - ComplaintController@dashboard
  Future<Response<dynamic>> dashboardapigetcomplaintsDashboard({Map<String, dynamic>? query}) {
    const url = '/api/v1/complaints/dashboard';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /complaints/{complaint} - ComplaintController@show
  Future<Response<dynamic>> complaintapigetcomplaintsId({required String complaint, Map<String, dynamic>? query}) {
    final url = '/api/v1/complaints/${complaint}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /customer-view/quick-search - CustomerViewController@quickSearch
  Future<Response<dynamic>> quicksearchapigetcustomerViewQuickSearch({Map<String, dynamic>? query}) {
    const url = '/api/v1/customer-view/quick-search';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /customer-view/{customerId} - CustomerViewController@show
  Future<Response<dynamic>> customeridapigetcustomerViewId({required String customerId, Map<String, dynamic>? query}) {
    final url = '/api/v1/customer-view/${customerId}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /customer-view/{customerId}/aggregation - CustomerViewController@aggregation
  Future<Response<dynamic>> customeridapigetcustomerViewIdAggregation({required String customerId, Map<String, dynamic>? query}) {
    final url = '/api/v1/customer-view/${customerId}/aggregation';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /customers - CustomerController@index
  Future<Response<dynamic>> rootapigetcustomers({Map<String, dynamic>? query}) {
    const url = '/api/v1/customers';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /customers/{customer} - CustomerController@show
  Future<Response<dynamic>> customerapigetcustomersId({required String customer, Map<String, dynamic>? query}) {
    final url = '/api/v1/customers/${customer}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /customers/{customer}/status-history - CustomerController@statusHistory
  Future<Response<dynamic>> customerapigetcustomersIdStatusHistory({required String customer, Map<String, dynamic>? query}) {
    final url = '/api/v1/customers/${customer}/status-history';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /dashboard/director - DashboardController@director
  Future<Response<dynamic>> directorapigetdashboardDirector({Map<String, dynamic>? query}) {
    const url = '/api/v1/dashboard/director';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /dashboard/executive-kpi - DashboardController@executiveKpi
  Future<Response<dynamic>> executivekpiapigetdashboardExecutiveKpi({Map<String, dynamic>? query}) {
    const url = '/api/v1/dashboard/executive-kpi';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /dashboard/finance - DashboardController@finance
  Future<Response<dynamic>> financeapigetdashboardFinance({Map<String, dynamic>? query}) {
    const url = '/api/v1/dashboard/finance';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /dashboard/metx - DashboardController@metx
  Future<Response<dynamic>> metxapigetdashboardMetx({Map<String, dynamic>? query}) {
    const url = '/api/v1/dashboard/metx';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /dashboard/operations - DashboardController@operations
  Future<Response<dynamic>> operationsapigetdashboardOperations({Map<String, dynamic>? query}) {
    const url = '/api/v1/dashboard/operations';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /distribution/dma-dashboard - DistController@dmaDashboard
  Future<Response<dynamic>> dmadashboardapigetdistributionDmaDashboard({Map<String, dynamic>? query}) {
    const url = '/api/v1/distribution/dma-dashboard';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /documentation - SwaggerController@api
  Future<Response<dynamic>> rootapigetdocumentation({Map<String, dynamic>? query}) {
    const url = '/api/v1/documentation';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /documents - DocumentController@index
  Future<Response<dynamic>> rootapigetdocuments({Map<String, dynamic>? query}) {
    const url = '/api/v1/documents';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /documents/{document} - DocumentController@show
  Future<Response<dynamic>> documentapigetdocumentsId({required String document, Map<String, dynamic>? query}) {
    final url = '/api/v1/documents/${document}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /employees - HrEmployeeController@index
  Future<Response<dynamic>> rootapigetemployees({Map<String, dynamic>? query}) {
    const url = '/api/v1/employees';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /employees/dashboard/summary - HrEmployeeController@dashboard
  Future<Response<dynamic>> dashboardapigetemployeesDashboardSummary({Map<String, dynamic>? query}) {
    const url = '/api/v1/employees/dashboard/summary';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /employees/{employee} - HrEmployeeController@show
  Future<Response<dynamic>> employeeapigetemployeesId({required String employee, Map<String, dynamic>? query}) {
    final url = '/api/v1/employees/${employee}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /fin/ar-aging-report - FinController@arAgingReport
  Future<Response<dynamic>> aragingreportapigetfinArAgingReport({Map<String, dynamic>? query}) {
    const url = '/api/v1/fin/ar-aging-report';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /fin/bank-accounts - FinController@bankAccounts
  Future<Response<dynamic>> bankaccountsapigetfinBankAccounts({Map<String, dynamic>? query}) {
    const url = '/api/v1/fin/bank-accounts';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /fin/bank/{account}/summary - FinAdvanceController@bankSummary
  Future<Response<dynamic>> bankapigetfinBankIdSummary({required String account, Map<String, dynamic>? query}) {
    final url = '/api/v1/fin/bank/${account}/summary';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /fin/budgets - FinController@budgets
  Future<Response<dynamic>> budgetsapigetfinBudgets({Map<String, dynamic>? query}) {
    const url = '/api/v1/fin/budgets';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /fin/currencies - FinController@currencies
  Future<Response<dynamic>> currenciesapigetfinCurrencies({Map<String, dynamic>? query}) {
    const url = '/api/v1/fin/currencies';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /fin/projects - FinController@projects
  Future<Response<dynamic>> projectsapigetfinProjects({Map<String, dynamic>? query}) {
    const url = '/api/v1/fin/projects';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /fin/reconciliations - FinAdvanceController@reconciliations
  Future<Response<dynamic>> reconciliationsapigetfinReconciliations({Map<String, dynamic>? query}) {
    const url = '/api/v1/fin/reconciliations';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /fin/recurring-transactions - FinController@recurringTransactions
  Future<Response<dynamic>> recurringtransactionsapigetfinRecurringTransactions({Map<String, dynamic>? query}) {
    const url = '/api/v1/fin/recurring-transactions';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /fin/sales-invoices - FinController@salesInvoices
  Future<Response<dynamic>> salesinvoicesapigetfinSalesInvoices({Map<String, dynamic>? query}) {
    const url = '/api/v1/fin/sales-invoices';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /fin/tax-records - FinController@taxRecords
  Future<Response<dynamic>> taxrecordsapigetfinTaxRecords({Map<String, dynamic>? query}) {
    const url = '/api/v1/fin/tax-records';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /gis/customers - GisController@customerGeoJson
  Future<Response<dynamic>> customersapigetgisCustomers({Map<String, dynamic>? query}) {
    const url = '/api/v1/gis/customers';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /gis/customers/status-summary - GisController@statusSummary
  Future<Response<dynamic>> customersapigetgisCustomersStatusSummary({Map<String, dynamic>? query}) {
    const url = '/api/v1/gis/customers/status-summary';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /gis/features - GisEditorController@features
  Future<Response<dynamic>> featuresapigetgisFeatures({Map<String, dynamic>? query}) {
    const url = '/api/v1/gis/features';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /gis/network-edges - GisEditorController@networkEdges
  Future<Response<dynamic>> networkedgesapigetgisNetworkEdges({Map<String, dynamic>? query}) {
    const url = '/api/v1/gis/network-edges';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /gis/pipes - GisEditorController@pipes
  Future<Response<dynamic>> pipesapigetgisPipes({Map<String, dynamic>? query}) {
    const url = '/api/v1/gis/pipes';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /hr/appraisals - HrTrainingController@appraisals
  Future<Response<dynamic>> appraisalsapigethrAppraisals({Map<String, dynamic>? query}) {
    const url = '/api/v1/hr/appraisals';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /hr/attendances - AttendanceController@index
  Future<Response<dynamic>> attendancesapigethrAttendances({Map<String, dynamic>? query}) {
    const url = '/api/v1/hr/attendances';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /hr/certifications - HrTrainingController@certifications
  Future<Response<dynamic>> certificationsapigethrCertifications({Map<String, dynamic>? query}) {
    const url = '/api/v1/hr/certifications';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /hr/certifications/expiring - HrTrainingController@expiringCertifications
  Future<Response<dynamic>> certificationsapigethrCertificationsExpiring({Map<String, dynamic>? query}) {
    const url = '/api/v1/hr/certifications/expiring';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /hr/contracts - HrAdvanceController@contracts
  Future<Response<dynamic>> contractsapigethrContracts({Map<String, dynamic>? query}) {
    const url = '/api/v1/hr/contracts';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /hr/contracts/terminating-soon - HrAdvanceController@terminatingSoon
  Future<Response<dynamic>> contractsapigethrContractsTerminatingSoon({Map<String, dynamic>? query}) {
    const url = '/api/v1/hr/contracts/terminating-soon';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /hr/leaves - AttendanceController@leaves
  Future<Response<dynamic>> leavesapigethrLeaves({Map<String, dynamic>? query}) {
    const url = '/api/v1/hr/leaves';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /hr/overtime - AttendanceController@overtimeRequests
  Future<Response<dynamic>> overtimeapigethrOvertime({Map<String, dynamic>? query}) {
    const url = '/api/v1/hr/overtime';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /hr/shifts - AttendanceController@shifts
  Future<Response<dynamic>> shiftsapigethrShifts({Map<String, dynamic>? query}) {
    const url = '/api/v1/hr/shifts';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /hr/terminations - HrAdvanceController@terminations
  Future<Response<dynamic>> terminationsapigethrTerminations({Map<String, dynamic>? query}) {
    const url = '/api/v1/hr/terminations';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /hr/trainings - HrTrainingController@trainings
  Future<Response<dynamic>> trainingsapigethrTrainings({Map<String, dynamic>? query}) {
    const url = '/api/v1/hr/trainings';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /integration-logs - IntegrationController@logs
  Future<Response<dynamic>> rootapigetintegrationLogs({Map<String, dynamic>? query}) {
    const url = '/api/v1/integration-logs';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /integrations - IntegrationController@index
  Future<Response<dynamic>> rootapigetintegrations({Map<String, dynamic>? query}) {
    const url = '/api/v1/integrations';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /inventory/balance-sheet - InventoryController@balanceSheet
  Future<Response<dynamic>> balancesheetapigetinventoryBalanceSheet({Map<String, dynamic>? query}) {
    const url = '/api/v1/inventory/balance-sheet';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /inventory/valuation - InventoryController@valuation
  Future<Response<dynamic>> valuationapigetinventoryValuation({Map<String, dynamic>? query}) {
    const url = '/api/v1/inventory/valuation';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /iot/dashboard - IotController@dashboard
  Future<Response<dynamic>> dashboardapigetiotDashboard({Map<String, dynamic>? query}) {
    const url = '/api/v1/iot/dashboard';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /maintenance/records - MaintenanceController@records
  Future<Response<dynamic>> recordsapigetmaintenanceRecords({Map<String, dynamic>? query}) {
    const url = '/api/v1/maintenance/records';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /maintenance/schedules - MaintenanceController@schedules
  Future<Response<dynamic>> schedulesapigetmaintenanceSchedules({Map<String, dynamic>? query}) {
    const url = '/api/v1/maintenance/schedules';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /marketplace/catalog - MarketplaceController@catalog
  Future<Response<dynamic>> catalogapigetmarketplaceCatalog({Map<String, dynamic>? query}) {
    const url = '/api/v1/marketplace/catalog';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /me - AuthController@me
  Future<Response<dynamic>> rootapigetme({Map<String, dynamic>? query}) {
    const url = '/api/v1/me';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /meter-anomalies - MeterAnomalyController@index
  Future<Response<dynamic>> rootapigetmeterAnomalies({Map<String, dynamic>? query}) {
    const url = '/api/v1/meter-anomalies';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /meter-readings/report - MeterReadingController@report
  Future<Response<dynamic>> reportapigetmeterReadingsReport({Map<String, dynamic>? query}) {
    const url = '/api/v1/meter-readings/report';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /meter-readings/route-progress - MeterReadingController@routeProgress
  Future<Response<dynamic>> routeprogressapigetmeterReadingsRouteProgress({Map<String, dynamic>? query}) {
    const url = '/api/v1/meter-readings/route-progress';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /meter-routes - MeterRouteController@index
  Future<Response<dynamic>> rootapigetmeterRoutes({Map<String, dynamic>? query}) {
    const url = '/api/v1/meter-routes';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /meter-routes/{route} - MeterRouteController@show
  Future<Response<dynamic>> routeapigetmeterRoutesId({required String route, Map<String, dynamic>? query}) {
    final url = '/api/v1/meter-routes/${route}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /meters - MeterController@index
  Future<Response<dynamic>> rootapigetmeters({Map<String, dynamic>? query}) {
    const url = '/api/v1/meters';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /meters/{meter} - MeterController@show
  Future<Response<dynamic>> meterapigetmetersId({required String meter, Map<String, dynamic>? query}) {
    final url = '/api/v1/meters/${meter}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /metx/dashboard - MeterAnomalyController@dashboard
  Future<Response<dynamic>> dashboardapigetmetxDashboard({Map<String, dynamic>? query}) {
    const url = '/api/v1/metx/dashboard';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /mfa/recovery-codes - MfaController@getRecoveryCodes
  Future<Response<dynamic>> recoverycodesapigetmfaRecoveryCodes({Map<String, dynamic>? query}) {
    const url = '/api/v1/mfa/recovery-codes';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /mobile/cities - MobileDropdownController@cities
  Future<Response<dynamic>> citiesapigetmobileCities({Map<String, dynamic>? query}) {
    const url = '/api/v1/mobile/cities';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /mobile/districts - MobileDropdownController@districts
  Future<Response<dynamic>> districtsapigetmobileDistricts({Map<String, dynamic>? query}) {
    const url = '/api/v1/mobile/districts';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /mobile/provinces - MobileDropdownController@provinces
  Future<Response<dynamic>> provincesapigetmobileProvinces({Map<String, dynamic>? query}) {
    const url = '/api/v1/mobile/provinces';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /mobile/streets - MobileDropdownController@streets
  Future<Response<dynamic>> streetsapigetmobileStreets({Map<String, dynamic>? query}) {
    const url = '/api/v1/mobile/streets';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /mobile/villages - MobileDropdownController@villages
  Future<Response<dynamic>> villagesapigetmobileVillages({Map<String, dynamic>? query}) {
    const url = '/api/v1/mobile/villages';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /notification-preferences - NotificationController@preferences
  Future<Response<dynamic>> rootapigetnotificationPreferences({Map<String, dynamic>? query}) {
    const url = '/api/v1/notification-preferences';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /nrw/dashboard - NrwController@dashboard
  Future<Response<dynamic>> dashboardapigetnrwDashboard({Map<String, dynamic>? query}) {
    const url = '/api/v1/nrw/dashboard';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /oauth2-callback - SwaggerController@oauth2Callback
  Future<Response<dynamic>> rootapigetoauth2Callback({Map<String, dynamic>? query}) {
    const url = '/api/v1/oauth2-callback';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /payments/check/{orderId} - PaymentController@checkStatus
  Future<Response<dynamic>> checkapigetpaymentsCheckId({required String orderId, Map<String, dynamic>? query}) {
    final url = '/api/v1/payments/check/${orderId}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /payments/history - PaymentController@paymentHistory
  Future<Response<dynamic>> historyapigetpaymentsHistory({Map<String, dynamic>? query}) {
    const url = '/api/v1/payments/history';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /payments/{payment}/receipt - PaymentController@downloadReceipt
  Future<Response<dynamic>> paymentapigetpaymentsIdReceipt({required String payment, Map<String, dynamic>? query}) {
    final url = '/api/v1/payments/${payment}/receipt';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /payments/{payment}/refunds - PaymentController@refunds
  Future<Response<dynamic>> paymentapigetpaymentsIdRefunds({required String payment, Map<String, dynamic>? query}) {
    final url = '/api/v1/payments/${payment}/refunds';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /platform/marketplace/bundles - MarketplaceController@bundles
  Future<Response<dynamic>> marketplaceapigetplatformMarketplaceBundles({Map<String, dynamic>? query}) {
    const url = '/api/v1/platform/marketplace/bundles';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /platform/marketplace/catalog - MarketplaceController@catalog
  Future<Response<dynamic>> marketplaceapigetplatformMarketplaceCatalog({Map<String, dynamic>? query}) {
    const url = '/api/v1/platform/marketplace/catalog';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /platform/marketplace/dashboard - MarketplaceController@superAdminDashboard
  Future<Response<dynamic>> marketplaceapigetplatformMarketplaceDashboard({Map<String, dynamic>? query}) {
    const url = '/api/v1/platform/marketplace/dashboard';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /platform/marketplace/promos - MarketplaceController@promos
  Future<Response<dynamic>> marketplaceapigetplatformMarketplacePromos({Map<String, dynamic>? query}) {
    const url = '/api/v1/platform/marketplace/promos';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /platform/modules - ModuleCatalogController@index
  Future<Response<dynamic>> modulesapigetplatformModules({Map<String, dynamic>? query}) {
    const url = '/api/v1/platform/modules';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /platform/tenants - TenantController@index
  Future<Response<dynamic>> tenantsapigetplatformTenants({Map<String, dynamic>? query}) {
    const url = '/api/v1/platform/tenants';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /platform/tenants/{tenant} - TenantController@show
  Future<Response<dynamic>> tenantsapigetplatformTenantsId({required String tenant, Map<String, dynamic>? query}) {
    final url = '/api/v1/platform/tenants/${tenant}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /platform/tenants/{tenant}/modules - TenantModuleController@index
  Future<Response<dynamic>> tenantsapigetplatformTenantsIdModules({required String tenant, Map<String, dynamic>? query}) {
    final url = '/api/v1/platform/tenants/${tenant}/modules';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /portal/bills - CustomerPortalController@bills
  Future<Response<dynamic>> billsapigetportalBills({Map<String, dynamic>? query}) {
    const url = '/api/v1/portal/bills';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /portal/bills/{bill} - CustomerPortalController@billDetail
  Future<Response<dynamic>> billsapigetportalBillsId({required String bill, Map<String, dynamic>? query}) {
    final url = '/api/v1/portal/bills/${bill}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /portal/complaints - CustomerPortalController@complaints
  Future<Response<dynamic>> complaintsapigetportalComplaints({Map<String, dynamic>? query}) {
    const url = '/api/v1/portal/complaints';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /portal/complaints/{complaint} - CustomerPortalController@complaint
  Future<Response<dynamic>> complaintsapigetportalComplaintsId({required String complaint, Map<String, dynamic>? query}) {
    final url = '/api/v1/portal/complaints/${complaint}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /portal/consumption-chart - CustomerPortalController@consumptionChart
  Future<Response<dynamic>> consumptionchartapigetportalConsumptionChart({Map<String, dynamic>? query}) {
    const url = '/api/v1/portal/consumption-chart';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /portal/dashboard - CustomerPortalController@dashboard
  Future<Response<dynamic>> dashboardapigetportalDashboard({Map<String, dynamic>? query}) {
    const url = '/api/v1/portal/dashboard';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /portal/notifications - CustomerPortalController@notifications
  Future<Response<dynamic>> notificationsapigetportalNotifications({Map<String, dynamic>? query}) {
    const url = '/api/v1/portal/notifications';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /portal/profile - CustomerPortalController@profile
  Future<Response<dynamic>> profileapigetportalProfile({Map<String, dynamic>? query}) {
    const url = '/api/v1/portal/profile';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /portal/usage-history - CustomerPortalController@consumptionChart
  Future<Response<dynamic>> usagehistoryapigetportalUsageHistory({Map<String, dynamic>? query}) {
    const url = '/api/v1/portal/usage-history';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /privacy/export-my-data - DataPrivacyController@exportMyData
  Future<Response<dynamic>> exportmydataapigetprivacyExportMyData({Map<String, dynamic>? query}) {
    const url = '/api/v1/privacy/export-my-data';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /privacy/retention-status - DataPrivacyController@retentionStatus
  Future<Response<dynamic>> retentionstatusapigetprivacyRetentionStatus({Map<String, dynamic>? query}) {
    const url = '/api/v1/privacy/retention-status';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /production/dashboard - ProductionController@dashboard
  Future<Response<dynamic>> dashboardapigetproductionDashboard({Map<String, dynamic>? query}) {
    const url = '/api/v1/production/dashboard';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /production/metrics - ProductionController@metrics
  Future<Response<dynamic>> metricsapigetproductionMetrics({Map<String, dynamic>? query}) {
    const url = '/api/v1/production/metrics';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /prospects - ProspectController@index
  Future<Response<dynamic>> rootapigetprospects({Map<String, dynamic>? query}) {
    const url = '/api/v1/prospects';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /prospects/{prospect} - ProspectController@show
  Future<Response<dynamic>> prospectapigetprospectsId({required String prospect, Map<String, dynamic>? query}) {
    final url = '/api/v1/prospects/${prospect}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /prospects/{prospect}/material-orders - InstallationController@materialOrders
  Future<Response<dynamic>> prospectapigetprospectsIdMaterialOrders({required String prospect, Map<String, dynamic>? query}) {
    final url = '/api/v1/prospects/${prospect}/material-orders';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /purchase-orders - PurchaseOrderController@index
  Future<Response<dynamic>> rootapigetpurchaseOrders({Map<String, dynamic>? query}) {
    const url = '/api/v1/purchase-orders';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /reports/accounting/balance-sheet - AccountingReportController@balanceSheet
  Future<Response<dynamic>> accountingapigetreportsAccountingBalanceSheet({Map<String, dynamic>? query}) {
    const url = '/api/v1/reports/accounting/balance-sheet';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /reports/accounting/cash-flow - AccountingReportController@cashFlow
  Future<Response<dynamic>> accountingapigetreportsAccountingCashFlow({Map<String, dynamic>? query}) {
    const url = '/api/v1/reports/accounting/cash-flow';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /reports/accounting/general-ledger - AccountingReportController@generalLedger
  Future<Response<dynamic>> accountingapigetreportsAccountingGeneralLedger({Map<String, dynamic>? query}) {
    const url = '/api/v1/reports/accounting/general-ledger';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /reports/accounting/income-statement - AccountingReportController@incomeStatement
  Future<Response<dynamic>> accountingapigetreportsAccountingIncomeStatement({Map<String, dynamic>? query}) {
    const url = '/api/v1/reports/accounting/income-statement';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /reports/accounting/trial-balance - AccountingReportController@trialBalance
  Future<Response<dynamic>> accountingapigetreportsAccountingTrialBalance({Map<String, dynamic>? query}) {
    const url = '/api/v1/reports/accounting/trial-balance';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /roles - RoleController@index
  Future<Response<dynamic>> rootapigetroles({Map<String, dynamic>? query}) {
    const url = '/api/v1/roles';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /session - AuthController@session
  Future<Response<dynamic>> rootapigetsession({Map<String, dynamic>? query}) {
    const url = '/api/v1/session';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /stock-transfers - StockTransferController@index
  Future<Response<dynamic>> rootapigetstockTransfers({Map<String, dynamic>? query}) {
    const url = '/api/v1/stock-transfers';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /sync/download - SyncController@download
  Future<Response<dynamic>> downloadapigetsyncDownload({Map<String, dynamic>? query}) {
    const url = '/api/v1/sync/download';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /sync/status - SyncController@status
  Future<Response<dynamic>> statusapigetsyncStatus({Map<String, dynamic>? query}) {
    const url = '/api/v1/sync/status';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /tariffs - TariffController@index
  Future<Response<dynamic>> rootapigettariffs({Map<String, dynamic>? query}) {
    const url = '/api/v1/tariffs';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /tariffs/{tariffCategory} - TariffController@show
  Future<Response<dynamic>> tariffcategoryapigettariffsId({required String tariffCategory, Map<String, dynamic>? query}) {
    final url = '/api/v1/tariffs/${tariffCategory}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /tenders - TenderController@index
  Future<Response<dynamic>> rootapigettenders({Map<String, dynamic>? query}) {
    const url = '/api/v1/tenders';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /tenders/{tender} - TenderController@show
  Future<Response<dynamic>> tenderapigettendersId({required String tender, Map<String, dynamic>? query}) {
    final url = '/api/v1/tenders/${tender}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /users - UserController@index
  Future<Response<dynamic>> rootapigetusers({Map<String, dynamic>? query}) {
    const url = '/api/v1/users';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /vendors - VendorController@index
  Future<Response<dynamic>> rootapigetvendors({Map<String, dynamic>? query}) {
    const url = '/api/v1/vendors';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /vendors/{vendor} - VendorController@show
  Future<Response<dynamic>> vendorapigetvendorsId({required String vendor, Map<String, dynamic>? query}) {
    final url = '/api/v1/vendors/${vendor}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /warehouse/dashboard - WarehouseController@stockDashboard
  Future<Response<dynamic>> dashboardapigetwarehouseDashboard({Map<String, dynamic>? query}) {
    const url = '/api/v1/warehouse/dashboard';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /work-orders - WorkOrderController@index
  Future<Response<dynamic>> rootapigetworkOrders({Map<String, dynamic>? query}) {
    const url = '/api/v1/work-orders';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /work-orders/technician/dashboard - WorkOrderController@technicianDashboard
  Future<Response<dynamic>> technicianapigetworkOrdersTechnicianDashboard({Map<String, dynamic>? query}) {
    const url = '/api/v1/work-orders/technician/dashboard';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /work-orders/{workOrder} - WorkOrderController@show
  Future<Response<dynamic>> workorderapigetworkOrdersId({required String workOrder, Map<String, dynamic>? query}) {
    final url = '/api/v1/work-orders/${workOrder}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /zones - ZoneController@index
  Future<Response<dynamic>> rootapigetzones({Map<String, dynamic>? query}) {
    const url = '/api/v1/zones';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /zones/dashboard - ZoneDashboardController@all
  Future<Response<dynamic>> dashboardapigetzonesDashboard({Map<String, dynamic>? query}) {
    const url = '/api/v1/zones/dashboard';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /zones/{zone} - ZoneController@show
  Future<Response<dynamic>> zoneapigetzonesId({required String zone, Map<String, dynamic>? query}) {
    final url = '/api/v1/zones/${zone}';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [GET] /zones/{zone}/dashboard - ZoneDashboardController@show
  Future<Response<dynamic>> zoneapigetzonesIdDashboard({required String zone, Map<String, dynamic>? query}) {
    final url = '/api/v1/zones/${zone}/dashboard';
    return _dio.request<dynamic>(url, queryParameters: query, options: Options(method: 'GET'));
  }

  /// [POST] /address/cities - AddressController@storeCity
  Future<Response<dynamic>> citiesapipostaddressCities({required Map<String, dynamic> data}) {
    const url = '/api/v1/address/cities';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /address/cities/{id}/restore - AddressController@restoreCity
  Future<Response<dynamic>> citiesapipostaddressCitiesIdRestore({required String id, required Map<String, dynamic> data}) {
    final url = '/api/v1/address/cities/${id}/restore';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /address/districts - AddressController@storeDistrict
  Future<Response<dynamic>> districtsapipostaddressDistricts({required Map<String, dynamic> data}) {
    const url = '/api/v1/address/districts';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /address/districts/{id}/restore - AddressController@restoreDistrict
  Future<Response<dynamic>> districtsapipostaddressDistrictsIdRestore({required String id, required Map<String, dynamic> data}) {
    final url = '/api/v1/address/districts/${id}/restore';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /address/provinces - AddressController@storeProvince
  Future<Response<dynamic>> provincesapipostaddressProvinces({required Map<String, dynamic> data}) {
    const url = '/api/v1/address/provinces';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /address/provinces/{id}/restore - AddressController@restoreProvince
  Future<Response<dynamic>> provincesapipostaddressProvincesIdRestore({required String id, required Map<String, dynamic> data}) {
    final url = '/api/v1/address/provinces/${id}/restore';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /address/streets - AddressController@storeStreet
  Future<Response<dynamic>> streetsapipostaddressStreets({required Map<String, dynamic> data}) {
    const url = '/api/v1/address/streets';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /address/streets/{id}/restore - AddressController@restoreStreet
  Future<Response<dynamic>> streetsapipostaddressStreetsIdRestore({required String id, required Map<String, dynamic> data}) {
    final url = '/api/v1/address/streets/${id}/restore';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /address/villages - AddressController@storeVillage
  Future<Response<dynamic>> villagesapipostaddressVillages({required Map<String, dynamic> data}) {
    const url = '/api/v1/address/villages';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /address/villages/{id}/restore - AddressController@restoreVillage
  Future<Response<dynamic>> villagesapipostaddressVillagesIdRestore({required String id, required Map<String, dynamic> data}) {
    final url = '/api/v1/address/villages/${id}/restore';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /api-keys - IntegrationController@createApiKey
  Future<Response<dynamic>> rootapipostapiKeys({required Map<String, dynamic> data}) {
    const url = '/api/v1/api-keys';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /api-keys/{apiKey}/revoke - IntegrationController@revokeApiKey
  Future<Response<dynamic>> apikeyapipostapiKeysIdRevoke({required String apiKey, required Map<String, dynamic> data}) {
    final url = '/api/v1/api-keys/${apiKey}/revoke';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /asset-categories - AssetCategoryController@store
  Future<Response<dynamic>> rootapipostassetCategories({required Map<String, dynamic> data}) {
    const url = '/api/v1/asset-categories';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /assets - FixedAssetController@store
  Future<Response<dynamic>> rootapipostassets({required Map<String, dynamic> data}) {
    const url = '/api/v1/assets';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /assets/capitalize - FixedAssetController@capitalize
  Future<Response<dynamic>> capitalizeapipostassetsCapitalize({required Map<String, dynamic> data}) {
    const url = '/api/v1/assets/capitalize';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /assets/depreciation/run - FixedAssetController@runDepreciation
  Future<Response<dynamic>> depreciationapipostassetsDepreciationRun({required Map<String, dynamic> data}) {
    const url = '/api/v1/assets/depreciation/run';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /assets/opname - AssetOpnameController@opname
  Future<Response<dynamic>> opnameapipostassetsOpname({required Map<String, dynamic> data}) {
    const url = '/api/v1/assets/opname';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /assets/{asset}/revaluation - AssetOpnameController@revaluation
  Future<Response<dynamic>> assetapipostassetsIdRevaluation({required String asset, required Map<String, dynamic> data}) {
    final url = '/api/v1/assets/${asset}/revaluation';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /assets/{fixedAsset}/dispose - FixedAssetController@dispose
  Future<Response<dynamic>> fixedassetapipostassetsIdDispose({required String fixedAsset, required Map<String, dynamic> data}) {
    final url = '/api/v1/assets/${fixedAsset}/dispose';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /assets/{fixedAsset}/move - FixedAssetController@move
  Future<Response<dynamic>> fixedassetapipostassetsIdMove({required String fixedAsset, required Map<String, dynamic> data}) {
    final url = '/api/v1/assets/${fixedAsset}/move';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /bi/report-builder - BiController@reportBuilder
  Future<Response<dynamic>> reportbuilderapipostbiReportBuilder({required Map<String, dynamic> data}) {
    const url = '/api/v1/bi/report-builder';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /bi/scheduled-reports - ScheduledReportController@store
  Future<Response<dynamic>> scheduledreportsapipostbiScheduledReports({required Map<String, dynamic> data}) {
    const url = '/api/v1/bi/scheduled-reports';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /bi/scheduled-reports/{scheduledReport}/run - ScheduledReportController@runNow
  Future<Response<dynamic>> scheduledreportsapipostbiScheduledReportsIdRun({required String scheduledReport, required Map<String, dynamic> data}) {
    final url = '/api/v1/bi/scheduled-reports/${scheduledReport}/run';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /bill-adjustments - BillAdjustmentController@store
  Future<Response<dynamic>> rootapipostbillAdjustments({required Map<String, dynamic> data}) {
    const url = '/api/v1/bill-adjustments';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /bill-adjustments/{adjustment}/approve - BillAdjustmentController@approve
  Future<Response<dynamic>> adjustmentapipostbillAdjustmentsIdApprove({required String adjustment, required Map<String, dynamic> data}) {
    final url = '/api/v1/bill-adjustments/${adjustment}/approve';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /bill-adjustments/{adjustment}/post - BillAdjustmentController@post
  Future<Response<dynamic>> adjustmentapipostbillAdjustmentsIdPost({required String adjustment, required Map<String, dynamic> data}) {
    final url = '/api/v1/bill-adjustments/${adjustment}/post';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /bill-adjustments/{adjustment}/reject - BillAdjustmentController@reject
  Future<Response<dynamic>> adjustmentapipostbillAdjustmentsIdReject({required String adjustment, required Map<String, dynamic> data}) {
    final url = '/api/v1/bill-adjustments/${adjustment}/reject';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /bill-audit - BillRecController@audit
  Future<Response<dynamic>> rootapipostbillAudit({required Map<String, dynamic> data}) {
    const url = '/api/v1/bill-audit';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /bill-reconciliation - BillRecController@reconcile
  Future<Response<dynamic>> rootapipostbillReconciliation({required Map<String, dynamic> data}) {
    const url = '/api/v1/bill-reconciliation';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /bills/generate - BillingController@generate
  Future<Response<dynamic>> generateapipostbillsGenerate({required Map<String, dynamic> data}) {
    const url = '/api/v1/bills/generate';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /call-logs - CallCenterController@log
  Future<Response<dynamic>> rootapipostcallLogs({required Map<String, dynamic> data}) {
    const url = '/api/v1/call-logs';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /chats - ChatController@store
  Future<Response<dynamic>> rootapipostchats({required Map<String, dynamic> data}) {
    const url = '/api/v1/chats';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /chats/{chat}/messages - ChatController@sendMessage
  Future<Response<dynamic>> chatapipostchatsIdMessages({required String chat, required Map<String, dynamic> data}) {
    final url = '/api/v1/chats/${chat}/messages';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /chem/chemicals - ChemController@storeChemical
  Future<Response<dynamic>> chemicalsapipostchemChemicals({required Map<String, dynamic> data}) {
    const url = '/api/v1/chem/chemicals';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /chem/receipts - ChemController@createReceipt
  Future<Response<dynamic>> receiptsapipostchemReceipts({required Map<String, dynamic> data}) {
    const url = '/api/v1/chem/receipts';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /chem/receipts/{receipt}/qc - ChemController@qcTest
  Future<Response<dynamic>> receiptsapipostchemReceiptsIdQc({required String receipt, required Map<String, dynamic> data}) {
    final url = '/api/v1/chem/receipts/${receipt}/qc';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /chem/usage - ChemController@usage
  Future<Response<dynamic>> usageapipostchemUsage({required Map<String, dynamic> data}) {
    const url = '/api/v1/chem/usage';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /complaints - ComplaintController@store
  Future<Response<dynamic>> rootapipostcomplaints({required Map<String, dynamic> data}) {
    const url = '/api/v1/complaints';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /complaints/{complaint}/assign - ComplaintController@assign
  Future<Response<dynamic>> complaintapipostcomplaintsIdAssign({required String complaint, required Map<String, dynamic> data}) {
    final url = '/api/v1/complaints/${complaint}/assign';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /complaints/{complaint}/resolve - ComplaintController@resolve
  Future<Response<dynamic>> complaintapipostcomplaintsIdResolve({required String complaint, required Map<String, dynamic> data}) {
    final url = '/api/v1/complaints/${complaint}/resolve';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /customers - CustomerController@store
  Future<Response<dynamic>> rootapipostcustomers({required Map<String, dynamic> data}) {
    const url = '/api/v1/customers';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /customers/{customer}/disconnect - CustomerLifecycleController@disconnect
  Future<Response<dynamic>> customerapipostcustomersIdDisconnect({required String customer, required Map<String, dynamic> data}) {
    final url = '/api/v1/customers/${customer}/disconnect';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /customers/{customer}/manual-reading - CustomerController@manualReading
  Future<Response<dynamic>> customerapipostcustomersIdManualReading({required String customer, required Map<String, dynamic> data}) {
    final url = '/api/v1/customers/${customer}/manual-reading';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /customers/{customer}/reconnect - CustomerLifecycleController@reconnect
  Future<Response<dynamic>> customerapipostcustomersIdReconnect({required String customer, required Map<String, dynamic> data}) {
    final url = '/api/v1/customers/${customer}/reconnect';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /customers/{customer}/transfer-ownership - CustomerLifecycleController@transferOwnership
  Future<Response<dynamic>> customerapipostcustomersIdTransferOwnership({required String customer, required Map<String, dynamic> data}) {
    final url = '/api/v1/customers/${customer}/transfer-ownership';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /distribution/ingest - DistController@ingest
  Future<Response<dynamic>> ingestapipostdistributionIngest({required Map<String, dynamic> data}) {
    const url = '/api/v1/distribution/ingest';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /documents - DocumentController@store
  Future<Response<dynamic>> rootapipostdocuments({required Map<String, dynamic> data}) {
    const url = '/api/v1/documents';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /documents/{document}/approve - DocumentController@approve
  Future<Response<dynamic>> documentapipostdocumentsIdApprove({required String document, required Map<String, dynamic> data}) {
    final url = '/api/v1/documents/${document}/approve';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /employees - HrEmployeeController@store
  Future<Response<dynamic>> rootapipostemployees({required Map<String, dynamic> data}) {
    const url = '/api/v1/employees';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /export - ExportController@export
  Future<Response<dynamic>> rootapipostexport({required Map<String, dynamic> data}) {
    const url = '/api/v1/export';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /fcm-token - AuthController@registerFcmToken
  Future<Response<dynamic>> rootapipostfcmToken({required Map<String, dynamic> data}) {
    const url = '/api/v1/fcm-token';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /files/signed-url - FileController@signedUrl
  Future<Response<dynamic>> signedurlapipostfilesSignedUrl({required Map<String, dynamic> data}) {
    const url = '/api/v1/files/signed-url';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /files/upload - FileController@upload
  Future<Response<dynamic>> uploadapipostfilesUpload({required Map<String, dynamic> data}) {
    const url = '/api/v1/files/upload';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /fin/bank-accounts - FinController@createBankAccount
  Future<Response<dynamic>> bankaccountsapipostfinBankAccounts({required Map<String, dynamic> data}) {
    const url = '/api/v1/fin/bank-accounts';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /fin/bank/transfer - FinAdvanceController@bankTransfer
  Future<Response<dynamic>> bankapipostfinBankTransfer({required Map<String, dynamic> data}) {
    const url = '/api/v1/fin/bank/transfer';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /fin/bank/{account}/reconcile - FinAdvanceController@reconcileBank
  Future<Response<dynamic>> bankapipostfinBankIdReconcile({required String account, required Map<String, dynamic> data}) {
    final url = '/api/v1/fin/bank/${account}/reconcile';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /fin/budgets - FinController@createBudget
  Future<Response<dynamic>> budgetsapipostfinBudgets({required Map<String, dynamic> data}) {
    const url = '/api/v1/fin/budgets';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /fin/projects - FinController@createProject
  Future<Response<dynamic>> projectsapipostfinProjects({required Map<String, dynamic> data}) {
    const url = '/api/v1/fin/projects';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /fin/recurring-transactions - FinController@createRecurring
  Future<Response<dynamic>> recurringtransactionsapipostfinRecurringTransactions({required Map<String, dynamic> data}) {
    const url = '/api/v1/fin/recurring-transactions';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /fin/sales-invoices - FinController@createSalesInvoice
  Future<Response<dynamic>> salesinvoicesapipostfinSalesInvoices({required Map<String, dynamic> data}) {
    const url = '/api/v1/fin/sales-invoices';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /fin/tax/calculate - FinAdvanceController@taxCalculate
  Future<Response<dynamic>> taxapipostfinTaxCalculate({required Map<String, dynamic> data}) {
    const url = '/api/v1/fin/tax/calculate';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /fin/tax/efaktur-export - FinAdvanceController@exportEfaktur
  Future<Response<dynamic>> taxapipostfinTaxEfakturExport({required Map<String, dynamic> data}) {
    const url = '/api/v1/fin/tax/efaktur-export';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /fin/tax/record - FinAdvanceController@taxRecord
  Future<Response<dynamic>> taxapipostfinTaxRecord({required Map<String, dynamic> data}) {
    const url = '/api/v1/fin/tax/record';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /gis/features - GisEditorController@createFeature
  Future<Response<dynamic>> featuresapipostgisFeatures({required Map<String, dynamic> data}) {
    const url = '/api/v1/gis/features';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /gis/network-edges - GisEditorController@createEdge
  Future<Response<dynamic>> networkedgesapipostgisNetworkEdges({required Map<String, dynamic> data}) {
    const url = '/api/v1/gis/network-edges';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /gis/pipes - GisEditorController@createPipe
  Future<Response<dynamic>> pipesapipostgisPipes({required Map<String, dynamic> data}) {
    const url = '/api/v1/gis/pipes';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /hr/appraisals - HrTrainingController@createAppraisal
  Future<Response<dynamic>> appraisalsapiposthrAppraisals({required Map<String, dynamic> data}) {
    const url = '/api/v1/hr/appraisals';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /hr/appraisals/{appraisal}/approve - HrTrainingController@approveAppraisal
  Future<Response<dynamic>> appraisalsapiposthrAppraisalsIdApprove({required String appraisal, required Map<String, dynamic> data}) {
    final url = '/api/v1/hr/appraisals/${appraisal}/approve';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /hr/attendances/check-in - AttendanceController@checkIn
  Future<Response<dynamic>> attendancesapiposthrAttendancesCheckIn({required Map<String, dynamic> data}) {
    const url = '/api/v1/hr/attendances/check-in';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /hr/attendances/check-out - AttendanceController@checkOut
  Future<Response<dynamic>> attendancesapiposthrAttendancesCheckOut({required Map<String, dynamic> data}) {
    const url = '/api/v1/hr/attendances/check-out';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /hr/certifications - HrTrainingController@createCertification
  Future<Response<dynamic>> certificationsapiposthrCertifications({required Map<String, dynamic> data}) {
    const url = '/api/v1/hr/certifications';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /hr/contracts - HrAdvanceController@createContract
  Future<Response<dynamic>> contractsapiposthrContracts({required Map<String, dynamic> data}) {
    const url = '/api/v1/hr/contracts';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /hr/leaves - AttendanceController@requestLeave
  Future<Response<dynamic>> leavesapiposthrLeaves({required Map<String, dynamic> data}) {
    const url = '/api/v1/hr/leaves';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /hr/leaves/{leave}/approve - AttendanceController@approveLeave
  Future<Response<dynamic>> leavesapiposthrLeavesIdApprove({required String leave, required Map<String, dynamic> data}) {
    final url = '/api/v1/hr/leaves/${leave}/approve';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /hr/overtime - AttendanceController@requestOvertime
  Future<Response<dynamic>> overtimeapiposthrOvertime({required Map<String, dynamic> data}) {
    const url = '/api/v1/hr/overtime';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /hr/overtime/{overtime}/approve - AttendanceController@approveOvertime
  Future<Response<dynamic>> overtimeapiposthrOvertimeIdApprove({required String overtime, required Map<String, dynamic> data}) {
    final url = '/api/v1/hr/overtime/${overtime}/approve';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /hr/shifts - AttendanceController@createShift
  Future<Response<dynamic>> shiftsapiposthrShifts({required Map<String, dynamic> data}) {
    const url = '/api/v1/hr/shifts';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /hr/terminations - HrAdvanceController@createTermination
  Future<Response<dynamic>> terminationsapiposthrTerminations({required Map<String, dynamic> data}) {
    const url = '/api/v1/hr/terminations';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /hr/terminations/{termination}/approve - HrAdvanceController@approveTermination
  Future<Response<dynamic>> terminationsapiposthrTerminationsIdApprove({required String termination, required Map<String, dynamic> data}) {
    final url = '/api/v1/hr/terminations/${termination}/approve';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /hr/trainings - HrTrainingController@createTraining
  Future<Response<dynamic>> trainingsapiposthrTrainings({required Map<String, dynamic> data}) {
    const url = '/api/v1/hr/trainings';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /hr/trainings/{training}/participants - HrTrainingController@addParticipant
  Future<Response<dynamic>> trainingsapiposthrTrainingsIdParticipants({required String training, required Map<String, dynamic> data}) {
    final url = '/api/v1/hr/trainings/${training}/participants';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /installations/{schedule}/complete - InstallationController@complete
  Future<Response<dynamic>> scheduleapipostinstallationsIdComplete({required String schedule, required Map<String, dynamic> data}) {
    final url = '/api/v1/installations/${schedule}/complete';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /integrations - IntegrationController@store
  Future<Response<dynamic>> rootapipostintegrations({required Map<String, dynamic> data}) {
    const url = '/api/v1/integrations';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /integrations/test-send - IntegrationController@testSend
  Future<Response<dynamic>> testsendapipostintegrationsTestSend({required Map<String, dynamic> data}) {
    const url = '/api/v1/integrations/test-send';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /integrations/{integration}/toggle - IntegrationController@toggle
  Future<Response<dynamic>> integrationapipostintegrationsIdToggle({required String integration, required Map<String, dynamic> data}) {
    final url = '/api/v1/integrations/${integration}/toggle';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /iot/ingest - IotController@ingest
  Future<Response<dynamic>> ingestapipostiotIngest({required Map<String, dynamic> data}) {
    const url = '/api/v1/iot/ingest';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /journal/manual - InventoryController@manualJournal
  Future<Response<dynamic>> manualapipostjournalManual({required Map<String, dynamic> data}) {
    const url = '/api/v1/journal/manual';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /login - AuthController@login
  Future<Response<dynamic>> rootapipostlogin({required Map<String, dynamic> data}) {
    const url = '/api/v1/login';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /logout - AuthController@logout
  Future<Response<dynamic>> rootapipostlogout({required Map<String, dynamic> data}) {
    const url = '/api/v1/logout';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /maintenance/records - MaintenanceController@createRecord
  Future<Response<dynamic>> recordsapipostmaintenanceRecords({required Map<String, dynamic> data}) {
    const url = '/api/v1/maintenance/records';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /maintenance/schedules - MaintenanceController@createSchedule
  Future<Response<dynamic>> schedulesapipostmaintenanceSchedules({required Map<String, dynamic> data}) {
    const url = '/api/v1/maintenance/schedules';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /marketplace/purchases - MarketplaceController@purchase
  Future<Response<dynamic>> purchasesapipostmarketplacePurchases({required Map<String, dynamic> data}) {
    const url = '/api/v1/marketplace/purchases';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /material-orders/{order}/cancel - InstallationController@cancelMaterials
  Future<Response<dynamic>> orderapipostmaterialOrdersIdCancel({required String order, required Map<String, dynamic> data}) {
    final url = '/api/v1/material-orders/${order}/cancel';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /material-orders/{order}/issue - InstallationController@issueMaterials
  Future<Response<dynamic>> orderapipostmaterialOrdersIdIssue({required String order, required Map<String, dynamic> data}) {
    final url = '/api/v1/material-orders/${order}/issue';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /meter-anomalies/scan - MeterAnomalyController@scan
  Future<Response<dynamic>> scanapipostmeterAnomaliesScan({required Map<String, dynamic> data}) {
    const url = '/api/v1/meter-anomalies/scan';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /meter-anomalies/{anomaly}/confirm - MeterAnomalyController@confirm
  Future<Response<dynamic>> anomalyapipostmeterAnomaliesIdConfirm({required String anomaly, required Map<String, dynamic> data}) {
    final url = '/api/v1/meter-anomalies/${anomaly}/confirm';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /meter-anomalies/{anomaly}/dismiss - MeterAnomalyController@dismiss
  Future<Response<dynamic>> anomalyapipostmeterAnomaliesIdDismiss({required String anomaly, required Map<String, dynamic> data}) {
    final url = '/api/v1/meter-anomalies/${anomaly}/dismiss';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /meter-anomalies/{anomaly}/review - MeterAnomalyController@review
  Future<Response<dynamic>> anomalyapipostmeterAnomaliesIdReview({required String anomaly, required Map<String, dynamic> data}) {
    final url = '/api/v1/meter-anomalies/${anomaly}/review';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /meter-readings - MeterReadingController@store
  Future<Response<dynamic>> rootapipostmeterReadings({required Map<String, dynamic> data}) {
    const url = '/api/v1/meter-readings';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /meter-readings/parse - MeterReadingController@parseMeter
  Future<Response<dynamic>> parseapipostmeterReadingsParse({required Map<String, dynamic> data}) {
    const url = '/api/v1/meter-readings/parse';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /meter-readings/{reading}/verify - MeterReadingController@verify
  Future<Response<dynamic>> readingapipostmeterReadingsIdVerify({required String reading, required Map<String, dynamic> data}) {
    final url = '/api/v1/meter-readings/${reading}/verify';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /meter-routes - MeterRouteController@store
  Future<Response<dynamic>> rootapipostmeterRoutes({required Map<String, dynamic> data}) {
    const url = '/api/v1/meter-routes';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /meter-routes/auto-map - MeterRouteController@autoMap
  Future<Response<dynamic>> automapapipostmeterRoutesAutoMap({required Map<String, dynamic> data}) {
    const url = '/api/v1/meter-routes/auto-map';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /meter-routes/{route}/officer - MeterRouteController@assignOfficer
  Future<Response<dynamic>> routeapipostmeterRoutesIdOfficer({required String route, required Map<String, dynamic> data}) {
    final url = '/api/v1/meter-routes/${route}/officer';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /meter-routes/{route}/streets - MeterRouteController@syncStreets
  Future<Response<dynamic>> routeapipostmeterRoutesIdStreets({required String route, required Map<String, dynamic> data}) {
    final url = '/api/v1/meter-routes/${route}/streets';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /meters - MeterController@store
  Future<Response<dynamic>> rootapipostmeters({required Map<String, dynamic> data}) {
    const url = '/api/v1/meters';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /meters/{meter}/assign - MeterController@assign
  Future<Response<dynamic>> meterapipostmetersIdAssign({required String meter, required Map<String, dynamic> data}) {
    final url = '/api/v1/meters/${meter}/assign';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /meters/{meter}/recalibrate - MeterController@recalibrate
  Future<Response<dynamic>> meterapipostmetersIdRecalibrate({required String meter, required Map<String, dynamic> data}) {
    final url = '/api/v1/meters/${meter}/recalibrate';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /meters/{meter}/remove - MeterController@remove
  Future<Response<dynamic>> meterapipostmetersIdRemove({required String meter, required Map<String, dynamic> data}) {
    final url = '/api/v1/meters/${meter}/remove';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /meters/{meter}/scrap - MeterController@scrap
  Future<Response<dynamic>> meterapipostmetersIdScrap({required String meter, required Map<String, dynamic> data}) {
    final url = '/api/v1/meters/${meter}/scrap';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /mfa/disable - MfaController@disable
  Future<Response<dynamic>> disableapipostmfaDisable({required Map<String, dynamic> data}) {
    const url = '/api/v1/mfa/disable';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /mfa/enable - MfaController@enable
  Future<Response<dynamic>> enableapipostmfaEnable({required Map<String, dynamic> data}) {
    const url = '/api/v1/mfa/enable';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /mfa/recovery-codes/regenerate - MfaController@regenerateRecoveryCodes
  Future<Response<dynamic>> recoverycodesapipostmfaRecoveryCodesRegenerate({required Map<String, dynamic> data}) {
    const url = '/api/v1/mfa/recovery-codes/regenerate';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /mfa/setup - MfaController@setup
  Future<Response<dynamic>> setupapipostmfaSetup({required Map<String, dynamic> data}) {
    const url = '/api/v1/mfa/setup';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /mfa/verify - MfaController@verify
  Future<Response<dynamic>> verifyapipostmfaVerify({required Map<String, dynamic> data}) {
    const url = '/api/v1/mfa/verify';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /notification-preferences/test-push - NotificationController@testPush
  Future<Response<dynamic>> testpushapipostnotificationPreferencesTestPush({required Map<String, dynamic> data}) {
    const url = '/api/v1/notification-preferences/test-push';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /nrw/calculate - NrwController@calculate
  Future<Response<dynamic>> calculateapipostnrwCalculate({required Map<String, dynamic> data}) {
    const url = '/api/v1/nrw/calculate';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /payments - PaymentController@createPayment
  Future<Response<dynamic>> rootapipostpayments({required Map<String, dynamic> data}) {
    const url = '/api/v1/payments';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /payments/cash - PaymentController@cashPayment
  Future<Response<dynamic>> cashapipostpaymentsCash({required Map<String, dynamic> data}) {
    const url = '/api/v1/payments/cash';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /payments/{payment}/refund - PaymentController@refund
  Future<Response<dynamic>> paymentapipostpaymentsIdRefund({required String payment, required Map<String, dynamic> data}) {
    final url = '/api/v1/payments/${payment}/refund';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /payroll/batch-run - PayrollController@batchRun
  Future<Response<dynamic>> batchrunapipostpayrollBatchRun({required Map<String, dynamic> data}) {
    const url = '/api/v1/payroll/batch-run';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /payroll/calculate - PayrollController@calculate
  Future<Response<dynamic>> calculateapipostpayrollCalculate({required Map<String, dynamic> data}) {
    const url = '/api/v1/payroll/calculate';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /payroll/slip - PayrollController@slip
  Future<Response<dynamic>> slipapipostpayrollSlip({required Map<String, dynamic> data}) {
    const url = '/api/v1/payroll/slip';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /platform/login - PlatformAuthController@login
  Future<Response<dynamic>> loginapipostplatformLogin({required Map<String, dynamic> data}) {
    const url = '/api/v1/platform/login';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /platform/logout - PlatformAuthController@logout
  Future<Response<dynamic>> logoutapipostplatformLogout({required Map<String, dynamic> data}) {
    const url = '/api/v1/platform/logout';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /platform/marketplace/bundles - MarketplaceController@createBundle
  Future<Response<dynamic>> marketplaceapipostplatformMarketplaceBundles({required Map<String, dynamic> data}) {
    const url = '/api/v1/platform/marketplace/bundles';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /platform/marketplace/prices - MarketplaceController@managePrice
  Future<Response<dynamic>> marketplaceapipostplatformMarketplacePrices({required Map<String, dynamic> data}) {
    const url = '/api/v1/platform/marketplace/prices';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /platform/marketplace/promos - MarketplaceController@createPromo
  Future<Response<dynamic>> marketplaceapipostplatformMarketplacePromos({required Map<String, dynamic> data}) {
    const url = '/api/v1/platform/marketplace/promos';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /platform/marketplace/purchase - MarketplaceController@purchase
  Future<Response<dynamic>> marketplaceapipostplatformMarketplacePurchase({required Map<String, dynamic> data}) {
    const url = '/api/v1/platform/marketplace/purchase';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /platform/tenants - TenantController@store
  Future<Response<dynamic>> tenantsapipostplatformTenants({required Map<String, dynamic> data}) {
    const url = '/api/v1/platform/tenants';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /platform/tenants/{tenant}/modules/{moduleCode}/activate - TenantModuleController@activate
  Future<Response<dynamic>> tenantsapipostplatformTenantsIdModulesIdActivate({required String tenant, required String moduleCode, required Map<String, dynamic> data}) {
    final url = '/api/v1/platform/tenants/${tenant}/modules/${moduleCode}/activate';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /platform/tenants/{tenant}/modules/{moduleCode}/lock - TenantModuleController@lock
  Future<Response<dynamic>> tenantsapipostplatformTenantsIdModulesIdLock({required String tenant, required String moduleCode, required Map<String, dynamic> data}) {
    final url = '/api/v1/platform/tenants/${tenant}/modules/${moduleCode}/lock';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /platform/tenants/{tenant}/toggle-status - TenantController@toggleStatus
  Future<Response<dynamic>> tenantsapipostplatformTenantsIdToggleStatus({required String tenant, required Map<String, dynamic> data}) {
    final url = '/api/v1/platform/tenants/${tenant}/toggle-status';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /portal/bills/{bill}/pay - CustomerPortalController@payBill
  Future<Response<dynamic>> billsapipostportalBillsIdPay({required String bill, required Map<String, dynamic> data}) {
    final url = '/api/v1/portal/bills/${bill}/pay';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /portal/complaints - CustomerPortalController@createComplaint
  Future<Response<dynamic>> complaintsapipostportalComplaints({required Map<String, dynamic> data}) {
    const url = '/api/v1/portal/complaints';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /portal/notifications/read-all - CustomerPortalController@markAllNotificationsRead
  Future<Response<dynamic>> notificationsapipostportalNotificationsReadAll({required Map<String, dynamic> data}) {
    const url = '/api/v1/portal/notifications/read-all';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /portal/notifications/{notificationId}/read - CustomerPortalController@markNotificationRead
  Future<Response<dynamic>> notificationsapipostportalNotificationsIdRead({required String notificationId, required Map<String, dynamic> data}) {
    final url = '/api/v1/portal/notifications/${notificationId}/read';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /privacy/delete-my-data - DataPrivacyController@deleteMyData
  Future<Response<dynamic>> deletemydataapipostprivacyDeleteMyData({required Map<String, dynamic> data}) {
    const url = '/api/v1/privacy/delete-my-data';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /privacy/purge-old-data - DataPrivacyController@purgeOldData
  Future<Response<dynamic>> purgeolddataapipostprivacyPurgeOldData({required Map<String, dynamic> data}) {
    const url = '/api/v1/privacy/purge-old-data';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /privacy/purge-old-data/{purgeRequest}/approve - DataPrivacyController@approvePurge
  Future<Response<dynamic>> purgeolddataapipostprivacyPurgeOldDataIdApprove({required String purgeRequest, required Map<String, dynamic> data}) {
    final url = '/api/v1/privacy/purge-old-data/${purgeRequest}/approve';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /production/ingest - ProductionController@ingest
  Future<Response<dynamic>> ingestapipostproductionIngest({required Map<String, dynamic> data}) {
    const url = '/api/v1/production/ingest';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /prospects - ProspectController@store
  Future<Response<dynamic>> rootapipostprospects({required Map<String, dynamic> data}) {
    const url = '/api/v1/prospects';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /prospects/parse-ktp - ProspectController@parseKtp
  Future<Response<dynamic>> parsektpapipostprospectsParseKtp({required Map<String, dynamic> data}) {
    const url = '/api/v1/prospects/parse-ktp';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /prospects/upload-ktp - ProspectController@uploadKtp
  Future<Response<dynamic>> uploadktpapipostprospectsUploadKtp({required Map<String, dynamic> data}) {
    const url = '/api/v1/prospects/upload-ktp';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /prospects/{prospect}/activate - InstallationController@activate
  Future<Response<dynamic>> prospectapipostprospectsIdActivate({required String prospect, required Map<String, dynamic> data}) {
    final url = '/api/v1/prospects/${prospect}/activate';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /prospects/{prospect}/assign-surveyor - ProspectController@assignSurveyor
  Future<Response<dynamic>> prospectapipostprospectsIdAssignSurveyor({required String prospect, required Map<String, dynamic> data}) {
    final url = '/api/v1/prospects/${prospect}/assign-surveyor';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /prospects/{prospect}/order-materials - InstallationController@orderMaterials
  Future<Response<dynamic>> prospectapipostprospectsIdOrderMaterials({required String prospect, required Map<String, dynamic> data}) {
    final url = '/api/v1/prospects/${prospect}/order-materials';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /prospects/{prospect}/pay-installation - ProspectController@payInstallation
  Future<Response<dynamic>> prospectapipostprospectsIdPayInstallation({required String prospect, required Map<String, dynamic> data}) {
    final url = '/api/v1/prospects/${prospect}/pay-installation';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /prospects/{prospect}/schedule-installation - InstallationController@schedule
  Future<Response<dynamic>> prospectapipostprospectsIdScheduleInstallation({required String prospect, required Map<String, dynamic> data}) {
    final url = '/api/v1/prospects/${prospect}/schedule-installation';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /prospects/{prospect}/survey - ProspectController@submitSurvey
  Future<Response<dynamic>> prospectapipostprospectsIdSurvey({required String prospect, required Map<String, dynamic> data}) {
    final url = '/api/v1/prospects/${prospect}/survey';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /purchase-orders - PurchaseOrderController@store
  Future<Response<dynamic>> rootapipostpurchaseOrders({required Map<String, dynamic> data}) {
    const url = '/api/v1/purchase-orders';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /purchase-orders/{po}/approve - PurchaseOrderController@approve
  Future<Response<dynamic>> poapipostpurchaseOrdersIdApprove({required String po, required Map<String, dynamic> data}) {
    final url = '/api/v1/purchase-orders/${po}/approve';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /purchase-orders/{po}/purchase - PurchaseOrderController@purchase
  Future<Response<dynamic>> poapipostpurchaseOrdersIdPurchase({required String po, required Map<String, dynamic> data}) {
    final url = '/api/v1/purchase-orders/${po}/purchase';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /purchase-orders/{po}/receive - PurchaseOrderController@receive
  Future<Response<dynamic>> poapipostpurchaseOrdersIdReceive({required String po, required Map<String, dynamic> data}) {
    final url = '/api/v1/purchase-orders/${po}/receive';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /reading-periods - MeterReadingController@openPeriod
  Future<Response<dynamic>> rootapipostreadingPeriods({required Map<String, dynamic> data}) {
    const url = '/api/v1/reading-periods';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /reading-periods/{period}/close - MeterReadingController@closePeriod
  Future<Response<dynamic>> periodapipostreadingPeriodsIdClose({required String period, required Map<String, dynamic> data}) {
    final url = '/api/v1/reading-periods/${period}/close';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /refresh-token - AuthController@refreshToken
  Future<Response<dynamic>> rootapipostrefreshToken({required Map<String, dynamic> data}) {
    const url = '/api/v1/refresh-token';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /roles - RoleController@store
  Future<Response<dynamic>> rootapipostroles({required Map<String, dynamic> data}) {
    const url = '/api/v1/roles';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /stock-transfers - StockTransferController@store
  Future<Response<dynamic>> rootapipoststockTransfers({required Map<String, dynamic> data}) {
    const url = '/api/v1/stock-transfers';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /stock-transfers/{transfer}/approve - StockTransferController@approve
  Future<Response<dynamic>> transferapipoststockTransfersIdApprove({required String transfer, required Map<String, dynamic> data}) {
    final url = '/api/v1/stock-transfers/${transfer}/approve';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /stock-transfers/{transfer}/receive - StockTransferController@receive
  Future<Response<dynamic>> transferapipoststockTransfersIdReceive({required String transfer, required Map<String, dynamic> data}) {
    final url = '/api/v1/stock-transfers/${transfer}/receive';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /survey-reports/{report}/review - ProspectController@reviewSurvey
  Future<Response<dynamic>> reportapipostsurveyReportsIdReview({required String report, required Map<String, dynamic> data}) {
    final url = '/api/v1/survey-reports/${report}/review';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /sync/upload - SyncController@upload
  Future<Response<dynamic>> uploadapipostsyncUpload({required Map<String, dynamic> data}) {
    const url = '/api/v1/sync/upload';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /tariffs - TariffController@store
  Future<Response<dynamic>> rootapiposttariffs({required Map<String, dynamic> data}) {
    const url = '/api/v1/tariffs';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /tenders - TenderController@store
  Future<Response<dynamic>> rootapiposttenders({required Map<String, dynamic> data}) {
    const url = '/api/v1/tenders';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /tenders/{tender}/award - TenderController@award
  Future<Response<dynamic>> tenderapiposttendersIdAward({required String tender, required Map<String, dynamic> data}) {
    final url = '/api/v1/tenders/${tender}/award';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /tenders/{tender}/bid - TenderController@submitBid
  Future<Response<dynamic>> tenderapiposttendersIdBid({required String tender, required Map<String, dynamic> data}) {
    final url = '/api/v1/tenders/${tender}/bid';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /tenders/{tender}/evaluate - TenderController@evaluate
  Future<Response<dynamic>> tenderapiposttendersIdEvaluate({required String tender, required Map<String, dynamic> data}) {
    final url = '/api/v1/tenders/${tender}/evaluate';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /users - UserController@store
  Future<Response<dynamic>> rootapipostusers({required Map<String, dynamic> data}) {
    const url = '/api/v1/users';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /users/{user}/roles - UserController@syncRoles
  Future<Response<dynamic>> userapipostusersIdRoles({required String user, required Map<String, dynamic> data}) {
    final url = '/api/v1/users/${user}/roles';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /users/{user}/zone - UserController@assignZone
  Future<Response<dynamic>> userapipostusersIdZone({required String user, required Map<String, dynamic> data}) {
    final url = '/api/v1/users/${user}/zone';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /vendors - VendorController@store
  Future<Response<dynamic>> rootapipostvendors({required Map<String, dynamic> data}) {
    const url = '/api/v1/vendors';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /vendors/{vendor}/evaluate - VendorController@evaluate
  Future<Response<dynamic>> vendorapipostvendorsIdEvaluate({required String vendor, required Map<String, dynamic> data}) {
    final url = '/api/v1/vendors/${vendor}/evaluate';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /warehouse/adjustment - WarehouseController@stockAdjustment
  Future<Response<dynamic>> adjustmentapipostwarehouseAdjustment({required Map<String, dynamic> data}) {
    const url = '/api/v1/warehouse/adjustment';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /warehouse/stock-out - WarehouseController@stockOut
  Future<Response<dynamic>> stockoutapipostwarehouseStockOut({required Map<String, dynamic> data}) {
    const url = '/api/v1/warehouse/stock-out';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /webhook - IntegrationController@webhook
  Future<Response<dynamic>> rootapipostwebhook({required Map<String, dynamic> data}) {
    const url = '/api/v1/webhook';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /webhooks/midtrans - PaymentWebhookController@handle
  Future<Response<dynamic>> midtransapipostwebhooksMidtrans({required Map<String, dynamic> data}) {
    const url = '/api/v1/webhooks/midtrans';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /webhooks/xendit - XenditWebhookController@handle
  Future<Response<dynamic>> xenditapipostwebhooksXendit({required Map<String, dynamic> data}) {
    const url = '/api/v1/webhooks/xendit';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /work-orders - WorkOrderController@store
  Future<Response<dynamic>> rootapipostworkOrders({required Map<String, dynamic> data}) {
    const url = '/api/v1/work-orders';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /work-orders/{workOrder}/assign - WorkOrderController@assign
  Future<Response<dynamic>> workorderapipostworkOrdersIdAssign({required String workOrder, required Map<String, dynamic> data}) {
    final url = '/api/v1/work-orders/${workOrder}/assign';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /work-orders/{workOrder}/complete - WorkOrderController@complete
  Future<Response<dynamic>> workorderapipostworkOrdersIdComplete({required String workOrder, required Map<String, dynamic> data}) {
    final url = '/api/v1/work-orders/${workOrder}/complete';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /work-orders/{workOrder}/start - WorkOrderController@start
  Future<Response<dynamic>> workorderapipostworkOrdersIdStart({required String workOrder, required Map<String, dynamic> data}) {
    final url = '/api/v1/work-orders/${workOrder}/start';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /zones - ZoneController@store
  Future<Response<dynamic>> rootapipostzones({required Map<String, dynamic> data}) {
    const url = '/api/v1/zones';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [POST] /zones/{zone}/deactivate - ZoneController@deactivate
  Future<Response<dynamic>> zoneapipostzonesIdDeactivate({required String zone, required Map<String, dynamic> data}) {
    final url = '/api/v1/zones/${zone}/deactivate';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'POST'));
  }

  /// [PUT] /address/cities/{city} - AddressController@updateCity
  Future<Response<dynamic>> citiesapiputaddressCitiesId({required String city, required Map<String, dynamic> data}) {
    final url = '/api/v1/address/cities/${city}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /address/districts/{district} - AddressController@updateDistrict
  Future<Response<dynamic>> districtsapiputaddressDistrictsId({required String district, required Map<String, dynamic> data}) {
    final url = '/api/v1/address/districts/${district}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /address/provinces/{province} - AddressController@updateProvince
  Future<Response<dynamic>> provincesapiputaddressProvincesId({required String province, required Map<String, dynamic> data}) {
    final url = '/api/v1/address/provinces/${province}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /address/streets/{street} - AddressController@updateStreet
  Future<Response<dynamic>> streetsapiputaddressStreetsId({required String street, required Map<String, dynamic> data}) {
    final url = '/api/v1/address/streets/${street}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /address/villages/{village} - AddressController@updateVillage
  Future<Response<dynamic>> villagesapiputaddressVillagesId({required String village, required Map<String, dynamic> data}) {
    final url = '/api/v1/address/villages/${village}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /asset-categories/{assetCategory} - AssetCategoryController@update
  Future<Response<dynamic>> assetcategoryapiputassetCategoriesId({required String assetCategory, required Map<String, dynamic> data}) {
    final url = '/api/v1/asset-categories/${assetCategory}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /bi/scheduled-reports/{scheduledReport} - ScheduledReportController@update
  Future<Response<dynamic>> scheduledreportsapiputbiScheduledReportsId({required String scheduledReport, required Map<String, dynamic> data}) {
    final url = '/api/v1/bi/scheduled-reports/${scheduledReport}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /change-password - AuthController@changePassword
  Future<Response<dynamic>> rootapiputchangePassword({required Map<String, dynamic> data}) {
    const url = '/api/v1/change-password';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /customers/{customer} - CustomerController@update
  Future<Response<dynamic>> customerapiputcustomersId({required String customer, required Map<String, dynamic> data}) {
    final url = '/api/v1/customers/${customer}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /employees/{employee} - HrEmployeeController@update
  Future<Response<dynamic>> employeeapiputemployeesId({required String employee, required Map<String, dynamic> data}) {
    final url = '/api/v1/employees/${employee}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /gis/pipes/{pipe} - GisEditorController@updatePipe
  Future<Response<dynamic>> pipesapiputgisPipesId({required String pipe, required Map<String, dynamic> data}) {
    final url = '/api/v1/gis/pipes/${pipe}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /hr/trainings/{training}/participants/{participant} - HrTrainingController@updateParticipant
  Future<Response<dynamic>> trainingsapiputhrTrainingsIdParticipantsId({required String training, required String participant, required Map<String, dynamic> data}) {
    final url = '/api/v1/hr/trainings/${training}/participants/${participant}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /maintenance/schedules/{schedule} - MaintenanceController@updateSchedule
  Future<Response<dynamic>> schedulesapiputmaintenanceSchedulesId({required String schedule, required Map<String, dynamic> data}) {
    final url = '/api/v1/maintenance/schedules/${schedule}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /meter-routes/{route} - MeterRouteController@update
  Future<Response<dynamic>> routeapiputmeterRoutesId({required String route, required Map<String, dynamic> data}) {
    final url = '/api/v1/meter-routes/${route}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /meters/{meter} - MeterController@update
  Future<Response<dynamic>> meterapiputmetersId({required String meter, required Map<String, dynamic> data}) {
    final url = '/api/v1/meters/${meter}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /notification-preferences - NotificationController@updatePreferences
  Future<Response<dynamic>> rootapiputnotificationPreferences({required Map<String, dynamic> data}) {
    const url = '/api/v1/notification-preferences';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /portal/profile - CustomerPortalController@updateProfile
  Future<Response<dynamic>> profileapiputportalProfile({required Map<String, dynamic> data}) {
    const url = '/api/v1/portal/profile';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /profile/update - AuthController@updateProfile
  Future<Response<dynamic>> updateapiputprofileUpdate({required Map<String, dynamic> data}) {
    const url = '/api/v1/profile/update';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /prospects/{prospect} - ProspectController@update
  Future<Response<dynamic>> prospectapiputprospectsId({required String prospect, required Map<String, dynamic> data}) {
    final url = '/api/v1/prospects/${prospect}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /roles/{role} - RoleController@update
  Future<Response<dynamic>> roleapiputrolesId({required String role, required Map<String, dynamic> data}) {
    final url = '/api/v1/roles/${role}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /tariffs/{tariffCategory} - TariffController@update
  Future<Response<dynamic>> tariffcategoryapiputtariffsId({required String tariffCategory, required Map<String, dynamic> data}) {
    final url = '/api/v1/tariffs/${tariffCategory}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /users/{user} - UserController@update
  Future<Response<dynamic>> userapiputusersId({required String user, required Map<String, dynamic> data}) {
    final url = '/api/v1/users/${user}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /vendors/{vendor} - VendorController@update
  Future<Response<dynamic>> vendorapiputvendorsId({required String vendor, required Map<String, dynamic> data}) {
    final url = '/api/v1/vendors/${vendor}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

  /// [PUT] /zones/{zone} - ZoneController@update
  Future<Response<dynamic>> zoneapiputzonesId({required String zone, required Map<String, dynamic> data}) {
    final url = '/api/v1/zones/${zone}';
    return _dio.request<dynamic>(url, data: data, options: Options(method: 'PUT'));
  }

}

/// Total operasi: 372; path: 296; tag: 133.
const int generatedOperationCount = 372;
