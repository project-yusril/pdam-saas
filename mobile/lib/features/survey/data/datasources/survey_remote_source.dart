import 'package:dio/dio.dart';
import '../../../../core/network/api_client.dart';
import '../../../../core/network/endpoints.dart';

/// SurveyRemoteSource — selaras backend SRV (ProspectController).
/// Catatan: draft survey disimpan LOKAL (CacheManager), backend tidak
/// menyediakan endpoint draft. Sinkronisasi draft memakai /sync.
class SurveyRemoteSource {
  final ApiClient _apiClient;

  SurveyRemoteSource({required ApiClient apiClient}) : _apiClient = apiClient;

  /// Daftar prospect/tugas survey (paginated).
  /// Backend: GET /prospects
  Future<Map<String, dynamic>> getTasks({
    int page = 1,
    int perPage = 10,
    String? status,
  }) async {
    final queryParams = <String, dynamic>{'page': page, 'per_page': perPage};
    if (status != null && status.isNotEmpty) {
      queryParams['status'] = status;
    }
    final response = await _apiClient.get(
      Endpoints.surveyTasks,
      queryParameters: queryParams,
    );
    return response.data as Map<String, dynamic>;
  }

  /// Detail prospect. Backend: GET /prospects/{id}
  Future<Map<String, dynamic>> getTaskDetail(int taskId) async {
    final path = Endpoints.surveyTaskDetail(taskId);
    final response = await _apiClient.get(path);
    return response.data as Map<String, dynamic>;
  }

  /// Submit hasil survey. Backend: POST /prospects/{id}/survey
  Future<Map<String, dynamic>> submitSurvey(
    int taskId,
    Map<String, dynamic> data,
  ) async {
    final path = Endpoints.surveySubmit(taskId);
    final response = await _apiClient.post(path, data: data);
    return response.data as Map<String, dynamic>;
  }

  /// Upload foto ke storage privat (signed URL flow).
  /// Backend: POST /files/signed-url
  Future<Map<String, dynamic>> uploadPhoto(String filePath) async {
    final formData = FormData.fromMap({
      'purpose': 'survey',
      'file': await MultipartFile.fromFile(filePath),
    });
    final response = await _apiClient.uploadFile(
      Endpoints.uploadFile,
      formData: formData,
    );
    return response.data as Map<String, dynamic>;
  }
}
