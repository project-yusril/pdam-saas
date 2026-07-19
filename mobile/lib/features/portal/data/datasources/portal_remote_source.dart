import '../../../../core/network/api_client.dart';
import '../../../../core/network/endpoints.dart';

class PortalRemoteSource {
  final ApiClient _apiClient;

  PortalRemoteSource({required ApiClient apiClient}) : _apiClient = apiClient;

  Future<Map<String, dynamic>> getDashboard() async {
    final response = await _apiClient.get(Endpoints.customerDashboard);
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> getBills({
    int page = 1,
    int perPage = 10,
    String? status,
  }) async {
    final queryParams = <String, dynamic>{'page': page, 'per_page': perPage};
    if (status != null && status.isNotEmpty) {
      queryParams['status'] = status;
    }
    final response = await _apiClient.get(
      Endpoints.customerBills,
      queryParameters: queryParams,
    );
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> getBillDetail(int billId) async {
    final path = Endpoints.customerBillDetail(billId);
    final response = await _apiClient.get(path);
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> payBill(
    int billId, {
    String? paymentMethod,
  }) async {
    final path = Endpoints.customerBillPay(billId);
    final response = await _apiClient.post(
      path,
      data: {'payment_method': paymentMethod ?? 'online'},
    );
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> getUsageHistory({int months = 12}) async {
    final response = await _apiClient.get(
      Endpoints.customerUsageHistory,
      queryParameters: {'months': months},
    );
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> getNotifications({
    int page = 1,
    int perPage = 20,
  }) async {
    final response = await _apiClient.get(
      Endpoints.customerNotifications,
      queryParameters: {'page': page, 'per_page': perPage},
    );
    return response.data as Map<String, dynamic>;
  }

  Future<void> markNotificationRead(int notificationId) async {
    final path = Endpoints.customerNotificationsRead(notificationId);
    // Backend: POST /portal/notifications/{id}/read
    await _apiClient.post(path);
  }

  Future<void> markAllNotificationsRead() async {
    // Backend: POST /portal/notifications/read-all
    await _apiClient.post(Endpoints.customerNotificationsReadAll);
  }

  Future<Map<String, dynamic>> getComplaints({
    int page = 1,
    int perPage = 10,
  }) async {
    final response = await _apiClient.get(
      Endpoints.customerComplaints,
      queryParameters: {'page': page, 'per_page': perPage},
    );
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> submitComplaint(
    Map<String, dynamic> data,
  ) async {
    final response = await _apiClient.post(
      Endpoints.customerComplaints,
      data: data,
    );
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> getProfile() async {
    final response = await _apiClient.get(Endpoints.customerProfile);
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> data) async {
    final response = await _apiClient.put(
      Endpoints.customerProfile,
      data: data,
    );
    return response.data as Map<String, dynamic>;
  }
}
