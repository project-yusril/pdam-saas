import 'package:dio/dio.dart';
import '../../../../core/network/api_client.dart';
import '../../../../core/network/endpoints.dart';

class MeterRemoteSource {
  final ApiClient _apiClient;

  MeterRemoteSource({required ApiClient apiClient}) : _apiClient = apiClient;

  Future<Map<String, dynamic>> getRoutes() async {
    final response = await _apiClient.get(Endpoints.meterRoutes);
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> getRouteTasks(int routeId) async {
    final path = Endpoints.meterRouteTasks(routeId);
    final response = await _apiClient.get(path);
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> getReadingHistory({
    int page = 1,
    int perPage = 20,
  }) async {
    final response = await _apiClient.get(
      Endpoints.meterReadingHistory,
      queryParameters: {'page': page, 'per_page': perPage},
    );
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> submitReading(Map<String, dynamic> data) async {
    final response = await _apiClient.post(
      Endpoints.meterReadingSubmit,
      data: data,
    );
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> uploadMeterPhoto(String filePath) async {
    final formData = FormData.fromMap({
      'purpose': 'meter',
      'file': await MultipartFile.fromFile(filePath),
    });
    final response = await _apiClient.uploadFile(
      Endpoints.uploadFile,
      formData: formData,
    );
    return response.data as Map<String, dynamic>;
  }
}
