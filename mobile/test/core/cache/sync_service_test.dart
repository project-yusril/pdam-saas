import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:pdam_mobile/core/cache/sync_service.dart';

void main() {
  group('SyncService reconciliation', () {
    test('reconciles mixed meter and survey results independently', () async {
      final queue = _Queue(meters: [
        _item('m1'),
        _item('m2')
      ], surveys: [
        _item('s1'),
        _item('s2'),
      ]);
      final transport = _Transport({
        'meter_readings': _response([
          _result('m1', 'created'),
          _result('m2', 'failed', code: 'PERIOD_CLOSED'),
        ]),
        'survey_reports': _response([
          _result('s1', 'duplicate'),
          _result('s2', 'failed', code: 'INVALID_STATE'),
        ]),
      });

      final result = await _service(queue, transport).syncAll();

      expect(result.meterSynced, 1);
      expect(result.meterFailed, 1);
      expect(result.surveySynced, 1);
      expect(result.surveyFailed, 1);
      expect(queue.removedMeters, ['m1']);
      expect(queue.removedSurveys, ['s1']);
    });

    test('retains failed row and retries it on next invocation', () async {
      final queue = _Queue(meters: [_item('m1')]);
      final transport = _Transport.sequential([
        _response([_result('m1', 'failed', code: 'RETRY')]),
        _response([_result('m1', 'created')]),
      ]);
      final service = _service(queue, transport);

      final first = await service.syncAll();
      final second = await service.syncAll();

      expect(first.meterFailed, 1);
      expect(second.meterSynced, 1);
      expect(transport.calls, 2);
      expect(queue.removedMeters, ['m1']);
    });

    test('whole request failure retains and counts every submitted row',
        () async {
      final queue = _Queue(meters: [_item('m1'), _item('m2')]);
      final transport = _Transport.throwing();

      final result = await _service(queue, transport).syncAll();

      expect(result.meterFailed, 2);
      expect(result.meterSynced, 0);
      expect(queue.removedMeters, isEmpty);
    });

    test('no connection does not read queues or call transport', () async {
      final queue = _Queue(meters: [_item('m1')]);
      final transport = _Transport({});
      final service = SyncService(
        queue: queue,
        connectivity: _Connectivity(false),
        transport: transport,
      );

      final result = await service.syncAll();

      expect(result.noConnection, isTrue);
      expect(queue.reads, 0);
      expect(transport.calls, 0);
    });

    test('malformed response fails all submitted rows without deletion',
        () async {
      final queue = _Queue(meters: [_item('m1'), _item('m2')]);
      final result =
          await _service(queue, _Transport({'meter_readings': []})).syncAll();

      expect(result.meterFailed, 2);
      expect(queue.removedMeters, isEmpty);
    });

    test('missing result is failed and retained', () async {
      final queue = _Queue(meters: [_item('m1'), _item('m2')]);
      final transport = _Transport({
        'meter_readings': _response([_result('m1', 'created')]),
      });

      final result = await _service(queue, transport).syncAll();

      expect(result.meterSynced, 1);
      expect(result.meterFailed, 1);
      expect(queue.removedMeters, ['m1']);
      expect(result.errors.singleWhere((e) => e.contains('m2')),
          contains('missing'));
    });

    test('unknown result never deletes or overcounts submitted rows', () async {
      final queue = _Queue(meters: [_item('m1')]);
      final transport = _Transport({
        'meter_readings': _response([
          _result('m1', 'created'),
          _result('unknown', 'created'),
        ]),
      });

      final result = await _service(queue, transport).syncAll();

      expect(result.meterSynced, 1);
      expect(result.meterFailed, 0);
      expect(result.success, isFalse);
      expect(queue.removedMeters, ['m1']);
    });

    test('duplicate result rows fail once and retain item', () async {
      final queue = _Queue(meters: [_item('m1')]);
      final transport = _Transport({
        'meter_readings': _response([
          _result('m1', 'created'),
          _result('m1', 'duplicate'),
        ]),
      });

      final result = await _service(queue, transport).syncAll();

      expect(result.meterSynced, 0);
      expect(result.meterFailed, 1);
      expect(queue.removedMeters, isEmpty);
    });

    test('malformed rows cannot acknowledge submitted items', () async {
      final queue = _Queue(meters: [_item('m1')]);
      final transport = _Transport({
        'meter_readings': _response([
          null,
          'bad',
          {'status': 'created'}
        ]),
      });

      final result = await _service(queue, transport).syncAll();

      expect(result.meterFailed, 1);
      expect(queue.removedMeters, isEmpty);
      expect(result.errors, hasLength(2));
    });

    test('meter payload includes backend-required offline reading type',
        () async {
      final queue = _Queue(meters: [_item('m1')]);
      final transport = _Transport({
        'meter_readings': _response([_result('m1', 'created')]),
      });

      await _service(queue, transport).syncAll();

      expect(transport.payloads.single.single['reading_type'], 'offline_sync');
    });

    test('duplicate UUIDs in local queue fail safely before request', () async {
      final queue = _Queue(meters: [_item('m1'), _item('m1')]);
      final transport = _Transport({});

      final result = await _service(queue, transport).syncAll();

      expect(result.meterFailed, 2);
      expect(transport.calls, 0);
      expect(queue.removedMeters, isEmpty);
    });
  });
}

SyncService _service(_Queue queue, _Transport transport) => SyncService(
      queue: queue,
      connectivity: _Connectivity(true),
      transport: transport,
    );

Map<String, dynamic> _item(String key) => {
      'key': key,
      'customer_id': 1,
      'reading_value': 10,
      'period': '2026-07',
      'prospect_id': 1,
      'latitude': 1,
      'longitude': 1,
      'recommendation': 'feasible',
      'photo_house_urls': ['a', 'b'],
    };

Map<String, dynamic> _result(String uuid, String status, {String? code}) => {
      'client_uuid': uuid,
      'status': status,
      if (code != null) 'error_code': code,
    };

Map<String, dynamic> _response(List<dynamic> results) => {
      'data': {'results': results},
    };

class _Queue implements SyncQueue {
  _Queue(
      {List<Map<String, dynamic>>? meters, List<Map<String, dynamic>>? surveys})
      : meters = meters ?? [],
        surveys = surveys ?? [];
  final List<Map<String, dynamic>> meters;
  final List<Map<String, dynamic>> surveys;
  final List<String> removedMeters = [];
  final List<String> removedSurveys = [];
  int reads = 0;

  @override
  List<Map<String, dynamic>> pendingMeterReadings() {
    reads++;
    return meters.where((e) => !removedMeters.contains(e['key'])).toList();
  }

  @override
  List<Map<String, dynamic>> pendingSurveys() {
    reads++;
    return surveys.where((e) => !removedSurveys.contains(e['key'])).toList();
  }

  @override
  Future<void> removeMeterReading(String clientUuid) async =>
      removedMeters.add(clientUuid);
  @override
  Future<void> removeSurvey(String clientUuid) async =>
      removedSurveys.add(clientUuid);
  @override
  int get pendingCount => meters.length + surveys.length;
  @override
  Future<void> cacheMeterReading({
    required int customerId,
    required double readingValue,
    required String period,
    String? photoMeterUrl,
    String? photoHouseUrl,
    double? latitude,
    double? longitude,
  }) async {}
}

class _Connectivity implements SyncConnectivity {
  _Connectivity(this.connected);
  final bool connected;
  @override
  Future<bool> hasConnection() async => connected;
  @override
  Stream<bool> get connectionChanges => const Stream.empty();
}

class _Transport implements SyncTransport {
  _Transport(this.responses)
      : sequential = null,
        error = null;
  _Transport.sequential(this.sequential)
      : responses = const {},
        error = null;
  _Transport.throwing()
      : responses = const {},
        sequential = null,
        error = Exception('offline');
  final Map<String, Object?> responses;
  final List<Object?>? sequential;
  final Exception? error;
  final List<List<Map<String, dynamic>>> payloads = [];
  int calls = 0;

  @override
  Future<Object?> upload(
      String type, List<Map<String, dynamic>> requestPayloads) async {
    calls++;
    payloads.add(requestPayloads);
    if (error != null) throw error!;
    if (sequential != null) return sequential![calls - 1];
    return responses[type];
  }
}
