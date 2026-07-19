import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../../../../core/constants.dart';
import '../../../../core/errors/exceptions.dart';
import '../datasources/auth_remote_source.dart';
import '../models/login_response.dart';

abstract class AuthRepository {
  Future<LoginData> login({
    required String pdamCode,
    required String email,
    required String password,
  });

  Future<void> logout();
  Future<String?> getToken();
  Future<String?> getRefreshToken();
  Future<String?> getUserRole();
  Future<UserData?> getUserData();
  Future<bool> isLoggedIn();
  Future<void> saveSession(LoginData data);
  Future<void> clearSession();
}

class AuthRepositoryImpl implements AuthRepository {
  final AuthRemoteSource _remoteSource;
  final FlutterSecureStorage _secureStorage;

  AuthRepositoryImpl({
    required AuthRemoteSource remoteSource,
    required FlutterSecureStorage secureStorage,
  })  : _remoteSource = remoteSource,
        _secureStorage = secureStorage;

  @override
  Future<LoginData> login({
    required String pdamCode,
    required String email,
    required String password,
  }) async {
    final response = await _remoteSource.login(
      pdamCode: pdamCode,
      email: email,
      password: password,
    );

    final loginResponse = LoginResponse.fromJson(response);

    if (!loginResponse.success || loginResponse.data == null) {
      throw AuthException(message: loginResponse.message);
    }

    await saveSession(loginResponse.data!);

    return loginResponse.data!;
  }

  @override
  Future<void> logout() async {
    try {
      await _remoteSource.logout();
    } finally {
      await clearSession();
    }
  }

  @override
  Future<String?> getToken() async {
    return _secureStorage.read(key: AppConstants.authTokenKey);
  }

  @override
  Future<String?> getRefreshToken() async {
    return _secureStorage.read(key: AppConstants.refreshTokenKey);
  }

  @override
  Future<String?> getUserRole() async {
    return _secureStorage.read(key: AppConstants.userRoleKey);
  }

  @override
  Future<UserData?> getUserData() async {
    final jsonStr = await _secureStorage.read(key: AppConstants.userDataKey);
    if (jsonStr == null) return null;
    try {
      return UserData.fromJson(json.decode(jsonStr) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  @override
  Future<bool> isLoggedIn() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }

  @override
  Future<void> saveSession(LoginData data) async {
    await _secureStorage.write(
      key: AppConstants.authTokenKey,
      value: data.token,
    );
    await _secureStorage.write(
      key: AppConstants.refreshTokenKey,
      value: data.refreshToken,
    );
    await _secureStorage.write(
      key: AppConstants.userRoleKey,
      value: data.roles.isNotEmpty ? data.roles.first : '',
    );
    await _secureStorage.write(
      key: AppConstants.userDataKey,
      value: json.encode(data.user.toJson()),
    );
  }

  @override
  Future<void> clearSession() async {
    await _secureStorage.delete(key: AppConstants.authTokenKey);
    await _secureStorage.delete(key: AppConstants.refreshTokenKey);
    await _secureStorage.delete(key: AppConstants.userRoleKey);
    await _secureStorage.delete(key: AppConstants.userDataKey);
  }
}
