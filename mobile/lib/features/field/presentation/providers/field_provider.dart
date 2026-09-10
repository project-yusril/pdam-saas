import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/network/api_client.dart';
import '../../data/datasources/location_remote_source.dart';
import '../../data/datasources/work_remote_source.dart';
import '../../domain/models/work_order.dart';

final locationRemoteSourceProvider = Provider<LocationRemoteSource>((ref) {
  return LocationRemoteSource(apiClient: ApiClient());
});

enum LocationReportState { idle, sending, sent, error }

class LocationState {
  const LocationState({
    this.status = LocationReportState.idle,
    this.latitude,
    this.longitude,
    this.message,
    this.lastReportedAt,
  });

  final LocationReportState status;
  final double? latitude;
  final double? longitude;
  final String? message;
  final DateTime? lastReportedAt;

  LocationState copyWith({
    LocationReportState? status,
    double? latitude,
    double? longitude,
    String? message,
    DateTime? lastReportedAt,
  }) {
    return LocationState(
      status: status ?? this.status,
      latitude: latitude ?? this.latitude,
      longitude: longitude ?? this.longitude,
      message: message,
      lastReportedAt: lastReportedAt ?? this.lastReportedAt,
    );
  }
}

/// Tombol "Lapor Lokasi" — kirim GPS sekali jalan, state utk UI (spinner/hasil).
class LocationNotifier extends Notifier<LocationState> {
  @override
  LocationState build() => const LocationState();

  Future<bool> report() async {
    if (state.status == LocationReportState.sending) return false;
    state = state.copyWith(status: LocationReportState.sending);
    try {
      final res =
          await ref.read(locationRemoteSourceProvider).captureAndReport();
      state = state.copyWith(
        status: LocationReportState.sent,
        latitude: res.latitude,
        longitude: res.longitude,
        lastReportedAt: DateTime.now(),
      );
      return true;
    } catch (e) {
      state = state.copyWith(
        status: LocationReportState.error,
        message: 'Gagal lapor lokasi: $e',
      );
      return false;
    }
  }
}

final locationProvider =
    NotifierProvider<LocationNotifier, LocationState>(LocationNotifier.new);

final workRemoteSourceProvider = Provider<WorkRemoteSource>((ref) {
  return WorkRemoteSource(apiClient: ApiClient());
});

enum WorkDataState { initial, loading, loaded, error }

class WorkOrdersState {
  const WorkOrdersState({
    this.dataState = WorkDataState.initial,
    this.items = const [],
    this.statusFilter = '',
    this.errorMessage,
    this.busyId,
  });

  final WorkDataState dataState;
  final List<WorkOrderModel> items;
  final String statusFilter;
  final String? errorMessage;
  final int? busyId;

  WorkOrdersState copyWith({
    WorkDataState? dataState,
    List<WorkOrderModel>? items,
    String? statusFilter,
    String? errorMessage,
    int? busyId,
  }) {
    return WorkOrdersState(
      dataState: dataState ?? this.dataState,
      items: items ?? this.items,
      statusFilter: statusFilter ?? this.statusFilter,
      errorMessage: errorMessage,
      busyId: busyId,
    );
  }
}

class WorkOrdersNotifier extends Notifier<WorkOrdersState> {
  @override
  WorkOrdersState build() => const WorkOrdersState();

  Future<void> load({String? status}) async {
    final filter = status ?? state.statusFilter;
    state =
        state.copyWith(dataState: WorkDataState.loading, statusFilter: filter);
    try {
      final items = await ref
          .read(workRemoteSourceProvider)
          .listMine(status: filter.isEmpty ? null : filter);
      state = state.copyWith(
        dataState: WorkDataState.loaded,
        items: items,
        statusFilter: filter,
      );
    } catch (e) {
      state = state.copyWith(
        dataState: WorkDataState.error,
        errorMessage: 'Gagal memuat WO: $e',
      );
    }
  }

  Future<void> start(int id) => _mutate(id, (rs) => rs.start(id));

  Future<void> complete(int id, String resolution) =>
      _mutate(id, (rs) => rs.complete(id, resolution: resolution));

  bool _muted = false;

  Future<void> _mutate(int id, Function(WorkRemoteSource) action) async {
    if (_muted) return;
    _muted = true;
    state = state.copyWith(busyId: id);
    try {
      await action(ref.read(workRemoteSourceProvider));
      await load();
    } catch (e) {
      state = state.copyWith(
        dataState: WorkDataState.error,
        errorMessage: 'Aksi gagal: $e',
      );
    } finally {
      _muted = false;
      state = state.copyWith(busyId: null);
    }
  }
}

final workOrdersProvider =
    NotifierProvider<WorkOrdersNotifier, WorkOrdersState>(
        WorkOrdersNotifier.new);
