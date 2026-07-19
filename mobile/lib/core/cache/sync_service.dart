import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/api_client.dart';
import '../network/endpoints.dart';
import 'cache_manager.dart';

abstract interface class SyncQueue {
  List<Map<String, dynamic>> pendingMeterReadings();
  List<Map<String, dynamic>> pendingSurveys();
  Future<void> removeMeterReading(String clientUuid);
  Future<void> removeSurvey(String clientUuid);
  Future<void> cacheMeterReading({
    required int customerId,
    required double readingValue,
    required String period,
    String? photoMeterUrl,
    String? photoHouseUrl,
    double? latitude,
    double? longitude,
  });
  int get pendingCount;
}

abstract interface class SyncConnectivity {
  Future<bool> hasConnection();
  Stream<bool> get connectionChanges;
}

abstract interface class SyncTransport {
  Future<Object?> upload(String type, List<Map<String, dynamic>> payloads);
}

final syncServiceProvider = Provider<SyncService>((ref) {
  return SyncService(
    queue: CacheSyncQueue(CacheManager()),
    connectivity: DeviceSyncConnectivity(Connectivity()),
    transport: ApiSyncTransport(ApiClient()),
  );
});

class CacheSyncQueue implements SyncQueue {
  CacheSyncQueue(this._cache);

  final CacheManager _cache;

  @override
  List<Map<String, dynamic>> pendingMeterReadings() =>
      _cache.getPendingMeterReadings();

  @override
  List<Map<String, dynamic>> pendingSurveys() =>
      _cache.getPendingSurveyDrafts();

  @override
  Future<void> removeMeterReading(String clientUuid) =>
      _cache.removeMeterReading(clientUuid);

  @override
  Future<void> removeSurvey(String clientUuid) =>
      _cache.removeSurveyDraft(clientUuid);

  @override
  Future<void> cacheMeterReading({
    required int customerId,
    required double readingValue,
    required String period,
    String? photoMeterUrl,
    String? photoHouseUrl,
    double? latitude,
    double? longitude,
  }) =>
      _cache.cacheMeterReading(
        customerId: customerId,
        readingValue: readingValue,
        period: period,
        photoMeterUrl: photoMeterUrl,
        photoHouseUrl: photoHouseUrl,
        latitude: latitude,
        longitude: longitude,
      );

  @override
  int get pendingCount => _cache.pendingCount;
}

class DeviceSyncConnectivity implements SyncConnectivity {
  DeviceSyncConnectivity(this._connectivity);

  final Connectivity _connectivity;

  @override
  Future<bool> hasConnection() async =>
      await _connectivity.checkConnectivity() != ConnectivityResult.none;

  @override
  Stream<bool> get connectionChanges => _connectivity.onConnectivityChanged
      .map((result) => result != ConnectivityResult.none)
      .distinct();
}

class ApiSyncTransport implements SyncTransport {
  ApiSyncTransport(this._api);

  final ApiClient _api;

  @override
  Future<Object?> upload(
    String type,
    List<Map<String, dynamic>> payloads,
  ) async {
    final response = await _api.post<Object?>(
      Endpoints.syncUpload,
      data: {'type': type, 'payloads': payloads},
    );
    return response.data;
  }
}

class SyncService {
  SyncService({
    required SyncQueue queue,
    required SyncConnectivity connectivity,
    required SyncTransport transport,
  })  : _queue = queue,
        _connectivity = connectivity,
        _transport = transport;

  final SyncQueue _queue;
  final SyncConnectivity _connectivity;
  final SyncTransport _transport;
  StreamSubscription<bool>? _connectivitySubscription;
  final StreamController<SyncStatus> _syncStatusController =
      StreamController<SyncStatus>.broadcast();
  bool _isSyncing = false;

  Stream<SyncStatus> get syncStatusStream => _syncStatusController.stream;

  void startListening() {
    _connectivitySubscription ??=
        _connectivity.connectionChanges.listen((connected) {
      if (connected) _triggerAutoSync();
    });
    _connectivity.hasConnection().then((connected) {
      if (connected) _triggerAutoSync();
    });
  }

  void stopListening() {
    _connectivitySubscription?.cancel();
    _connectivitySubscription = null;
  }

  Future<void> cacheMeterReading({
    required int customerId,
    required double readingValue,
    required String period,
    String? photoMeterUrl,
    String? photoHouseUrl,
    double? latitude,
    double? longitude,
  }) =>
      _queue.cacheMeterReading(
        customerId: customerId,
        readingValue: readingValue,
        period: period,
        photoMeterUrl: photoMeterUrl,
        photoHouseUrl: photoHouseUrl,
        latitude: latitude,
        longitude: longitude,
      );

  void dispose() {
    stopListening();
    _syncStatusController.close();
  }

  Future<void> _triggerAutoSync() async {
    if (!_isSyncing && _queue.pendingCount > 0) await syncAll();
  }

  Future<SyncResult> syncAll() async {
    if (_isSyncing) return SyncResult.alreadySyncing();
    if (!await _connectivity.hasConnection()) return SyncResult.noConnection();

    _isSyncing = true;
    _syncStatusController.add(SyncStatus.syncing);
    final errors = <String>[];
    var meterSynced = 0;
    var meterFailed = 0;
    var surveySynced = 0;
    var surveyFailed = 0;

    try {
      final meters = _queue.pendingMeterReadings();
      if (meters.isNotEmpty) {
        final outcome = await _uploadBatch(
          label: 'Meter reading',
          type: 'meter_readings',
          queued: meters,
          payloadFor: _buildMeterPayload,
          remove: _queue.removeMeterReading,
        );
        meterSynced = outcome.synced;
        meterFailed = outcome.failed;
        errors.addAll(outcome.errors);
      }

      final surveys = _queue.pendingSurveys();
      if (surveys.isNotEmpty) {
        final outcome = await _uploadBatch(
          label: 'Survey',
          type: 'survey_reports',
          queued: surveys,
          payloadFor: _buildSurveyPayload,
          remove: _queue.removeSurvey,
        );
        surveySynced = outcome.synced;
        surveyFailed = outcome.failed;
        errors.addAll(outcome.errors);
      }

      final result = SyncResult.success(
        meterSynced: meterSynced,
        meterFailed: meterFailed,
        surveySynced: surveySynced,
        surveyFailed: surveyFailed,
        errors: errors,
      );
      _syncStatusController.add(
        result.success ? SyncStatus.completed : SyncStatus.partial,
      );
      return result;
    } finally {
      _isSyncing = false;
    }
  }

  Future<_BatchOutcome> _uploadBatch({
    required String label,
    required String type,
    required List<Map<String, dynamic>> queued,
    required Map<String, dynamic> Function(Map<String, dynamic>) payloadFor,
    required Future<void> Function(String) remove,
  }) async {
    final payloads = queued.map(payloadFor).toList();
    final submitted = <String>{};
    for (final payload in payloads) {
      final uuid = payload['client_uuid'];
      if (uuid is! String || uuid.isEmpty || !submitted.add(uuid)) {
        return _BatchOutcome(
          failed: queued.length,
          errors: [
            '$label queue contains an invalid or duplicate client_uuid.'
          ],
        );
      }
    }

    Object? response;
    try {
      response = await _transport.upload(type, payloads);
    } on Exception catch (error) {
      return _BatchOutcome(
        failed: submitted.length,
        errors: ['$label batch sync failed: $error'],
      );
    }

    final rows = _resultRows(response);
    if (rows == null) {
      return _BatchOutcome(
        failed: submitted.length,
        errors: ['$label batch returned a malformed response.'],
      );
    }

    final byUuid = <String, List<Map<String, dynamic>>>{};
    var malformedRows = 0;
    for (final row in rows) {
      if (row is! Map) {
        malformedRows++;
        continue;
      }
      final normalized = Map<String, dynamic>.from(row);
      final uuid = normalized['client_uuid'];
      if (uuid is! String || uuid.isEmpty) {
        malformedRows++;
        continue;
      }
      byUuid.putIfAbsent(uuid, () => []).add(normalized);
    }

    var synced = 0;
    var failed = 0;
    final errors = <String>[];
    if (malformedRows > 0) {
      errors.add(
          '$label batch contained $malformedRows malformed result row(s).');
    }
    for (final uuid in byUuid.keys.where((uuid) => !submitted.contains(uuid))) {
      errors.add('$label batch returned unknown client_uuid $uuid.');
    }
    for (final uuid in submitted) {
      final matches = byUuid[uuid] ?? const [];
      if (matches.isEmpty) {
        failed++;
        errors.add('$label $uuid: missing result.');
        continue;
      }
      if (matches.length != 1) {
        failed++;
        errors.add('$label $uuid: duplicate results.');
        continue;
      }
      final row = matches.single;
      final status = row['status'];
      if (status == 'created' || status == 'duplicate') {
        await remove(uuid);
        synced++;
      } else {
        failed++;
        errors.add('$label $uuid: ${row['error_code'] ?? 'invalid status'}');
      }
    }
    return _BatchOutcome(synced: synced, failed: failed, errors: errors);
  }

  List<dynamic>? _resultRows(Object? response) {
    if (response is! Map) return null;
    final data = response['data'];
    if (data is! Map) return null;
    final results = data['results'];
    return results is List ? results : null;
  }

  Map<String, dynamic> _buildMeterPayload(Map<String, dynamic> cached) => {
        'customer_id': cached['customer_id'],
        'client_uuid': cached['key'],
        'reading_value': cached['reading_value'],
        'reading_date':
            cached['reading_date'] ?? DateTime.now().toIso8601String(),
        'reading_type': 'offline_sync',
        'photo_meter_url': cached['photo_meter_url'],
        'photo_house_url': cached['photo_house_url'],
        'latitude': cached['latitude'],
        'longitude': cached['longitude'],
        'notes': cached['notes'],
        'period': cached['period'],
        'cached_at': cached['cached_at'],
      };

  Map<String, dynamic> _buildSurveyPayload(Map<String, dynamic> cached) => {
        'photo_house_urls': cached['photo_house_urls'] ?? [],
        'client_uuid': cached['key'],
        'prospect_id': cached['prospect_id'],
        'distance_to_main_pipe': cached['distance_to_main_pipe'],
        'building_condition': cached['building_condition'],
        'accessibility': cached['accessibility'],
        'land_status': cached['land_status'],
        'estimated_materials': cached['estimated_materials'],
        'latitude': cached['latitude'],
        'longitude': cached['longitude'],
        'surveyor_notes': cached['surveyor_notes'],
        'recommendation': cached['recommendation'],
      };

  bool get isSyncing => _isSyncing;
  int get pendingSyncCount => _queue.pendingCount;
}

class _BatchOutcome {
  const _BatchOutcome(
      {this.synced = 0, this.failed = 0, this.errors = const []});
  final int synced;
  final int failed;
  final List<String> errors;
}

enum SyncStatus { idle, syncing, completed, partial, error }

class SyncResult {
  final bool success;
  final bool alreadySyncing;
  final bool noConnection;
  final int meterSynced;
  final int meterFailed;
  final int surveySynced;
  final int surveyFailed;
  final List<String> errors;

  SyncResult._({
    required this.success,
    this.alreadySyncing = false,
    this.noConnection = false,
    this.meterSynced = 0,
    this.meterFailed = 0,
    this.surveySynced = 0,
    this.surveyFailed = 0,
    this.errors = const [],
  });

  factory SyncResult.success({
    required int meterSynced,
    required int meterFailed,
    required int surveySynced,
    required int surveyFailed,
    required List<String> errors,
  }) =>
      SyncResult._(
        success: meterFailed == 0 && surveyFailed == 0 && errors.isEmpty,
        meterSynced: meterSynced,
        meterFailed: meterFailed,
        surveySynced: surveySynced,
        surveyFailed: surveyFailed,
        errors: errors,
      );

  factory SyncResult.alreadySyncing() =>
      SyncResult._(success: false, alreadySyncing: true);

  factory SyncResult.noConnection() =>
      SyncResult._(success: false, noConnection: true);
}
