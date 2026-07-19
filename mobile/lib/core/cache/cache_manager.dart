import 'dart:convert';
import 'dart:math';
import 'package:hive_flutter/hive_flutter.dart';
import '../constants.dart';
import '../errors/exceptions.dart';

class CacheManager {
  static CacheManager? _instance;
  late final Box<dynamic> _meterReadingsBox;
  late final Box<dynamic> _surveyDraftsBox;
  late final Box<dynamic> _customerBillsBox;
  late final Box<dynamic> _notificationsBox;

  CacheManager._();

  factory CacheManager() {
    _instance ??= CacheManager._();
    return _instance!;
  }

  Future<void> initialize() async {
    await Hive.initFlutter();

    _meterReadingsBox = await Hive.openBox(AppConstants.cacheBoxMeterReadings);
    _surveyDraftsBox = await Hive.openBox(AppConstants.cacheBoxSurveyDrafts);
    _customerBillsBox = await Hive.openBox(AppConstants.cacheBoxCustomerBills);
    _notificationsBox = await Hive.openBox(AppConstants.cacheBoxNotifications);
  }

  Box<dynamic> get meterReadingsBox => _meterReadingsBox;
  Box<dynamic> get surveyDraftsBox => _surveyDraftsBox;
  Box<dynamic> get customerBillsBox => _customerBillsBox;
  Box<dynamic> get notificationsBox => _notificationsBox;

  Future<void> cacheMeterReading({
    required int customerId,
    required double readingValue,
    required String period,
    String? photoMeterUrl,
    String? photoHouseUrl,
    double? latitude,
    double? longitude,
    String? notes,
  }) async {
    try {
      final key = _uuid();
      await _meterReadingsBox.put(
          key,
          json.encode({
            'key': key,
            'customer_id': customerId,
            'reading_value': readingValue,
            'period': period,
            'reading_date': DateTime.now().toIso8601String(),
            'photo_meter_url': photoMeterUrl,
            'photo_house_url': photoHouseUrl,
            'latitude': latitude,
            'longitude': longitude,
            'notes': notes,
            'cached_at': DateTime.now().toIso8601String(),
            'synced': false,
          }));
    } catch (e) {
      throw const CacheException(
          message: 'Gagal menyimpan data pembacaan meter.');
    }
  }

  List<Map<String, dynamic>> getPendingMeterReadings() {
    try {
      return _meterReadingsBox.values
          .map<Map<String, dynamic>>(
              (v) => json.decode(v as String) as Map<String, dynamic>)
          .where((r) => r['synced'] != true)
          .toList();
    } catch (e) {
      throw const CacheException(
          message: 'Gagal membaca data pembacaan meter.');
    }
  }

  Future<void> markMeterReadingSynced(String key) async {
    try {
      final raw = _meterReadingsBox.get(key);
      if (raw != null) {
        final data = json.decode(raw as String) as Map<String, dynamic>;
        data['synced'] = true;
        await _meterReadingsBox.put(key, json.encode(data));
      }
    } catch (e) {
      throw const CacheException(message: 'Gagal memperbarui status sync.');
    }
  }

  Future<void> removeMeterReading(String key) async {
    await _meterReadingsBox.delete(key);
  }

  Future<void> clearMeterReadings() async {
    await _meterReadingsBox.clear();
  }

  Future<void> cacheSurveyDraft({
    required int prospectId,
    required Map<String, dynamic> data,
  }) async {
    try {
      final key = _uuid();
      data['key'] = key;
      data['prospect_id'] = prospectId;
      data['cached_at'] = DateTime.now().toIso8601String();
      data['synced'] = false;
      await _surveyDraftsBox.put(key, json.encode(data));
    } catch (e) {
      throw const CacheException(message: 'Gagal menyimpan draft survey.');
    }
  }

  List<Map<String, dynamic>> getPendingSurveyDrafts() {
    try {
      return _surveyDraftsBox.values
          .map<Map<String, dynamic>>(
              (v) => json.decode(v as String) as Map<String, dynamic>)
          .where((d) => d['synced'] != true)
          .toList();
    } catch (e) {
      throw const CacheException(message: 'Gagal membaca draft survey.');
    }
  }

  Future<void> markSurveyDraftSynced(String key) async {
    try {
      final raw = _surveyDraftsBox.get(key);
      if (raw != null) {
        final data = json.decode(raw as String) as Map<String, dynamic>;
        data['synced'] = true;
        await _surveyDraftsBox.put(key, json.encode(data));
      }
    } catch (e) {
      throw const CacheException(
          message: 'Gagal memperbarui status sync draft.');
    }
  }

  Future<void> removeSurveyDraft(String key) async {
    await _surveyDraftsBox.delete(key);
  }

  Future<void> clearSurveyDrafts() async {
    await _surveyDraftsBox.clear();
  }

  Future<void> cacheCustomerBills(List<Map<String, dynamic>> bills) async {
    try {
      await _customerBillsBox.put(
          'bills',
          json.encode(
              {'data': bills, 'cached_at': DateTime.now().toIso8601String()}));
    } catch (e) {
      throw const CacheException(message: 'Gagal menyimpan data tagihan.');
    }
  }

  Map<String, dynamic>? getCachedCustomerBills() {
    try {
      final raw = _customerBillsBox.get('bills');
      if (raw != null) {
        return json.decode(raw as String) as Map<String, dynamic>;
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  Future<void> cacheNotifications(
      List<Map<String, dynamic>> notifications) async {
    try {
      await _notificationsBox.put('notifications', json.encode(notifications));
    } catch (e) {
      throw const CacheException(message: 'Gagal menyimpan notifikasi.');
    }
  }

  List<Map<String, dynamic>> getCachedNotifications() {
    try {
      final raw = _notificationsBox.get('notifications');
      if (raw != null) {
        final list = json.decode(raw as String) as List<dynamic>;
        return list.cast<Map<String, dynamic>>();
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  int get pendingCount {
    return getPendingMeterReadings().length + getPendingSurveyDrafts().length;
  }

  Future<void> clearAllCache() async {
    await _meterReadingsBox.clear();
    await _surveyDraftsBox.clear();
    await _customerBillsBox.clear();
    await _notificationsBox.clear();
  }

  String _uuid() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    final hex = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-'
        '${hex.substring(12, 16)}-${hex.substring(16, 20)}-'
        '${hex.substring(20)}';
  }
}
