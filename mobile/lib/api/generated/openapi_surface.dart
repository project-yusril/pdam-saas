// openapi_surface.dart — GENERATED oleh tools/generate_openapi_surface.dart
// dari docs/openapi.json (route registry Laravel via pdam:openapi). JANGAN EDIT MANUAL.
// Regenerate (root repo):
//   cd backend && php artisan pdam:openapi --out=../docs/openapi.json
//   dart tools/generate_openapi_surface.dart
// ignore_for_file: type=lint

/// Satu operasi API.
class ApiOp {
  const ApiOp({
    required this.method,
    required this.tag,
    required this.summary,
    required this.pathParams,
  });

  final String method;
  final String tag;
  final String summary;
  final List<String> pathParams;

  bool get isTemplate => pathParams.isNotEmpty;
}

/// Path -> {METHOD: ApiOp}. Total operasi: 371; path unik: 295.
const Map<String, Map<String, ApiOp>> apiSurface = <String, Map<String, ApiOp>>{
  '/address/cities': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Cities',
      summary: 'AddressController@cities',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Cities',
      summary: 'AddressController@storeCity',
      pathParams: const <String>[],
    ),
  },
  '/address/cities/{city}': <String, ApiOp>{
    'DELETE': ApiOp(
      method: 'DELETE',
      tag: 'Cities',
      summary: 'AddressController@destroyCity',
      pathParams: const <String>['city'],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Cities',
      summary: 'AddressController@updateCity',
      pathParams: const <String>['city'],
    ),
  },
  '/address/cities/{id}/restore': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Cities',
      summary: 'AddressController@restoreCity',
      pathParams: const <String>['id'],
    ),
  },
  '/address/districts': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Districts',
      summary: 'AddressController@districts',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Districts',
      summary: 'AddressController@storeDistrict',
      pathParams: const <String>[],
    ),
  },
  '/address/districts/{district}': <String, ApiOp>{
    'DELETE': ApiOp(
      method: 'DELETE',
      tag: 'Districts',
      summary: 'AddressController@destroyDistrict',
      pathParams: const <String>['district'],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Districts',
      summary: 'AddressController@updateDistrict',
      pathParams: const <String>['district'],
    ),
  },
  '/address/districts/{id}/restore': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Districts',
      summary: 'AddressController@restoreDistrict',
      pathParams: const <String>['id'],
    ),
  },
  '/address/provinces': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Provinces',
      summary: 'AddressController@provinces',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Provinces',
      summary: 'AddressController@storeProvince',
      pathParams: const <String>[],
    ),
  },
  '/address/provinces/{id}/restore': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Provinces',
      summary: 'AddressController@restoreProvince',
      pathParams: const <String>['id'],
    ),
  },
  '/address/provinces/{province}': <String, ApiOp>{
    'DELETE': ApiOp(
      method: 'DELETE',
      tag: 'Provinces',
      summary: 'AddressController@destroyProvince',
      pathParams: const <String>['province'],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Provinces',
      summary: 'AddressController@updateProvince',
      pathParams: const <String>['province'],
    ),
  },
  '/address/streets': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Streets',
      summary: 'AddressController@streets',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Streets',
      summary: 'AddressController@storeStreet',
      pathParams: const <String>[],
    ),
  },
  '/address/streets/{id}/restore': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Streets',
      summary: 'AddressController@restoreStreet',
      pathParams: const <String>['id'],
    ),
  },
  '/address/streets/{street}': <String, ApiOp>{
    'DELETE': ApiOp(
      method: 'DELETE',
      tag: 'Streets',
      summary: 'AddressController@destroyStreet',
      pathParams: const <String>['street'],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Streets',
      summary: 'AddressController@updateStreet',
      pathParams: const <String>['street'],
    ),
  },
  '/address/villages': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Villages',
      summary: 'AddressController@villages',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Villages',
      summary: 'AddressController@storeVillage',
      pathParams: const <String>[],
    ),
  },
  '/address/villages/{id}/restore': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Villages',
      summary: 'AddressController@restoreVillage',
      pathParams: const <String>['id'],
    ),
  },
  '/address/villages/{village}': <String, ApiOp>{
    'DELETE': ApiOp(
      method: 'DELETE',
      tag: 'Villages',
      summary: 'AddressController@destroyVillage',
      pathParams: const <String>['village'],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Villages',
      summary: 'AddressController@updateVillage',
      pathParams: const <String>['village'],
    ),
  },
  '/api-keys': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'IntegrationController@apiKeys',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'IntegrationController@createApiKey',
      pathParams: const <String>[],
    ),
  },
  '/api-keys/{apiKey}/revoke': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'ApiKey',
      summary: 'IntegrationController@revokeApiKey',
      pathParams: const <String>['apiKey'],
    ),
  },
  '/asset-categories': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'AssetCategoryController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'AssetCategoryController@store',
      pathParams: const <String>[],
    ),
  },
  '/asset-categories/{assetCategory}': <String, ApiOp>{
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'AssetCategory',
      summary: 'AssetCategoryController@update',
      pathParams: const <String>['assetCategory'],
    ),
  },
  '/assets': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'FixedAssetController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'FixedAssetController@store',
      pathParams: const <String>[],
    ),
  },
  '/assets/capitalize': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Capitalize',
      summary: 'FixedAssetController@capitalize',
      pathParams: const <String>[],
    ),
  },
  '/assets/dashboard': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Dashboard',
      summary: 'FixedAssetController@dashboard',
      pathParams: const <String>[],
    ),
  },
  '/assets/depreciation/run': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Depreciation',
      summary: 'FixedAssetController@runDepreciation',
      pathParams: const <String>[],
    ),
  },
  '/assets/opname': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Opname',
      summary: 'AssetOpnameController@opname',
      pathParams: const <String>[],
    ),
  },
  '/assets/{asset}/revaluation': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Asset',
      summary: 'AssetOpnameController@revaluation',
      pathParams: const <String>['asset'],
    ),
  },
  '/assets/{fixedAsset}': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'FixedAsset',
      summary: 'FixedAssetController@show',
      pathParams: const <String>['fixedAsset'],
    ),
  },
  '/assets/{fixedAsset}/card': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'FixedAsset',
      summary: 'FixedAssetController@card',
      pathParams: const <String>['fixedAsset'],
    ),
  },
  '/assets/{fixedAsset}/dispose': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'FixedAsset',
      summary: 'FixedAssetController@dispose',
      pathParams: const <String>['fixedAsset'],
    ),
  },
  '/assets/{fixedAsset}/move': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'FixedAsset',
      summary: 'FixedAssetController@move',
      pathParams: const <String>['fixedAsset'],
    ),
  },
  '/bi/kpi-eksekutif': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Kpi Eksekutif',
      summary: 'BiController@kpiEkskutif',
      pathParams: const <String>[],
    ),
  },
  '/bi/report-builder': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Report Builder',
      summary: 'BiController@reportBuilder',
      pathParams: const <String>[],
    ),
  },
  '/bi/scheduled-reports': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Scheduled Reports',
      summary: 'ScheduledReportController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Scheduled Reports',
      summary: 'ScheduledReportController@store',
      pathParams: const <String>[],
    ),
  },
  '/bi/scheduled-reports/{scheduledReport}': <String, ApiOp>{
    'DELETE': ApiOp(
      method: 'DELETE',
      tag: 'Scheduled Reports',
      summary: 'ScheduledReportController@destroy',
      pathParams: const <String>['scheduledReport'],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Scheduled Reports',
      summary: 'ScheduledReportController@update',
      pathParams: const <String>['scheduledReport'],
    ),
  },
  '/bi/scheduled-reports/{scheduledReport}/run': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Scheduled Reports',
      summary: 'ScheduledReportController@runNow',
      pathParams: const <String>['scheduledReport'],
    ),
  },
  '/bi/scheduled-reports/{scheduledReport}/runs': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Scheduled Reports',
      summary: 'ScheduledReportController@history',
      pathParams: const <String>['scheduledReport'],
    ),
  },
  '/bi/scheduled-reports/{scheduledReport}/runs/{run}/download': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Scheduled Reports',
      summary: 'ScheduledReportController@download',
      pathParams: const <String>['scheduledReport', 'run'],
    ),
  },
  '/bill-adjustments': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'BillAdjustmentController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'BillAdjustmentController@store',
      pathParams: const <String>[],
    ),
  },
  '/bill-adjustments/{adjustment}/approve': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Adjustment',
      summary: 'BillAdjustmentController@approve',
      pathParams: const <String>['adjustment'],
    ),
  },
  '/bill-adjustments/{adjustment}/post': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Adjustment',
      summary: 'BillAdjustmentController@post',
      pathParams: const <String>['adjustment'],
    ),
  },
  '/bill-adjustments/{adjustment}/reject': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Adjustment',
      summary: 'BillAdjustmentController@reject',
      pathParams: const <String>['adjustment'],
    ),
  },
  '/bill-audit': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'BillRecController@audit',
      pathParams: const <String>[],
    ),
  },
  '/bill-reconciliation': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'BillRecController@reconcile',
      pathParams: const <String>[],
    ),
  },
  '/bills': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'BillingController@index',
      pathParams: const <String>[],
    ),
  },
  '/bills/generate': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Generate',
      summary: 'BillingController@generate',
      pathParams: const <String>[],
    ),
  },
  '/bills/{bill}': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Bill',
      summary: 'BillingController@show',
      pathParams: const <String>['bill'],
    ),
  },
  '/call-logs': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'CallCenterController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'CallCenterController@log',
      pathParams: const <String>[],
    ),
  },
  '/call-logs/dashboard': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Dashboard',
      summary: 'CallCenterController@dashboard',
      pathParams: const <String>[],
    ),
  },
  '/change-password': <String, ApiOp>{
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Root',
      summary: 'AuthController@changePassword',
      pathParams: const <String>[],
    ),
  },
  '/chats': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'ChatController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'ChatController@store',
      pathParams: const <String>[],
    ),
  },
  '/chats/{chat}/messages': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Chat',
      summary: 'ChatController@messages',
      pathParams: const <String>['chat'],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Chat',
      summary: 'ChatController@sendMessage',
      pathParams: const <String>['chat'],
    ),
  },
  '/chem/chemicals': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Chemicals',
      summary: 'ChemController@chemicals',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Chemicals',
      summary: 'ChemController@storeChemical',
      pathParams: const <String>[],
    ),
  },
  '/chem/dashboard': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Dashboard',
      summary: 'ChemController@dashboard',
      pathParams: const <String>[],
    ),
  },
  '/chem/receipts': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Receipts',
      summary: 'ChemController@receipts',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Receipts',
      summary: 'ChemController@createReceipt',
      pathParams: const <String>[],
    ),
  },
  '/chem/receipts/{receipt}/qc': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Receipts',
      summary: 'ChemController@qcTest',
      pathParams: const <String>['receipt'],
    ),
  },
  '/chem/usage': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Usage',
      summary: 'ChemController@usage',
      pathParams: const <String>[],
    ),
  },
  '/complaints': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'ComplaintController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'ComplaintController@store',
      pathParams: const <String>[],
    ),
  },
  '/complaints/dashboard': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Dashboard',
      summary: 'ComplaintController@dashboard',
      pathParams: const <String>[],
    ),
  },
  '/complaints/{complaint}': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Complaint',
      summary: 'ComplaintController@show',
      pathParams: const <String>['complaint'],
    ),
  },
  '/complaints/{complaint}/assign': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Complaint',
      summary: 'ComplaintController@assign',
      pathParams: const <String>['complaint'],
    ),
  },
  '/complaints/{complaint}/resolve': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Complaint',
      summary: 'ComplaintController@resolve',
      pathParams: const <String>['complaint'],
    ),
  },
  '/customer-view/quick-search': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Quick Search',
      summary: 'CustomerViewController@quickSearch',
      pathParams: const <String>[],
    ),
  },
  '/customer-view/{customerId}': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'CustomerId',
      summary: 'CustomerViewController@show',
      pathParams: const <String>['customerId'],
    ),
  },
  '/customer-view/{customerId}/aggregation': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'CustomerId',
      summary: 'CustomerViewController@aggregation',
      pathParams: const <String>['customerId'],
    ),
  },
  '/customers': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'CustomerController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'CustomerController@store',
      pathParams: const <String>[],
    ),
  },
  '/customers/{customer}': <String, ApiOp>{
    'DELETE': ApiOp(
      method: 'DELETE',
      tag: 'Customer',
      summary: 'CustomerController@destroy',
      pathParams: const <String>['customer'],
    ),
    'GET': ApiOp(
      method: 'GET',
      tag: 'Customer',
      summary: 'CustomerController@show',
      pathParams: const <String>['customer'],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Customer',
      summary: 'CustomerController@update',
      pathParams: const <String>['customer'],
    ),
  },
  '/customers/{customer}/disconnect': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Customer',
      summary: 'CustomerLifecycleController@disconnect',
      pathParams: const <String>['customer'],
    ),
  },
  '/customers/{customer}/manual-reading': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Customer',
      summary: 'CustomerController@manualReading',
      pathParams: const <String>['customer'],
    ),
  },
  '/customers/{customer}/reconnect': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Customer',
      summary: 'CustomerLifecycleController@reconnect',
      pathParams: const <String>['customer'],
    ),
  },
  '/customers/{customer}/status-history': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Customer',
      summary: 'CustomerController@statusHistory',
      pathParams: const <String>['customer'],
    ),
  },
  '/customers/{customer}/transfer-ownership': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Customer',
      summary: 'CustomerLifecycleController@transferOwnership',
      pathParams: const <String>['customer'],
    ),
  },
  '/dashboard/director': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Director',
      summary: 'DashboardController@director',
      pathParams: const <String>[],
    ),
  },
  '/dashboard/executive-kpi': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Executive Kpi',
      summary: 'DashboardController@executiveKpi',
      pathParams: const <String>[],
    ),
  },
  '/dashboard/finance': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Finance',
      summary: 'DashboardController@finance',
      pathParams: const <String>[],
    ),
  },
  '/dashboard/metx': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Metx',
      summary: 'DashboardController@metx',
      pathParams: const <String>[],
    ),
  },
  '/dashboard/operations': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Operations',
      summary: 'DashboardController@operations',
      pathParams: const <String>[],
    ),
  },
  '/distribution/dma-dashboard': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Dma Dashboard',
      summary: 'DistController@dmaDashboard',
      pathParams: const <String>[],
    ),
  },
  '/distribution/ingest': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Ingest',
      summary: 'DistController@ingest',
      pathParams: const <String>[],
    ),
  },
  '/documentation': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'SwaggerController@api',
      pathParams: const <String>[],
    ),
  },
  '/documents': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'DocumentController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'DocumentController@store',
      pathParams: const <String>[],
    ),
  },
  '/documents/{document}': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Document',
      summary: 'DocumentController@show',
      pathParams: const <String>['document'],
    ),
  },
  '/documents/{document}/approve': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Document',
      summary: 'DocumentController@approve',
      pathParams: const <String>['document'],
    ),
  },
  '/employees': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'HrEmployeeController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'HrEmployeeController@store',
      pathParams: const <String>[],
    ),
  },
  '/employees/dashboard/summary': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Dashboard',
      summary: 'HrEmployeeController@dashboard',
      pathParams: const <String>[],
    ),
  },
  '/employees/{employee}': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Employee',
      summary: 'HrEmployeeController@show',
      pathParams: const <String>['employee'],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Employee',
      summary: 'HrEmployeeController@update',
      pathParams: const <String>['employee'],
    ),
  },
  '/export': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'ExportController@export',
      pathParams: const <String>[],
    ),
  },
  '/fcm-token': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'AuthController@registerFcmToken',
      pathParams: const <String>[],
    ),
  },
  '/files/signed-url': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Signed Url',
      summary: 'FileController@signedUrl',
      pathParams: const <String>[],
    ),
  },
  '/files/upload': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Upload',
      summary: 'FileController@upload',
      pathParams: const <String>[],
    ),
  },
  '/fin/ar-aging-report': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Ar Aging Report',
      summary: 'FinController@arAgingReport',
      pathParams: const <String>[],
    ),
  },
  '/fin/bank-accounts': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Bank Accounts',
      summary: 'FinController@bankAccounts',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Bank Accounts',
      summary: 'FinController@createBankAccount',
      pathParams: const <String>[],
    ),
  },
  '/fin/bank/transfer': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Bank',
      summary: 'FinAdvanceController@bankTransfer',
      pathParams: const <String>[],
    ),
  },
  '/fin/bank/{account}/reconcile': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Bank',
      summary: 'FinAdvanceController@reconcileBank',
      pathParams: const <String>['account'],
    ),
  },
  '/fin/bank/{account}/summary': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Bank',
      summary: 'FinAdvanceController@bankSummary',
      pathParams: const <String>['account'],
    ),
  },
  '/fin/budgets': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Budgets',
      summary: 'FinController@budgets',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Budgets',
      summary: 'FinController@createBudget',
      pathParams: const <String>[],
    ),
  },
  '/fin/currencies': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Currencies',
      summary: 'FinController@currencies',
      pathParams: const <String>[],
    ),
  },
  '/fin/projects': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Projects',
      summary: 'FinController@projects',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Projects',
      summary: 'FinController@createProject',
      pathParams: const <String>[],
    ),
  },
  '/fin/reconciliations': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Reconciliations',
      summary: 'FinAdvanceController@reconciliations',
      pathParams: const <String>[],
    ),
  },
  '/fin/recurring-transactions': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Recurring Transactions',
      summary: 'FinController@recurringTransactions',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Recurring Transactions',
      summary: 'FinController@createRecurring',
      pathParams: const <String>[],
    ),
  },
  '/fin/sales-invoices': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Sales Invoices',
      summary: 'FinController@salesInvoices',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Sales Invoices',
      summary: 'FinController@createSalesInvoice',
      pathParams: const <String>[],
    ),
  },
  '/fin/tax-records': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Tax Records',
      summary: 'FinController@taxRecords',
      pathParams: const <String>[],
    ),
  },
  '/fin/tax/calculate': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Tax',
      summary: 'FinAdvanceController@taxCalculate',
      pathParams: const <String>[],
    ),
  },
  '/fin/tax/efaktur-export': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Tax',
      summary: 'FinAdvanceController@exportEfaktur',
      pathParams: const <String>[],
    ),
  },
  '/fin/tax/record': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Tax',
      summary: 'FinAdvanceController@taxRecord',
      pathParams: const <String>[],
    ),
  },
  '/gis/customers': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Customers',
      summary: 'GisController@customerGeoJson',
      pathParams: const <String>[],
    ),
  },
  '/gis/customers/status-summary': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Customers',
      summary: 'GisController@statusSummary',
      pathParams: const <String>[],
    ),
  },
  '/gis/features': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Features',
      summary: 'GisEditorController@features',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Features',
      summary: 'GisEditorController@createFeature',
      pathParams: const <String>[],
    ),
  },
  '/gis/network-edges': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Network Edges',
      summary: 'GisEditorController@networkEdges',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Network Edges',
      summary: 'GisEditorController@createEdge',
      pathParams: const <String>[],
    ),
  },
  '/gis/pipes': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Pipes',
      summary: 'GisEditorController@pipes',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Pipes',
      summary: 'GisEditorController@createPipe',
      pathParams: const <String>[],
    ),
  },
  '/gis/pipes/{pipe}': <String, ApiOp>{
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Pipes',
      summary: 'GisEditorController@updatePipe',
      pathParams: const <String>['pipe'],
    ),
  },
  '/hr/appraisals': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Appraisals',
      summary: 'HrTrainingController@appraisals',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Appraisals',
      summary: 'HrTrainingController@createAppraisal',
      pathParams: const <String>[],
    ),
  },
  '/hr/appraisals/{appraisal}/approve': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Appraisals',
      summary: 'HrTrainingController@approveAppraisal',
      pathParams: const <String>['appraisal'],
    ),
  },
  '/hr/attendances': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Attendances',
      summary: 'AttendanceController@index',
      pathParams: const <String>[],
    ),
  },
  '/hr/attendances/check-in': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Attendances',
      summary: 'AttendanceController@checkIn',
      pathParams: const <String>[],
    ),
  },
  '/hr/attendances/check-out': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Attendances',
      summary: 'AttendanceController@checkOut',
      pathParams: const <String>[],
    ),
  },
  '/hr/certifications': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Certifications',
      summary: 'HrTrainingController@certifications',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Certifications',
      summary: 'HrTrainingController@createCertification',
      pathParams: const <String>[],
    ),
  },
  '/hr/certifications/expiring': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Certifications',
      summary: 'HrTrainingController@expiringCertifications',
      pathParams: const <String>[],
    ),
  },
  '/hr/contracts': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Contracts',
      summary: 'HrAdvanceController@contracts',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Contracts',
      summary: 'HrAdvanceController@createContract',
      pathParams: const <String>[],
    ),
  },
  '/hr/contracts/terminating-soon': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Contracts',
      summary: 'HrAdvanceController@terminatingSoon',
      pathParams: const <String>[],
    ),
  },
  '/hr/leaves': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Leaves',
      summary: 'AttendanceController@leaves',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Leaves',
      summary: 'AttendanceController@requestLeave',
      pathParams: const <String>[],
    ),
  },
  '/hr/leaves/{leave}/approve': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Leaves',
      summary: 'AttendanceController@approveLeave',
      pathParams: const <String>['leave'],
    ),
  },
  '/hr/overtime': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Overtime',
      summary: 'AttendanceController@overtimeRequests',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Overtime',
      summary: 'AttendanceController@requestOvertime',
      pathParams: const <String>[],
    ),
  },
  '/hr/overtime/{overtime}/approve': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Overtime',
      summary: 'AttendanceController@approveOvertime',
      pathParams: const <String>['overtime'],
    ),
  },
  '/hr/shifts': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Shifts',
      summary: 'AttendanceController@shifts',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Shifts',
      summary: 'AttendanceController@createShift',
      pathParams: const <String>[],
    ),
  },
  '/hr/terminations': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Terminations',
      summary: 'HrAdvanceController@terminations',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Terminations',
      summary: 'HrAdvanceController@createTermination',
      pathParams: const <String>[],
    ),
  },
  '/hr/terminations/{termination}/approve': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Terminations',
      summary: 'HrAdvanceController@approveTermination',
      pathParams: const <String>['termination'],
    ),
  },
  '/hr/trainings': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Trainings',
      summary: 'HrTrainingController@trainings',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Trainings',
      summary: 'HrTrainingController@createTraining',
      pathParams: const <String>[],
    ),
  },
  '/hr/trainings/{training}/participants': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Trainings',
      summary: 'HrTrainingController@addParticipant',
      pathParams: const <String>['training'],
    ),
  },
  '/hr/trainings/{training}/participants/{participant}': <String, ApiOp>{
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Trainings',
      summary: 'HrTrainingController@updateParticipant',
      pathParams: const <String>['training', 'participant'],
    ),
  },
  '/installations/{schedule}/complete': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Schedule',
      summary: 'InstallationController@complete',
      pathParams: const <String>['schedule'],
    ),
  },
  '/integration-logs': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'IntegrationController@logs',
      pathParams: const <String>[],
    ),
  },
  '/integrations': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'IntegrationController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'IntegrationController@store',
      pathParams: const <String>[],
    ),
  },
  '/integrations/test-send': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Test Send',
      summary: 'IntegrationController@testSend',
      pathParams: const <String>[],
    ),
  },
  '/integrations/{integration}/toggle': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Integration',
      summary: 'IntegrationController@toggle',
      pathParams: const <String>['integration'],
    ),
  },
  '/inventory/balance-sheet': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Balance Sheet',
      summary: 'InventoryController@balanceSheet',
      pathParams: const <String>[],
    ),
  },
  '/inventory/valuation': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Valuation',
      summary: 'InventoryController@valuation',
      pathParams: const <String>[],
    ),
  },
  '/iot/dashboard': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Dashboard',
      summary: 'IotController@dashboard',
      pathParams: const <String>[],
    ),
  },
  '/iot/ingest': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Ingest',
      summary: 'IotController@ingest',
      pathParams: const <String>[],
    ),
  },
  '/journal/manual': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Manual',
      summary: 'InventoryController@manualJournal',
      pathParams: const <String>[],
    ),
  },
  '/login': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'AuthController@login',
      pathParams: const <String>[],
    ),
  },
  '/logout': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'AuthController@logout',
      pathParams: const <String>[],
    ),
  },
  '/maintenance/records': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Records',
      summary: 'MaintenanceController@records',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Records',
      summary: 'MaintenanceController@createRecord',
      pathParams: const <String>[],
    ),
  },
  '/maintenance/schedules': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Schedules',
      summary: 'MaintenanceController@schedules',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Schedules',
      summary: 'MaintenanceController@createSchedule',
      pathParams: const <String>[],
    ),
  },
  '/maintenance/schedules/{schedule}': <String, ApiOp>{
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Schedules',
      summary: 'MaintenanceController@updateSchedule',
      pathParams: const <String>['schedule'],
    ),
  },
  '/marketplace/catalog': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Catalog',
      summary: 'MarketplaceController@catalog',
      pathParams: const <String>[],
    ),
  },
  '/marketplace/purchases': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Purchases',
      summary: 'MarketplaceController@purchase',
      pathParams: const <String>[],
    ),
  },
  '/material-orders/{order}/cancel': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Order',
      summary: 'InstallationController@cancelMaterials',
      pathParams: const <String>['order'],
    ),
  },
  '/material-orders/{order}/issue': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Order',
      summary: 'InstallationController@issueMaterials',
      pathParams: const <String>['order'],
    ),
  },
  '/me': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'AuthController@me',
      pathParams: const <String>[],
    ),
  },
  '/meter-anomalies': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'MeterAnomalyController@index',
      pathParams: const <String>[],
    ),
  },
  '/meter-anomalies/scan': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Scan',
      summary: 'MeterAnomalyController@scan',
      pathParams: const <String>[],
    ),
  },
  '/meter-anomalies/{anomaly}/confirm': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Anomaly',
      summary: 'MeterAnomalyController@confirm',
      pathParams: const <String>['anomaly'],
    ),
  },
  '/meter-anomalies/{anomaly}/dismiss': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Anomaly',
      summary: 'MeterAnomalyController@dismiss',
      pathParams: const <String>['anomaly'],
    ),
  },
  '/meter-anomalies/{anomaly}/review': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Anomaly',
      summary: 'MeterAnomalyController@review',
      pathParams: const <String>['anomaly'],
    ),
  },
  '/meter-readings': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'MeterReadingController@store',
      pathParams: const <String>[],
    ),
  },
  '/meter-readings/parse': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Parse',
      summary: 'MeterReadingController@parseMeter',
      pathParams: const <String>[],
    ),
  },
  '/meter-readings/report': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Report',
      summary: 'MeterReadingController@report',
      pathParams: const <String>[],
    ),
  },
  '/meter-readings/route-progress': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Route Progress',
      summary: 'MeterReadingController@routeProgress',
      pathParams: const <String>[],
    ),
  },
  '/meter-readings/{reading}/verify': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Reading',
      summary: 'MeterReadingController@verify',
      pathParams: const <String>['reading'],
    ),
  },
  '/meter-routes': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'MeterRouteController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'MeterRouteController@store',
      pathParams: const <String>[],
    ),
  },
  '/meter-routes/auto-map': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Auto Map',
      summary: 'MeterRouteController@autoMap',
      pathParams: const <String>[],
    ),
  },
  '/meter-routes/{route}': <String, ApiOp>{
    'DELETE': ApiOp(
      method: 'DELETE',
      tag: 'Route',
      summary: 'MeterRouteController@destroy',
      pathParams: const <String>['route'],
    ),
    'GET': ApiOp(
      method: 'GET',
      tag: 'Route',
      summary: 'MeterRouteController@show',
      pathParams: const <String>['route'],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Route',
      summary: 'MeterRouteController@update',
      pathParams: const <String>['route'],
    ),
  },
  '/meter-routes/{route}/officer': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Route',
      summary: 'MeterRouteController@assignOfficer',
      pathParams: const <String>['route'],
    ),
  },
  '/meter-routes/{route}/streets': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Route',
      summary: 'MeterRouteController@syncStreets',
      pathParams: const <String>['route'],
    ),
  },
  '/meters': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'MeterController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'MeterController@store',
      pathParams: const <String>[],
    ),
  },
  '/meters/{meter}': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Meter',
      summary: 'MeterController@show',
      pathParams: const <String>['meter'],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Meter',
      summary: 'MeterController@update',
      pathParams: const <String>['meter'],
    ),
  },
  '/meters/{meter}/assign': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Meter',
      summary: 'MeterController@assign',
      pathParams: const <String>['meter'],
    ),
  },
  '/meters/{meter}/recalibrate': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Meter',
      summary: 'MeterController@recalibrate',
      pathParams: const <String>['meter'],
    ),
  },
  '/meters/{meter}/remove': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Meter',
      summary: 'MeterController@remove',
      pathParams: const <String>['meter'],
    ),
  },
  '/meters/{meter}/scrap': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Meter',
      summary: 'MeterController@scrap',
      pathParams: const <String>['meter'],
    ),
  },
  '/metx/dashboard': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Dashboard',
      summary: 'MeterAnomalyController@dashboard',
      pathParams: const <String>[],
    ),
  },
  '/mfa/disable': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Disable',
      summary: 'MfaController@disable',
      pathParams: const <String>[],
    ),
  },
  '/mfa/enable': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Enable',
      summary: 'MfaController@enable',
      pathParams: const <String>[],
    ),
  },
  '/mfa/recovery-codes': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Recovery Codes',
      summary: 'MfaController@getRecoveryCodes',
      pathParams: const <String>[],
    ),
  },
  '/mfa/recovery-codes/regenerate': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Recovery Codes',
      summary: 'MfaController@regenerateRecoveryCodes',
      pathParams: const <String>[],
    ),
  },
  '/mfa/setup': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Setup',
      summary: 'MfaController@setup',
      pathParams: const <String>[],
    ),
  },
  '/mfa/verify': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Verify',
      summary: 'MfaController@verify',
      pathParams: const <String>[],
    ),
  },
  '/mobile/cities': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Cities',
      summary: 'MobileDropdownController@cities',
      pathParams: const <String>[],
    ),
  },
  '/mobile/districts': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Districts',
      summary: 'MobileDropdownController@districts',
      pathParams: const <String>[],
    ),
  },
  '/mobile/provinces': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Provinces',
      summary: 'MobileDropdownController@provinces',
      pathParams: const <String>[],
    ),
  },
  '/mobile/streets': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Streets',
      summary: 'MobileDropdownController@streets',
      pathParams: const <String>[],
    ),
  },
  '/mobile/villages': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Villages',
      summary: 'MobileDropdownController@villages',
      pathParams: const <String>[],
    ),
  },
  '/notification-preferences': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'NotificationController@preferences',
      pathParams: const <String>[],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Root',
      summary: 'NotificationController@updatePreferences',
      pathParams: const <String>[],
    ),
  },
  '/notification-preferences/test-push': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Test Push',
      summary: 'NotificationController@testPush',
      pathParams: const <String>[],
    ),
  },
  '/nrw/calculate': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Calculate',
      summary: 'NrwController@calculate',
      pathParams: const <String>[],
    ),
  },
  '/nrw/dashboard': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Dashboard',
      summary: 'NrwController@dashboard',
      pathParams: const <String>[],
    ),
  },
  '/oauth2-callback': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'SwaggerController@oauth2Callback',
      pathParams: const <String>[],
    ),
  },
  '/payments': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'PaymentController@createPayment',
      pathParams: const <String>[],
    ),
  },
  '/payments/cash': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Cash',
      summary: 'PaymentController@cashPayment',
      pathParams: const <String>[],
    ),
  },
  '/payments/check/{orderId}': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Check',
      summary: 'PaymentController@checkStatus',
      pathParams: const <String>['orderId'],
    ),
  },
  '/payments/history': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'History',
      summary: 'PaymentController@paymentHistory',
      pathParams: const <String>[],
    ),
  },
  '/payments/{payment}/receipt': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Payment',
      summary: 'PaymentController@downloadReceipt',
      pathParams: const <String>['payment'],
    ),
  },
  '/payments/{payment}/refund': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Payment',
      summary: 'PaymentController@refund',
      pathParams: const <String>['payment'],
    ),
  },
  '/payments/{payment}/refunds': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Payment',
      summary: 'PaymentController@refunds',
      pathParams: const <String>['payment'],
    ),
  },
  '/payroll/batch-run': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Batch Run',
      summary: 'PayrollController@batchRun',
      pathParams: const <String>[],
    ),
  },
  '/payroll/calculate': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Calculate',
      summary: 'PayrollController@calculate',
      pathParams: const <String>[],
    ),
  },
  '/payroll/slip': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Slip',
      summary: 'PayrollController@slip',
      pathParams: const <String>[],
    ),
  },
  '/platform/login': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Login',
      summary: 'PlatformAuthController@login',
      pathParams: const <String>[],
    ),
  },
  '/platform/logout': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Logout',
      summary: 'PlatformAuthController@logout',
      pathParams: const <String>[],
    ),
  },
  '/platform/marketplace/bundles': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Marketplace',
      summary: 'MarketplaceController@bundles',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Marketplace',
      summary: 'MarketplaceController@createBundle',
      pathParams: const <String>[],
    ),
  },
  '/platform/marketplace/catalog': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Marketplace',
      summary: 'MarketplaceController@catalog',
      pathParams: const <String>[],
    ),
  },
  '/platform/marketplace/dashboard': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Marketplace',
      summary: 'MarketplaceController@superAdminDashboard',
      pathParams: const <String>[],
    ),
  },
  '/platform/marketplace/prices': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Marketplace',
      summary: 'MarketplaceController@managePrice',
      pathParams: const <String>[],
    ),
  },
  '/platform/marketplace/promos': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Marketplace',
      summary: 'MarketplaceController@promos',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Marketplace',
      summary: 'MarketplaceController@createPromo',
      pathParams: const <String>[],
    ),
  },
  '/platform/marketplace/purchase': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Marketplace',
      summary: 'MarketplaceController@purchase',
      pathParams: const <String>[],
    ),
  },
  '/platform/modules': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Modules',
      summary: 'ModuleCatalogController@index',
      pathParams: const <String>[],
    ),
  },
  '/platform/tenants': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Tenants',
      summary: 'TenantController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Tenants',
      summary: 'TenantController@store',
      pathParams: const <String>[],
    ),
  },
  '/platform/tenants/{tenant}': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Tenants',
      summary: 'TenantController@show',
      pathParams: const <String>['tenant'],
    ),
  },
  '/platform/tenants/{tenant}/modules': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Tenants',
      summary: 'TenantModuleController@index',
      pathParams: const <String>['tenant'],
    ),
  },
  '/platform/tenants/{tenant}/modules/{moduleCode}/activate': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Tenants',
      summary: 'TenantModuleController@activate',
      pathParams: const <String>['tenant', 'moduleCode'],
    ),
  },
  '/platform/tenants/{tenant}/modules/{moduleCode}/lock': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Tenants',
      summary: 'TenantModuleController@lock',
      pathParams: const <String>['tenant', 'moduleCode'],
    ),
  },
  '/platform/tenants/{tenant}/toggle-status': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Tenants',
      summary: 'TenantController@toggleStatus',
      pathParams: const <String>['tenant'],
    ),
  },
  '/portal/bills': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Bills',
      summary: 'CustomerPortalController@bills',
      pathParams: const <String>[],
    ),
  },
  '/portal/bills/{bill}': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Bills',
      summary: 'CustomerPortalController@billDetail',
      pathParams: const <String>['bill'],
    ),
  },
  '/portal/bills/{bill}/pay': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Bills',
      summary: 'CustomerPortalController@payBill',
      pathParams: const <String>['bill'],
    ),
  },
  '/portal/complaints': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Complaints',
      summary: 'CustomerPortalController@complaints',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Complaints',
      summary: 'CustomerPortalController@createComplaint',
      pathParams: const <String>[],
    ),
  },
  '/portal/complaints/{complaint}': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Complaints',
      summary: 'CustomerPortalController@complaint',
      pathParams: const <String>['complaint'],
    ),
  },
  '/portal/consumption-chart': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Consumption Chart',
      summary: 'CustomerPortalController@consumptionChart',
      pathParams: const <String>[],
    ),
  },
  '/portal/dashboard': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Dashboard',
      summary: 'CustomerPortalController@dashboard',
      pathParams: const <String>[],
    ),
  },
  '/portal/notifications': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Notifications',
      summary: 'CustomerPortalController@notifications',
      pathParams: const <String>[],
    ),
  },
  '/portal/notifications/read-all': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Notifications',
      summary: 'CustomerPortalController@markAllNotificationsRead',
      pathParams: const <String>[],
    ),
  },
  '/portal/notifications/{notificationId}/read': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Notifications',
      summary: 'CustomerPortalController@markNotificationRead',
      pathParams: const <String>['notificationId'],
    ),
  },
  '/portal/profile': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Profile',
      summary: 'CustomerPortalController@profile',
      pathParams: const <String>[],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Profile',
      summary: 'CustomerPortalController@updateProfile',
      pathParams: const <String>[],
    ),
  },
  '/portal/usage-history': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Usage History',
      summary: 'CustomerPortalController@consumptionChart',
      pathParams: const <String>[],
    ),
  },
  '/privacy/delete-my-data': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Delete My Data',
      summary: 'DataPrivacyController@deleteMyData',
      pathParams: const <String>[],
    ),
  },
  '/privacy/export-my-data': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Export My Data',
      summary: 'DataPrivacyController@exportMyData',
      pathParams: const <String>[],
    ),
  },
  '/privacy/purge-old-data': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Purge Old Data',
      summary: 'DataPrivacyController@purgeOldData',
      pathParams: const <String>[],
    ),
  },
  '/privacy/purge-old-data/{purgeRequest}/approve': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Purge Old Data',
      summary: 'DataPrivacyController@approvePurge',
      pathParams: const <String>['purgeRequest'],
    ),
  },
  '/privacy/retention-status': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Retention Status',
      summary: 'DataPrivacyController@retentionStatus',
      pathParams: const <String>[],
    ),
  },
  '/production/dashboard': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Dashboard',
      summary: 'ProductionController@dashboard',
      pathParams: const <String>[],
    ),
  },
  '/production/ingest': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Ingest',
      summary: 'ProductionController@ingest',
      pathParams: const <String>[],
    ),
  },
  '/production/metrics': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Metrics',
      summary: 'ProductionController@metrics',
      pathParams: const <String>[],
    ),
  },
  '/profile/update': <String, ApiOp>{
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Update',
      summary: 'AuthController@updateProfile',
      pathParams: const <String>[],
    ),
  },
  '/prospects': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'ProspectController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'ProspectController@store',
      pathParams: const <String>[],
    ),
  },
  '/prospects/parse-ktp': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Parse Ktp',
      summary: 'ProspectController@parseKtp',
      pathParams: const <String>[],
    ),
  },
  '/prospects/upload-ktp': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Upload Ktp',
      summary: 'ProspectController@uploadKtp',
      pathParams: const <String>[],
    ),
  },
  '/prospects/{prospect}': <String, ApiOp>{
    'DELETE': ApiOp(
      method: 'DELETE',
      tag: 'Prospect',
      summary: 'ProspectController@destroy',
      pathParams: const <String>['prospect'],
    ),
    'GET': ApiOp(
      method: 'GET',
      tag: 'Prospect',
      summary: 'ProspectController@show',
      pathParams: const <String>['prospect'],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Prospect',
      summary: 'ProspectController@update',
      pathParams: const <String>['prospect'],
    ),
  },
  '/prospects/{prospect}/activate': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Prospect',
      summary: 'InstallationController@activate',
      pathParams: const <String>['prospect'],
    ),
  },
  '/prospects/{prospect}/assign-surveyor': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Prospect',
      summary: 'ProspectController@assignSurveyor',
      pathParams: const <String>['prospect'],
    ),
  },
  '/prospects/{prospect}/material-orders': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Prospect',
      summary: 'InstallationController@materialOrders',
      pathParams: const <String>['prospect'],
    ),
  },
  '/prospects/{prospect}/order-materials': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Prospect',
      summary: 'InstallationController@orderMaterials',
      pathParams: const <String>['prospect'],
    ),
  },
  '/prospects/{prospect}/pay-installation': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Prospect',
      summary: 'ProspectController@payInstallation',
      pathParams: const <String>['prospect'],
    ),
  },
  '/prospects/{prospect}/schedule-installation': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Prospect',
      summary: 'InstallationController@schedule',
      pathParams: const <String>['prospect'],
    ),
  },
  '/prospects/{prospect}/survey': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Prospect',
      summary: 'ProspectController@submitSurvey',
      pathParams: const <String>['prospect'],
    ),
  },
  '/purchase-orders': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'PurchaseOrderController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'PurchaseOrderController@store',
      pathParams: const <String>[],
    ),
  },
  '/purchase-orders/{po}/approve': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Po',
      summary: 'PurchaseOrderController@approve',
      pathParams: const <String>['po'],
    ),
  },
  '/purchase-orders/{po}/purchase': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Po',
      summary: 'PurchaseOrderController@purchase',
      pathParams: const <String>['po'],
    ),
  },
  '/purchase-orders/{po}/receive': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Po',
      summary: 'PurchaseOrderController@receive',
      pathParams: const <String>['po'],
    ),
  },
  '/reading-periods': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'MeterReadingController@openPeriod',
      pathParams: const <String>[],
    ),
  },
  '/reading-periods/{period}/close': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Period',
      summary: 'MeterReadingController@closePeriod',
      pathParams: const <String>['period'],
    ),
  },
  '/refresh-token': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'AuthController@refreshToken',
      pathParams: const <String>[],
    ),
  },
  '/reports/accounting/balance-sheet': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Accounting',
      summary: 'AccountingReportController@balanceSheet',
      pathParams: const <String>[],
    ),
  },
  '/reports/accounting/cash-flow': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Accounting',
      summary: 'AccountingReportController@cashFlow',
      pathParams: const <String>[],
    ),
  },
  '/reports/accounting/general-ledger': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Accounting',
      summary: 'AccountingReportController@generalLedger',
      pathParams: const <String>[],
    ),
  },
  '/reports/accounting/income-statement': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Accounting',
      summary: 'AccountingReportController@incomeStatement',
      pathParams: const <String>[],
    ),
  },
  '/reports/accounting/trial-balance': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Accounting',
      summary: 'AccountingReportController@trialBalance',
      pathParams: const <String>[],
    ),
  },
  '/roles': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'RoleController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'RoleController@store',
      pathParams: const <String>[],
    ),
  },
  '/roles/{role}': <String, ApiOp>{
    'DELETE': ApiOp(
      method: 'DELETE',
      tag: 'Role',
      summary: 'RoleController@destroy',
      pathParams: const <String>['role'],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Role',
      summary: 'RoleController@update',
      pathParams: const <String>['role'],
    ),
  },
  '/session': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'AuthController@session',
      pathParams: const <String>[],
    ),
  },
  '/stock-transfers': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'StockTransferController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'StockTransferController@store',
      pathParams: const <String>[],
    ),
  },
  '/stock-transfers/{transfer}/approve': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Transfer',
      summary: 'StockTransferController@approve',
      pathParams: const <String>['transfer'],
    ),
  },
  '/stock-transfers/{transfer}/receive': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Transfer',
      summary: 'StockTransferController@receive',
      pathParams: const <String>['transfer'],
    ),
  },
  '/survey-reports/{report}/review': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Report',
      summary: 'ProspectController@reviewSurvey',
      pathParams: const <String>['report'],
    ),
  },
  '/sync/download': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Download',
      summary: 'SyncController@download',
      pathParams: const <String>[],
    ),
  },
  '/sync/status': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Status',
      summary: 'SyncController@status',
      pathParams: const <String>[],
    ),
  },
  '/sync/upload': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Upload',
      summary: 'SyncController@upload',
      pathParams: const <String>[],
    ),
  },
  '/tariffs': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'TariffController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'TariffController@store',
      pathParams: const <String>[],
    ),
  },
  '/tariffs/{tariffCategory}': <String, ApiOp>{
    'DELETE': ApiOp(
      method: 'DELETE',
      tag: 'TariffCategory',
      summary: 'TariffController@destroy',
      pathParams: const <String>['tariffCategory'],
    ),
    'GET': ApiOp(
      method: 'GET',
      tag: 'TariffCategory',
      summary: 'TariffController@show',
      pathParams: const <String>['tariffCategory'],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'TariffCategory',
      summary: 'TariffController@update',
      pathParams: const <String>['tariffCategory'],
    ),
  },
  '/tenders': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'TenderController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'TenderController@store',
      pathParams: const <String>[],
    ),
  },
  '/tenders/{tender}': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Tender',
      summary: 'TenderController@show',
      pathParams: const <String>['tender'],
    ),
  },
  '/tenders/{tender}/award': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Tender',
      summary: 'TenderController@award',
      pathParams: const <String>['tender'],
    ),
  },
  '/tenders/{tender}/bid': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Tender',
      summary: 'TenderController@submitBid',
      pathParams: const <String>['tender'],
    ),
  },
  '/tenders/{tender}/evaluate': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Tender',
      summary: 'TenderController@evaluate',
      pathParams: const <String>['tender'],
    ),
  },
  '/users': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'UserController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'UserController@store',
      pathParams: const <String>[],
    ),
  },
  '/users/{user}': <String, ApiOp>{
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'User',
      summary: 'UserController@update',
      pathParams: const <String>['user'],
    ),
  },
  '/users/{user}/roles': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'User',
      summary: 'UserController@syncRoles',
      pathParams: const <String>['user'],
    ),
  },
  '/users/{user}/zone': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'User',
      summary: 'UserController@assignZone',
      pathParams: const <String>['user'],
    ),
  },
  '/vendors': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'VendorController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'VendorController@store',
      pathParams: const <String>[],
    ),
  },
  '/vendors/{vendor}': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Vendor',
      summary: 'VendorController@show',
      pathParams: const <String>['vendor'],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Vendor',
      summary: 'VendorController@update',
      pathParams: const <String>['vendor'],
    ),
  },
  '/vendors/{vendor}/evaluate': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Vendor',
      summary: 'VendorController@evaluate',
      pathParams: const <String>['vendor'],
    ),
  },
  '/warehouse/adjustment': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Adjustment',
      summary: 'WarehouseController@stockAdjustment',
      pathParams: const <String>[],
    ),
  },
  '/warehouse/dashboard': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Dashboard',
      summary: 'WarehouseController@stockDashboard',
      pathParams: const <String>[],
    ),
  },
  '/warehouse/stock-out': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Stock Out',
      summary: 'WarehouseController@stockOut',
      pathParams: const <String>[],
    ),
  },
  '/webhook': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'IntegrationController@webhook',
      pathParams: const <String>[],
    ),
  },
  '/webhooks/midtrans': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Midtrans',
      summary: 'PaymentWebhookController@handle',
      pathParams: const <String>[],
    ),
  },
  '/work-orders': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'WorkOrderController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'WorkOrderController@store',
      pathParams: const <String>[],
    ),
  },
  '/work-orders/technician/dashboard': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Technician',
      summary: 'WorkOrderController@technicianDashboard',
      pathParams: const <String>[],
    ),
  },
  '/work-orders/{workOrder}': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'WorkOrder',
      summary: 'WorkOrderController@show',
      pathParams: const <String>['workOrder'],
    ),
  },
  '/work-orders/{workOrder}/assign': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'WorkOrder',
      summary: 'WorkOrderController@assign',
      pathParams: const <String>['workOrder'],
    ),
  },
  '/work-orders/{workOrder}/complete': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'WorkOrder',
      summary: 'WorkOrderController@complete',
      pathParams: const <String>['workOrder'],
    ),
  },
  '/work-orders/{workOrder}/start': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'WorkOrder',
      summary: 'WorkOrderController@start',
      pathParams: const <String>['workOrder'],
    ),
  },
  '/zones': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Root',
      summary: 'ZoneController@index',
      pathParams: const <String>[],
    ),
    'POST': ApiOp(
      method: 'POST',
      tag: 'Root',
      summary: 'ZoneController@store',
      pathParams: const <String>[],
    ),
  },
  '/zones/dashboard': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Dashboard',
      summary: 'ZoneDashboardController@all',
      pathParams: const <String>[],
    ),
  },
  '/zones/{zone}': <String, ApiOp>{
    'DELETE': ApiOp(
      method: 'DELETE',
      tag: 'Zone',
      summary: 'ZoneController@destroy',
      pathParams: const <String>['zone'],
    ),
    'GET': ApiOp(
      method: 'GET',
      tag: 'Zone',
      summary: 'ZoneController@show',
      pathParams: const <String>['zone'],
    ),
    'PUT': ApiOp(
      method: 'PUT',
      tag: 'Zone',
      summary: 'ZoneController@update',
      pathParams: const <String>['zone'],
    ),
  },
  '/zones/{zone}/dashboard': <String, ApiOp>{
    'GET': ApiOp(
      method: 'GET',
      tag: 'Zone',
      summary: 'ZoneDashboardController@show',
      pathParams: const <String>['zone'],
    ),
  },
  '/zones/{zone}/deactivate': <String, ApiOp>{
    'POST': ApiOp(
      method: 'POST',
      tag: 'Zone',
      summary: 'ZoneController@deactivate',
      pathParams: const <String>['zone'],
    ),
  },
};

/// true bila pasangan path+method dikenal oleh registry OpenAPI.
bool apiKnows(String path, String method) {
  final Map<String, ApiOp>? ops = apiSurface[path];
  if (ops == null) return false;
  return ops.containsKey(method.toUpperCase());
}
