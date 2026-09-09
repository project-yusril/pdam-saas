import 'package:dio/dio.dart';

import '../../../../api/api_services.dart';
import '../../../../core/network/api_client.dart';

/// SurveyRemoteSource — selaras backend SRV (ProspectController).
/// Catatan: draft survey disimpan LOKAL (CacheManager), backend tidak
/// menyediakan endpoint draft. Sinkronisasi draft memakai /sync.
class SurveyRemoteSource {
  SurveyRemoteSource({required ApiClient apiClient})
      : _api = SurveyApi(apiClient);

  final SurveyApi _api;

  /// Daftar prospect/tugas survey (paginated).
  /// Backend: GET /prospects
  Future<Map<String, dynamic>> getTasks({
    int page = 1,
    int perPage = 10,
    String? status,
  }) async {
    final response = await _api.getTasks(page: page, perPage: perPage, status: status);
    return response.data as Map<String, dynamic>;
  }

  /// Detail prospect. Backend: GET /prospects/{id}
  Future<Map<String, dynamic>> getTaskDetail(int taskId) async {
    final response = await _api.getTaskDetail(taskId);
    return response.data as Map<String, dynamic>;
  }

  /// Submit hasil survey. Backend: POST /prospects/{id}/survey
  Future<Map<String, dynamic>> submitSurvey(
    int taskId,
    Map<String, dynamic> data,
  ) async {
    final response = await _api.submitSurvey(taskId, data);
    return response.data as Map<String, dynamic>;
  }

  /// Upload foto survey ke storage privat (signed upload).
  /// Backend via UploadFileController: POST /files/upload.
  Future<Map<String, dynamic>> uploadPhoto(String filePath) async {
    final formData = FormData.fromMap({
      'purpose': 'survey',
      'file': await MultipartFile.fromFile(filePath),
    });
    final response = await _api.uploadFile(formData);
    return response.data as Map<String, dynamic>;
  }
}
