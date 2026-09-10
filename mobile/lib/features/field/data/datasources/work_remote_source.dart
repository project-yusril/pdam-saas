import '../../../../api/api_services.dart';
import '../../../../core/network/api_client.dart';
import '../../domain/models/work_order.dart';

/// WorkRemoteSource — data tugas Field Service (FSM).
/// Selaras backend WorkOrderController: GET /work-orders (mine=1 utk
/// "WO Saya"), POST /work-orders/{id}/start|complete, GET /{id} (detail+log).
class WorkRemoteSource {
  WorkRemoteSource({required ApiClient apiClient}) : _api = WorkApi(apiClient);

  final WorkApi _api;

  Future<List<WorkOrderModel>> listMine({
    String? status,
    int page = 1,
    int perPage = 25,
  }) async {
    final response = await _api.list(
        mine: true, status: status, page: page, perPage: perPage);
    final data = (response.data as Map)['data'] as List? ?? const [];

    return data
        .map((e) => WorkOrderModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<({WorkOrderModel order, List<Map<String, dynamic>> logs})>
      showWithLogs(int id) async {
    final response = await _api.show(id);
    final data = (response.data as Map)['data'] ?? response.data;
    final map = (data as Map).cast<String, dynamic>();

    return (
      order: WorkOrderModel.fromJson(map),
      logs: (map['logs'] as List? ?? const [])
          .map((e) => (e as Map).cast<String, dynamic>())
          .toList(),
    );
  }

  /// Mulai pekerjaan — backend wajib status assigned|in_progress.
  Future<void> start(int id) => _api.start(id);

  /// Selesaikan dengan resolusi (wajib dari field).
  Future<void> complete(int id, {required String resolution}) =>
      _api.complete(id, {'resolution': resolution});
}
