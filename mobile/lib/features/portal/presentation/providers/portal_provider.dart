import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/network/api_client.dart';
import '../../data/datasources/portal_remote_source.dart';
import '../../domain/models/bill.dart';
import '../../domain/models/customer_dashboard.dart';

final portalRemoteSourceProvider = Provider<PortalRemoteSource>((ref) {
  return PortalRemoteSource(apiClient: ApiClient());
});

enum PortalDataState { initial, loading, loaded, error }

class DashboardState {
  final PortalDataState dataState;
  final CustomerDashboard? dashboard;
  final String? errorMessage;

  const DashboardState({
    this.dataState = PortalDataState.initial,
    this.dashboard,
    this.errorMessage,
  });

  DashboardState copyWith({
    PortalDataState? dataState,
    CustomerDashboard? dashboard,
    String? errorMessage,
  }) {
    return DashboardState(
      dataState: dataState ?? this.dataState,
      dashboard: dashboard ?? this.dashboard,
      errorMessage: errorMessage ?? this.errorMessage,
    );
  }
}

class DashboardNotifier extends StateNotifier<DashboardState> {
  final PortalRemoteSource _remoteSource;

  DashboardNotifier({required PortalRemoteSource remoteSource})
      : _remoteSource = remoteSource,
        super(const DashboardState());

  Future<void> loadDashboard() async {
    state = state.copyWith(dataState: PortalDataState.loading);
    try {
      final data = await _remoteSource.getDashboard();
      final dashboard =
          CustomerDashboard.fromJson(data['data'] as Map<String, dynamic>);
      state = state.copyWith(
        dataState: PortalDataState.loaded,
        dashboard: dashboard,
      );
    } catch (e) {
      state = state.copyWith(
        dataState: PortalDataState.error,
        errorMessage: 'Gagal memuat dashboard: ${e.toString()}',
      );
    }
  }
}

class BillsState {
  final PortalDataState dataState;
  final List<Bill> bills;
  final int currentPage;
  final int totalPages;
  final String? errorMessage;
  final bool isLoadingMore;

  const BillsState({
    this.dataState = PortalDataState.initial,
    this.bills = const [],
    this.currentPage = 1,
    this.totalPages = 1,
    this.errorMessage,
    this.isLoadingMore = false,
  });

  BillsState copyWith({
    PortalDataState? dataState,
    List<Bill>? bills,
    int? currentPage,
    int? totalPages,
    String? errorMessage,
    bool? isLoadingMore,
  }) {
    return BillsState(
      dataState: dataState ?? this.dataState,
      bills: bills ?? this.bills,
      currentPage: currentPage ?? this.currentPage,
      totalPages: totalPages ?? this.totalPages,
      errorMessage: errorMessage ?? this.errorMessage,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
    );
  }
}

class BillsNotifier extends StateNotifier<BillsState> {
  final PortalRemoteSource _remoteSource;

  BillsNotifier({required PortalRemoteSource remoteSource})
      : _remoteSource = remoteSource,
        super(const BillsState());

  Future<void> loadBills({bool refresh = false}) async {
    if (refresh) {
      state = const BillsState(dataState: PortalDataState.loading);
    } else if (state.bills.isEmpty) {
      state = state.copyWith(dataState: PortalDataState.loading);
    }

    try {
      final data = await _remoteSource.getBills(page: 1);
      final list = (data['data'] as List)
          .map((e) => Bill.fromJson(e as Map<String, dynamic>))
          .toList();
      final totalPages = data['last_page'] as int? ?? 1;

      state = state.copyWith(
        dataState: PortalDataState.loaded,
        bills: list,
        currentPage: 1,
        totalPages: totalPages,
      );
    } catch (e) {
      state = state.copyWith(
        dataState: PortalDataState.error,
        errorMessage: 'Gagal memuat tagihan: ${e.toString()}',
      );
    }
  }

  Future<void> loadMore() async {
    if (state.isLoadingMore || state.currentPage >= state.totalPages) return;

    state = state.copyWith(isLoadingMore: true);
    try {
      final data = await _remoteSource.getBills(page: state.currentPage + 1);
      final list = (data['data'] as List)
          .map((e) => Bill.fromJson(e as Map<String, dynamic>))
          .toList();
      final totalPages = data['last_page'] as int? ?? state.totalPages;

      state = state.copyWith(
        bills: [...state.bills, ...list],
        currentPage: state.currentPage + 1,
        totalPages: totalPages,
        isLoadingMore: false,
      );
    } catch (e) {
      state = state.copyWith(isLoadingMore: false);
    }
  }

  Future<Bill?> getBillDetail(int billId) async {
    try {
      final data = await _remoteSource.getBillDetail(billId);
      return Bill.fromJson(data['data'] as Map<String, dynamic>);
    } catch (e) {
      return null;
    }
  }

  Future<bool> payBill(int billId, {String? paymentMethod}) async {
    try {
      await _remoteSource.payBill(billId, paymentMethod: paymentMethod);
      await loadBills(refresh: true);
      return true;
    } catch (e) {
      return false;
    }
  }
}

class UsageState {
  final PortalDataState dataState;
  final List<UsageDataPoint> usageData;
  final String? errorMessage;

  const UsageState({
    this.dataState = PortalDataState.initial,
    this.usageData = const [],
    this.errorMessage,
  });

  UsageState copyWith({
    PortalDataState? dataState,
    List<UsageDataPoint>? usageData,
    String? errorMessage,
  }) {
    return UsageState(
      dataState: dataState ?? this.dataState,
      usageData: usageData ?? this.usageData,
      errorMessage: errorMessage ?? this.errorMessage,
    );
  }
}

class UsageDataPoint {
  final String period;
  final double usage;
  final double amount;

  const UsageDataPoint({
    required this.period,
    required this.usage,
    required this.amount,
  });

  factory UsageDataPoint.fromJson(Map<String, dynamic> json) {
    return UsageDataPoint(
      period: json['period'] as String,
      usage: (json['usage'] as num).toDouble(),
      amount: (json['amount'] as num).toDouble(),
    );
  }
}

class UsageNotifier extends StateNotifier<UsageState> {
  final PortalRemoteSource _remoteSource;

  UsageNotifier({required PortalRemoteSource remoteSource})
      : _remoteSource = remoteSource,
        super(const UsageState());

  Future<void> loadUsageHistory({int months = 12}) async {
    state = state.copyWith(dataState: PortalDataState.loading);
    try {
      final data = await _remoteSource.getUsageHistory(months: months);
      final list = (data['data'] as List)
          .map((e) => UsageDataPoint.fromJson(e as Map<String, dynamic>))
          .toList();
      state = state.copyWith(
        dataState: PortalDataState.loaded,
        usageData: list,
      );
    } catch (e) {
      state = state.copyWith(
        dataState: PortalDataState.error,
        errorMessage: 'Gagal memuat riwayat pemakaian: ${e.toString()}',
      );
    }
  }
}

class NotificationsState {
  final PortalDataState dataState;
  final List<AppNotification> notifications;
  final String? errorMessage;

  const NotificationsState({
    this.dataState = PortalDataState.initial,
    this.notifications = const [],
    this.errorMessage,
  });

  NotificationsState copyWith({
    PortalDataState? dataState,
    List<AppNotification>? notifications,
    String? errorMessage,
  }) {
    return NotificationsState(
      dataState: dataState ?? this.dataState,
      notifications: notifications ?? this.notifications,
      errorMessage: errorMessage ?? this.errorMessage,
    );
  }
}

class AppNotification {
  final int id;
  final String title;
  final String message;
  final String type;
  final bool isRead;
  final DateTime createdAt;

  const AppNotification({
    required this.id,
    required this.title,
    required this.message,
    required this.type,
    required this.isRead,
    required this.createdAt,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: json['id'] as int,
      title: json['title'] as String,
      message: json['message'] as String,
      type: json['type'] as String,
      isRead: json['is_read'] as bool? ?? false,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }

  AppNotification copyWith({bool? isRead}) {
    return AppNotification(
      id: id,
      title: title,
      message: message,
      type: type,
      isRead: isRead ?? this.isRead,
      createdAt: createdAt,
    );
  }
}

class NotificationsNotifier extends StateNotifier<NotificationsState> {
  final PortalRemoteSource _remoteSource;

  NotificationsNotifier({required PortalRemoteSource remoteSource})
      : _remoteSource = remoteSource,
        super(const NotificationsState());

  Future<void> loadNotifications() async {
    state = state.copyWith(dataState: PortalDataState.loading);
    try {
      final data = await _remoteSource.getNotifications();
      final list = (data['data'] as List)
          .map((e) => AppNotification.fromJson(e as Map<String, dynamic>))
          .toList();
      state = state.copyWith(
        dataState: PortalDataState.loaded,
        notifications: list,
      );
    } catch (e) {
      state = state.copyWith(
        dataState: PortalDataState.error,
        errorMessage: 'Gagal memuat notifikasi: ${e.toString()}',
      );
    }
  }

  Future<void> markAsRead(int notificationId) async {
    try {
      await _remoteSource.markNotificationRead(notificationId);
      state = state.copyWith(
        notifications: state.notifications.map((n) {
          if (n.id == notificationId) {
            return n.copyWith(isRead: true);
          }
          return n;
        }).toList(),
      );
    } catch (_) {
      // Abaikan error; status baca akan tersinkron ulang saat refresh berikutnya.
    }
  }

  Future<void> markAllAsRead() async {
    try {
      await _remoteSource.markAllNotificationsRead();
      state = state.copyWith(
        notifications:
            state.notifications.map((n) => n.copyWith(isRead: true)).toList(),
      );
    } catch (_) {
      // Abaikan error; status baca akan tersinkron ulang saat refresh berikutnya.
    }
  }
}

class ProfileState {
  final PortalDataState dataState;
  final Map<String, dynamic>? profile;
  final String? errorMessage;

  const ProfileState({
    this.dataState = PortalDataState.initial,
    this.profile,
    this.errorMessage,
  });

  ProfileState copyWith({
    PortalDataState? dataState,
    Map<String, dynamic>? profile,
    String? errorMessage,
  }) {
    return ProfileState(
      dataState: dataState ?? this.dataState,
      profile: profile ?? this.profile,
      errorMessage: errorMessage ?? this.errorMessage,
    );
  }
}

class ProfileNotifier extends StateNotifier<ProfileState> {
  final PortalRemoteSource _remoteSource;

  ProfileNotifier({required PortalRemoteSource remoteSource})
      : _remoteSource = remoteSource,
        super(const ProfileState());

  Future<void> loadProfile() async {
    state = state.copyWith(dataState: PortalDataState.loading);
    try {
      final data = await _remoteSource.getProfile();
      state = state.copyWith(
        dataState: PortalDataState.loaded,
        profile: data['data'] as Map<String, dynamic>,
      );
    } catch (e) {
      state = state.copyWith(
        dataState: PortalDataState.error,
        errorMessage: 'Gagal memuat profil: ${e.toString()}',
      );
    }
  }
}

final dashboardProvider =
    StateNotifierProvider<DashboardNotifier, DashboardState>((ref) {
  return DashboardNotifier(
    remoteSource: ref.watch(portalRemoteSourceProvider),
  );
});

final billsProvider = StateNotifierProvider<BillsNotifier, BillsState>((ref) {
  return BillsNotifier(
    remoteSource: ref.watch(portalRemoteSourceProvider),
  );
});

final usageProvider = StateNotifierProvider<UsageNotifier, UsageState>((ref) {
  return UsageNotifier(
    remoteSource: ref.watch(portalRemoteSourceProvider),
  );
});

final notificationsProvider =
    StateNotifierProvider<NotificationsNotifier, NotificationsState>((ref) {
  return NotificationsNotifier(
    remoteSource: ref.watch(portalRemoteSourceProvider),
  );
});

final profileProvider =
    StateNotifierProvider<ProfileNotifier, ProfileState>((ref) {
  return ProfileNotifier(
    remoteSource: ref.watch(portalRemoteSourceProvider),
  );
});
