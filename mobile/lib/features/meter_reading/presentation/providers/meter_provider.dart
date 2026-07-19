import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/network/api_client.dart';
import '../../data/datasources/meter_remote_source.dart';
import '../../domain/models/reading_entry.dart';
import '../../domain/models/route_task.dart';

final meterRemoteSourceProvider = Provider<MeterRemoteSource>((ref) {
  return MeterRemoteSource(apiClient: ApiClient());
});

enum MeterDataState { initial, loading, loaded, error }

class MeterRoutesState {
  final MeterDataState dataState;
  final List<RouteTask> routes;
  final String? errorMessage;

  const MeterRoutesState({
    this.dataState = MeterDataState.initial,
    this.routes = const [],
    this.errorMessage,
  });

  MeterRoutesState copyWith({
    MeterDataState? dataState,
    List<RouteTask>? routes,
    String? errorMessage,
  }) {
    return MeterRoutesState(
      dataState: dataState ?? this.dataState,
      routes: routes ?? this.routes,
      errorMessage: errorMessage ?? this.errorMessage,
    );
  }
}

class MeterRoutesNotifier extends StateNotifier<MeterRoutesState> {
  final MeterRemoteSource _remoteSource;

  MeterRoutesNotifier({required MeterRemoteSource remoteSource})
      : _remoteSource = remoteSource,
        super(const MeterRoutesState());

  Future<void> loadRoutes() async {
    state = state.copyWith(dataState: MeterDataState.loading);
    try {
      final data = await _remoteSource.getRoutes();
      final list = (data['data'] as List)
          .map((e) => RouteTask.fromJson(e as Map<String, dynamic>))
          .toList();
      state = state.copyWith(
        dataState: MeterDataState.loaded,
        routes: list,
      );
    } catch (e) {
      state = state.copyWith(
        dataState: MeterDataState.error,
        errorMessage: 'Gagal memuat data rute: ${e.toString()}',
      );
    }
  }
}

class MeterReadingsState {
  final MeterDataState dataState;
  final List<ReadingEntry> readings;
  final RouteTask? currentRoute;
  final String? errorMessage;
  final bool isSubmitting;

  const MeterReadingsState({
    this.dataState = MeterDataState.initial,
    this.readings = const [],
    this.currentRoute,
    this.errorMessage,
    this.isSubmitting = false,
  });

  MeterReadingsState copyWith({
    MeterDataState? dataState,
    List<ReadingEntry>? readings,
    RouteTask? currentRoute,
    String? errorMessage,
    bool? isSubmitting,
  }) {
    return MeterReadingsState(
      dataState: dataState ?? this.dataState,
      readings: readings ?? this.readings,
      currentRoute: currentRoute ?? this.currentRoute,
      errorMessage: errorMessage ?? this.errorMessage,
      isSubmitting: isSubmitting ?? this.isSubmitting,
    );
  }
}

class MeterReadingsNotifier extends StateNotifier<MeterReadingsState> {
  final MeterRemoteSource _remoteSource;

  MeterReadingsNotifier({required MeterRemoteSource remoteSource})
      : _remoteSource = remoteSource,
        super(const MeterReadingsState());

  Future<void> loadRouteTasks(int routeId) async {
    state = state.copyWith(dataState: MeterDataState.loading);
    try {
      final data = await _remoteSource.getRouteTasks(routeId);
      final routeData = data['route'] as Map<String, dynamic>?;
      final list = (data['tasks'] as List)
          .map((e) => ReadingEntry.fromJson(e as Map<String, dynamic>))
          .toList();

      state = state.copyWith(
        dataState: MeterDataState.loaded,
        readings: list,
        currentRoute: routeData != null ? RouteTask.fromJson(routeData) : null,
      );
    } catch (e) {
      state = state.copyWith(
        dataState: MeterDataState.error,
        errorMessage: 'Gagal memuat data tugas: ${e.toString()}',
      );
    }
  }

  Future<bool> submitReading(
    int readingId,
    double currentReading,
    String? photoPath, {
    double? latitude,
    double? longitude,
    String? notes,
  }) async {
    state = state.copyWith(isSubmitting: true);
    try {
      await _remoteSource.submitReading({
        'current_reading': currentReading,
        'photo': photoPath,
        'latitude': latitude,
        'longitude': longitude,
        'notes': notes,
      });

      state = state.copyWith(
        readings: state.readings.map((r) {
          if (r.id == readingId) {
            return ReadingEntry(
              id: r.id,
              taskId: r.taskId,
              customerName: r.customerName,
              customerNumber: r.customerNumber,
              address: r.address,
              previousReading: r.previousReading,
              currentReading: currentReading,
              usage: r.previousReading != null
                  ? currentReading - r.previousReading!
                  : null,
              meterPhoto: photoPath,
              housePhoto: null,
              latitude: latitude,
              longitude: longitude,
              notes: notes,
              status: 'completed',
              readAt: DateTime.now(),
              synced: true,
            );
          }
          return r;
        }).toList(),
        isSubmitting: false,
      );
      return true;
    } catch (e) {
      state = state.copyWith(
        errorMessage: 'Gagal mengirim pembacaan: ${e.toString()}',
        isSubmitting: false,
      );
      return false;
    }
  }

  void updateReading(int index, ReadingEntry entry) {
    final updated = List<ReadingEntry>.from(state.readings);
    updated[index] = entry;
    state = state.copyWith(readings: updated);
  }
}

final meterRoutesProvider =
    StateNotifierProvider<MeterRoutesNotifier, MeterRoutesState>((ref) {
  return MeterRoutesNotifier(
    remoteSource: ref.watch(meterRemoteSourceProvider),
  );
});

final meterReadingsProvider =
    StateNotifierProvider<MeterReadingsNotifier, MeterReadingsState>((ref) {
  return MeterReadingsNotifier(
    remoteSource: ref.watch(meterRemoteSourceProvider),
  );
});
