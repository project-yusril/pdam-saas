import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../../../../core/network/api_client.dart';

import '../../data/datasources/auth_remote_source.dart';
import '../../data/repositories/auth_repository_impl.dart';
import '../../domain/usecases/login_usecase.dart';

final secureStorageProvider = Provider<FlutterSecureStorage>((ref) {
  return const FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
    iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
  );
});

final authRemoteSourceProvider = Provider<AuthRemoteSource>((ref) {
  return AuthRemoteSource(apiClient: ApiClient());
});

final authRepositoryProvider = Provider<AuthRepositoryImpl>((ref) {
  return AuthRepositoryImpl(
    remoteSource: ref.watch(authRemoteSourceProvider),
    secureStorage: ref.watch(secureStorageProvider),
  );
});

final loginUseCaseProvider = Provider<LoginUseCase>((ref) {
  return LoginUseCase(repository: ref.watch(authRepositoryProvider));
});

enum AuthState { initial, loading, authenticated, unauthenticated, error }

class AuthNotifier extends StateNotifier<AuthState> {
  final AuthRepositoryImpl _repository;
  final LoginUseCase _loginUseCase;
  String? _role;
  String? _errorMessage;

  AuthNotifier({
    required AuthRepositoryImpl repository,
    required LoginUseCase loginUseCase,
  })  : _repository = repository,
        _loginUseCase = loginUseCase,
        super(AuthState.initial);

  String? get role => _role;
  String? get errorMessage => _errorMessage;

  Future<void> checkAuthStatus() async {
    state = AuthState.loading;
    try {
      final isLoggedIn = await _repository.isLoggedIn();
      if (isLoggedIn) {
        _role = await _repository.getUserRole();
        state = AuthState.authenticated;
      } else {
        state = AuthState.unauthenticated;
      }
    } catch (e) {
      state = AuthState.unauthenticated;
    }
  }

  Future<bool> login(String pdamCode, String email, String password) async {
    state = AuthState.loading;
    final result = await _loginUseCase.execute(
      pdamCode: pdamCode,
      email: email,
      password: password,
    );

    if (result.isSuccess) {
      _role = await _repository.getUserRole();
      state = AuthState.authenticated;
      return true;
    } else {
      _errorMessage = result.failure?.message ?? 'Login gagal';
      state = AuthState.error;
      return false;
    }
  }

  Future<void> logout() async {
    await _repository.logout();
    _role = null;
    state = AuthState.unauthenticated;
  }

  void clearError() {
    _errorMessage = null;
    if (state == AuthState.error) {
      state = AuthState.unauthenticated;
    }
  }
}

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  return AuthNotifier(
    repository: ref.watch(authRepositoryProvider),
    loginUseCase: ref.watch(loginUseCaseProvider),
  );
});

final userRoleProvider = Provider<String?>((ref) {
  ref.watch(authProvider);
  return ref.read(authProvider.notifier).role;
});
