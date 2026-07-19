import '../../../../core/network/api_client.dart';
import '../../../../core/network/endpoints.dart';

class AuthRemoteSource {
  final ApiTransport _apiClient;

  AuthRemoteSource({required ApiTransport apiClient}) : _apiClient = apiClient;

  Future<Map<String, dynamic>> login({
    required String pdamCode,
    required String email,
    required String password,
  }) async {
    final response = await _apiClient.post(
      Endpoints.login,
      data: {
        'pdam_code': pdamCode,
        'email': email,
        'password': password,
        'device_name': 'pdam-mobile',
      },
    );
    final body = response.data as Map<String, dynamic>;
    final token = body['token'];
    if (token is String && token.isNotEmpty) _apiClient.updateToken(token);
    return body;
  }

  Future<void> logout() async {
    await _apiClient.post(Endpoints.logout);
  }

  Future<String> refreshToken() async {
    final response = await _apiClient.post(Endpoints.refreshToken);
    final body = response.data as Map<String, dynamic>;
    final token = body['token'];
    if (token is! String || token.isEmpty) {
      throw const FormatException('Refresh response is missing token.');
    }
    _apiClient.updateToken(token);
    return token;
  }

  Future<Map<String, dynamic>> getProfile() async {
    final response = await _apiClient.get(Endpoints.me);
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> data) async {
    final response = await _apiClient.put(Endpoints.updateProfile, data: data);
    return response.data as Map<String, dynamic>;
  }

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    await _apiClient.put(
      Endpoints.changePassword,
      data: {'current_password': currentPassword, 'new_password': newPassword},
    );
  }

  Future<void> registerFcmToken(String fcmToken) async {
    await _apiClient.post(
      Endpoints.registerFcmToken,
      data: {'fcm_token': fcmToken},
    );
  }
}
