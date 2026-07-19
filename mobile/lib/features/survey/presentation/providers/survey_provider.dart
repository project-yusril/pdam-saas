import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/cache/cache_manager.dart';
import '../../../../core/network/api_client.dart';
import '../../data/datasources/survey_remote_source.dart';
import '../../domain/models/survey_form.dart';
import '../../domain/models/survey_task.dart';

final surveyRemoteSourceProvider = Provider<SurveyRemoteSource>((ref) {
  return SurveyRemoteSource(apiClient: ApiClient());
});

enum SurveyDataState { initial, loading, loaded, error }

class SurveyTasksState {
  final SurveyDataState dataState;
  final List<SurveyTask> tasks;
  final String? errorMessage;

  const SurveyTasksState({
    this.dataState = SurveyDataState.initial,
    this.tasks = const [],
    this.errorMessage,
  });

  SurveyTasksState copyWith({
    SurveyDataState? dataState,
    List<SurveyTask>? tasks,
    String? errorMessage,
  }) {
    return SurveyTasksState(
      dataState: dataState ?? this.dataState,
      tasks: tasks ?? this.tasks,
      errorMessage: errorMessage ?? this.errorMessage,
    );
  }
}

class SurveyTasksNotifier extends StateNotifier<SurveyTasksState> {
  final SurveyRemoteSource _remoteSource;

  SurveyTasksNotifier({required SurveyRemoteSource remoteSource})
      : _remoteSource = remoteSource,
        super(const SurveyTasksState());

  Future<void> loadTasks({String? status}) async {
    state = state.copyWith(dataState: SurveyDataState.loading);
    try {
      final data = await _remoteSource.getTasks(status: status);
      final list = (data['data'] as List)
          .map((e) => SurveyTask.fromJson(e as Map<String, dynamic>))
          .toList();
      state = state.copyWith(dataState: SurveyDataState.loaded, tasks: list);
    } catch (e) {
      state = state.copyWith(
        dataState: SurveyDataState.error,
        errorMessage: 'Gagal memuat tugas survey: ${e.toString()}',
      );
    }
  }
}

class SurveyFormState {
  final SurveyDataState dataState;
  final SurveyTask? task;
  final SurveyFormData? formData;
  final String? errorMessage;
  final bool isSubmitting;
  final bool isDraftSaved;

  const SurveyFormState({
    this.dataState = SurveyDataState.initial,
    this.task,
    this.formData,
    this.errorMessage,
    this.isSubmitting = false,
    this.isDraftSaved = false,
  });

  SurveyFormState copyWith({
    SurveyDataState? dataState,
    SurveyTask? task,
    SurveyFormData? formData,
    String? errorMessage,
    bool? isSubmitting,
    bool? isDraftSaved,
  }) {
    return SurveyFormState(
      dataState: dataState ?? this.dataState,
      task: task ?? this.task,
      formData: formData ?? this.formData,
      errorMessage: errorMessage ?? this.errorMessage,
      isSubmitting: isSubmitting ?? this.isSubmitting,
      isDraftSaved: isDraftSaved ?? this.isDraftSaved,
    );
  }
}

class SurveyFormNotifier extends StateNotifier<SurveyFormState> {
  final SurveyRemoteSource _remoteSource;

  SurveyFormNotifier({required SurveyRemoteSource remoteSource})
      : _remoteSource = remoteSource,
        super(const SurveyFormState());

  Future<void> loadTaskDetail(int taskId) async {
    state = state.copyWith(dataState: SurveyDataState.loading);
    try {
      final data = await _remoteSource.getTaskDetail(taskId);
      final task = SurveyTask.fromJson(data['data'] as Map<String, dynamic>);
      state = state.copyWith(dataState: SurveyDataState.loaded, task: task);
    } catch (e) {
      state = state.copyWith(
        dataState: SurveyDataState.error,
        errorMessage: 'Gagal memuat detail tugas: ${e.toString()}',
      );
    }
  }

  Future<bool> submitSurvey(SurveyFormData formData) async {
    state = state.copyWith(isSubmitting: true);
    try {
      await _remoteSource.submitSurvey(formData.taskId, formData.toJson());
      state = state.copyWith(isSubmitting: false);
      return true;
    } catch (e) {
      state = state.copyWith(
        isSubmitting: false,
        errorMessage: 'Gagal mengirim survey: ${e.toString()}',
      );
      return false;
    }
  }

  /// Draft disimpan LOKAL (offline-first). Backend tidak punya endpoint draft;
  /// data akan ikut tersinkron lewat /sync saat online.
  Future<bool> saveDraft(SurveyFormData formData) async {
    try {
      await CacheManager().cacheSurveyDraft(
        prospectId: formData.taskId,
        data: formData.toJson(),
      );
      state = state.copyWith(isDraftSaved: true);
      return true;
    } catch (e) {
      return false;
    }
  }

  void updateFormData(SurveyFormData data) {
    state = state.copyWith(formData: data, isDraftSaved: false);
  }
}

final surveyTasksProvider =
    StateNotifierProvider<SurveyTasksNotifier, SurveyTasksState>((ref) {
  return SurveyTasksNotifier(
    remoteSource: ref.watch(surveyRemoteSourceProvider),
  );
});

final surveyFormProvider =
    StateNotifierProvider<SurveyFormNotifier, SurveyFormState>((ref) {
  return SurveyFormNotifier(
    remoteSource: ref.watch(surveyRemoteSourceProvider),
  );
});
