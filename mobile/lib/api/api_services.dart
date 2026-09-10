import 'package:dio/dio.dart';

import '../core/network/api_client.dart';
import '../core/network/endpoints.dart';

/// Lapisan typed di atas ApiClient: satu class per tag API supaya repository tak
/// lagi menyuntik `Endpoints.*` / Dio. Base URL, interceptor pin/CSRF/offline
/// ApiClient tetap dipertahankan. (H-10: migrasi call-site repository mobile).
class SurveyApi {
  const SurveyApi(this._api);
  final ApiClient _api;

  /// GET /prospects
  Future<Response<dynamic>> getTasks({
    int page = 1,
    int perPage = 10,
    String? status,
  }) {
    final q = <String, dynamic>{'page': page, 'per_page': perPage};
    if (status != null && status.isNotEmpty) q['status'] = status;
    return _api.get(Endpoints.surveyTasks, queryParameters: q);
  }

  Future<Response<dynamic>> getTaskDetail(int taskId) =>
      _api.get(Endpoints.surveyTaskDetail(taskId));

  Future<Response<dynamic>> submitSurvey(int taskId, Map<String, dynamic> data) =>
      _api.post(Endpoints.surveySubmit(taskId), data: data);

  Future<Response<dynamic>> uploadFile(FormData form) =>
      _api.uploadFile(Endpoints.uploadFile, formData: form);
}

/// OCR KTP (ProspectController/upload-ktp + /parse)
class ProspectApi {
  const ProspectApi(this._api);
  final ApiClient _api;

  Future<Response<dynamic>> uploadKtp(FormData form) =>
      _api.uploadFile(Endpoints.ocrKtpUpload, formData: form, options: Options(headers: {'Accept': 'application/json'}));

  Future<Response<dynamic>> parseKtp(Map<String, dynamic> data) =>
      _api.post(Endpoints.ocrKtpParse, data: data);
}

/// Dashboard & tagihan & notifikasi pelanggan (CustomerPortalController)
class PortalApi {
  const PortalApi(this._api);
  final ApiClient _api;

  Future<Response<dynamic>> customerDashboard() => _api.get(Endpoints.customerDashboard);
  Future<Response<dynamic>> bills({int page = 1, int perPage = 10}) =>
      _api.get(Endpoints.customerBills, queryParameters: {'page': page, 'per_page': perPage});
  Future<Response<dynamic>> billDetail(int id) => _api.get(Endpoints.customerBillDetail(id));
  Future<Response<dynamic>> payBill(int id, Map<String, dynamic> data) =>
      _api.post(Endpoints.customerBillPay(id), data: data);
  Future<Response<dynamic>> usageHistory({int? months}) {
    final q = <String, dynamic>{'page': 1};
    if (months != null) q['months'] = months;
    return _api.get(Endpoints.customerUsageHistory, queryParameters: q);
  }

  Future<Response<dynamic>> notifications({int page = 1}) =>
      _api.get(Endpoints.customerNotifications, queryParameters: {'page': page});
  Future<Response<dynamic>> markRead(int id) =>
      _api.post(Endpoints.customerNotificationsRead(id));
  Future<Response<dynamic>> markAllRead() => _api.post(Endpoints.customerNotificationsReadAll);
  Future<Response<dynamic>> complaints({int page = 1, int perPage = 10}) =>
      _api.get(Endpoints.customerComplaints, queryParameters: {'page': page, 'per_page': perPage});
  Future<Response<dynamic>> createComplaint(Map<String, dynamic> data) =>
      _api.post(Endpoints.customerComplaints, data: data);
  Future<Response<dynamic>> profile() => _api.get(Endpoints.customerProfile);
  Future<Response<dynamic>> updateProfile(Map<String, dynamic> data) =>
      _api.put(Endpoints.customerProfile, data: data);
}

/// Meter route & reading (MeterRouteController + MeterReadingController)
class MeterApi {
  const MeterApi(this._api);
  final ApiClient _api;

  Future<Response<dynamic>> routes({int page = 1}) =>
      _api.get(Endpoints.meterRoutes, queryParameters: {'page': page});
  Future<Response<dynamic>> routeTask(int routeId) =>
      _api.get(Endpoints.meterRouteTasks(routeId));
  Future<Response<dynamic>> submitReading(Map<String, dynamic> body) =>
      _api.post(Endpoints.meterReadingSubmit, data: body);
  Future<Response<dynamic>> readingProgress({int? routeId, int page = 1}) {
    final q = <String, dynamic>{'page': page};
    if (routeId != null) q['meter_route_id'] = routeId;
    return _api.get(Endpoints.meterReadingHistory, queryParameters: q);
  }

  Future<Response<dynamic>> uploadPhoto(FormData form) =>
      _api.uploadFile(Endpoints.uploadFile, formData: form);
}

/// Titik petugas LIVE untuk peta GIS kantor (FieldLocationController@store;
/// GET kontrak web panel — lihat `backend/SEED_DATA.md` §11)
class FieldApi {
  const FieldApi(this._api);
  final ApiClient _api;

  Future<Response<dynamic>> reportLocation({
    required double latitude,
    required double longitude,
    double? accuracy,
  }) =>
      _api.post(Endpoints.fieldLocation, data: {
        'latitude': latitude,
        'longitude': longitude,
        if (accuracy != null) 'accuracy': accuracy,
      });
}

/// Work Order teknis (WorkOrderController — FSM fase 15).
class WorkApi {
  const WorkApi(this._api);
  final ApiClient _api;

  /// mine=true → hanya WO milik user login (layar "WO Saya").
  Future<Response<dynamic>> list({bool mine = false, String? status, int page = 1, int perPage = 20}) {
    final q = <String, dynamic>{'page': page, 'per_page': perPage};
    if (mine) q['mine'] = 1;
    if (status != null && status.isNotEmpty) q['status'] = status;
    return _api.get(Endpoints.workOrders, queryParameters: q);
  }

  Future<Response<dynamic>> show(int id) => _api.get(Endpoints.workOrderDetail(id));

  Future<Response<dynamic>> start(int id) => _api.post(Endpoints.workOrderStart(id));

  Future<Response<dynamic>> complete(int id, Map<String, dynamic> body) =>
      _api.post(Endpoints.workOrderComplete(id), data: body);

  Future<Response<dynamic>> dashboard() => _api.get(Endpoints.workOrderDashboard);

  Future<Response<dynamic>> uploadPhoto(FormData form) =>
      _api.uploadFile(Endpoints.uploadFile, formData: form);
}
