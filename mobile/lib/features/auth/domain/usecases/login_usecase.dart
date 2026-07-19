import '../../../../core/errors/exceptions.dart';
import '../../../../core/errors/failure.dart';
import '../../data/repositories/auth_repository_impl.dart';

class LoginUseCase {
  final AuthRepositoryImpl _repository;

  LoginUseCase({required AuthRepositoryImpl repository})
      : _repository = repository;

  Future<LoginResult> execute({
    required String pdamCode,
    required String email,
    required String password,
  }) async {
    try {
      final loginData = await _repository.login(
        pdamCode: pdamCode,
        email: email,
        password: password,
      );

      return LoginResult.success(data: loginData);
    } on AuthException catch (e) {
      return LoginResult.failure(failure: AuthFailure(message: e.message));
    } on ServerException catch (e) {
      return LoginResult.failure(
        failure: ServerFailure(message: e.message, code: e.code),
      );
    } on NetworkException catch (e) {
      return LoginResult.failure(failure: NetworkFailure(message: e.message));
    } catch (e) {
      return LoginResult.failure(
        failure: ServerFailure(message: 'Terjadi kesalahan: ${e.toString()}'),
      );
    }
  }
}

class LoginResult {
  final bool isSuccess;
  final Failure? failure;
  final dynamic data;

  const LoginResult._({required this.isSuccess, this.failure, this.data});

  factory LoginResult.success({required dynamic data}) {
    return LoginResult._(isSuccess: true, data: data);
  }

  factory LoginResult.failure({required Failure failure}) {
    return LoginResult._(isSuccess: false, failure: failure);
  }
}
