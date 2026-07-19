import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../constants.dart';
import '../errors/exceptions.dart';
import '../security/obfuscation_config.dart';
import 'certificate_pinning.dart';
import 'base_url_policy.dart';

abstract interface class ApiTransport {
  Future<Response<T>> get<T>(String path,
      {Map<String, dynamic>? queryParameters,
      CancelToken? cancelToken,
      Options? options});
  Future<Response<T>> post<T>(String path,
      {dynamic data,
      Map<String, dynamic>? queryParameters,
      CancelToken? cancelToken,
      Options? options});
  Future<Response<T>> put<T>(String path,
      {dynamic data,
      Map<String, dynamic>? queryParameters,
      CancelToken? cancelToken,
      Options? options});
  void updateToken(String token);
}

class ApiClient implements ApiTransport {
  static ApiClient? _instance;
  late final Dio dio;
  final DioExceptionHelper _exceptionHelper = DioExceptionHelper();

  ApiClient._({String? token}) {
    dio = Dio(BaseOptions(
      baseUrl: BaseUrlPolicy.select(
        production: !kDebugMode,
        productionUrl: AppConstants.apiBaseUrl,
        debugUrl: AppConstants.apiDevBaseUrl,
      ),
      connectTimeout:
          const Duration(milliseconds: AppConstants.connectTimeoutMs),
      receiveTimeout:
          const Duration(milliseconds: AppConstants.receiveTimeoutMs),
      sendTimeout: const Duration(milliseconds: AppConstants.sendTimeoutMs),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ));

    dio.interceptors.addAll([
      _AuthInterceptor(token: token),
      SafeApiLoggingInterceptor(),
    ]);

    if (!kDebugMode) {
      _configureCertificatePinning();
    }
  }

  factory ApiClient({String? token}) {
    _instance ??= ApiClient._(token: token);
    return _instance!;
  }

  @override
  void updateToken(String token) {
    dio.options.headers['Authorization'] = 'Bearer $token';
  }

  void removeToken() {
    dio.options.headers.remove('Authorization');
  }

  void _configureCertificatePinning() {
    const primaryPin = String.fromEnvironment('CERT_SPKI_SHA256_PRIMARY');
    const backupPin = String.fromEnvironment('CERT_SPKI_SHA256_BACKUP');
    try {
      dio.httpClientAdapter = CertificatePinning.fromConfig(
        primaryPin: primaryPin,
        backupPin: backupPin,
      ).createAdapter();
    } on FormatException catch (error) {
      throw StateError('Konfigurasi certificate pinning tidak valid: $error');
    }
  }

  static void resetInstance() {
    _instance = null;
  }

  @override
  Future<Response<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
    CancelToken? cancelToken,
    Options? options,
  }) async {
    try {
      final response = await dio.get<T>(
        path,
        queryParameters: queryParameters,
        cancelToken: cancelToken,
        options: options,
      );
      return response;
    } on DioException catch (e) {
      throw _exceptionHelper.handleDioException(e);
    }
  }

  @override
  Future<Response<T>> post<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    CancelToken? cancelToken,
    Options? options,
  }) async {
    try {
      final response = await dio.post<T>(
        path,
        data: data,
        queryParameters: queryParameters,
        cancelToken: cancelToken,
        options: options,
      );
      return response;
    } on DioException catch (e) {
      throw _exceptionHelper.handleDioException(e);
    }
  }

  @override
  Future<Response<T>> put<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    CancelToken? cancelToken,
    Options? options,
  }) async {
    try {
      final response = await dio.put<T>(
        path,
        data: data,
        queryParameters: queryParameters,
        cancelToken: cancelToken,
        options: options,
      );
      return response;
    } on DioException catch (e) {
      throw _exceptionHelper.handleDioException(e);
    }
  }

  Future<Response<T>> delete<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    CancelToken? cancelToken,
    Options? options,
  }) async {
    try {
      final response = await dio.delete<T>(
        path,
        data: data,
        queryParameters: queryParameters,
        cancelToken: cancelToken,
        options: options,
      );
      return response;
    } on DioException catch (e) {
      throw _exceptionHelper.handleDioException(e);
    }
  }

  Future<Response<T>> uploadFile<T>(
    String path, {
    required FormData formData,
    CancelToken? cancelToken,
    Options? options,
    void Function(int, int)? onSendProgress,
  }) async {
    try {
      final response = await dio.post<T>(
        path,
        data: formData,
        cancelToken: cancelToken,
        options: options ?? Options(contentType: 'multipart/form-data'),
        onSendProgress: onSendProgress,
      );
      return response;
    } on DioException catch (e) {
      throw _exceptionHelper.handleDioException(e);
    }
  }
}

class _AuthInterceptor extends Interceptor {
  final String? token;

  _AuthInterceptor({this.token});

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    if (token != null && token!.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    if (err.response?.statusCode == 401) {
      ObfuscationConfig.clearOnSecurityBreach();
    }
    handler.next(err);
  }
}

class SafeApiLoggingInterceptor extends Interceptor {
  SafeApiLoggingInterceptor({
    bool? enabled,
    void Function(String message)? sink,
  })  : enabled = enabled ?? kDebugMode,
        sink = sink ?? ((message) => debugPrint(message));

  final bool enabled;
  final void Function(String message) sink;

  static String sanitizeRoute(Uri uri) {
    final segments = uri.pathSegments.map((segment) {
      final decoded = Uri.decodeComponent(segment);
      if (RegExp(r'^\d+$').hasMatch(decoded) ||
          RegExp(
            r'^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
            caseSensitive: false,
          ).hasMatch(decoded) ||
          RegExp(r'^[A-Za-z0-9_-]{24,}$').hasMatch(decoded)) {
        return ':id';
      }
      return decoded.replaceAll(RegExp(r'[\r\n]'), '');
    });
    return '/${segments.join('/')}';
  }

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    if (enabled) {
      sink(
          '[API] request method=${options.method} route=${sanitizeRoute(options.uri)}');
    }
    handler.next(options);
  }

  @override
  void onResponse(
      Response<dynamic> response, ResponseInterceptorHandler handler) {
    if (enabled) {
      sink(
        '[API] response method=${response.requestOptions.method} '
        'route=${sanitizeRoute(response.requestOptions.uri)} '
        'status=${response.statusCode}',
      );
    }
    handler.next(response);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    if (enabled) {
      sink(
        '[API] error method=${err.requestOptions.method} '
        'route=${sanitizeRoute(err.requestOptions.uri)} '
        'type=${err.type.name} status=${err.response?.statusCode}',
      );
    }
    handler.next(err);
  }
}

class DioExceptionHelper {
  Exception handleDioException(DioException e) {
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return const TimeoutException(
            message: 'Koneksi timeout. Silakan coba lagi.');
      case DioExceptionType.connectionError:
        return const NetworkException(message: 'Tidak ada koneksi internet.');
      case DioExceptionType.badResponse:
        return handleResponseError(e.response);
      case DioExceptionType.cancel:
        return const ServerException(message: 'Permintaan dibatalkan.');
      default:
        return ServerException(message: 'Terjadi kesalahan: ${e.message}');
    }
  }

  Exception handleResponseError(Response<dynamic>? response) {
    if (response == null) {
      return const ServerException(message: 'Tidak ada respons dari server.');
    }

    final statusCode = response.statusCode;
    final data = response.data as Map<String, dynamic>?;
    final message = data?['message'] as String? ?? 'Terjadi kesalahan server.';

    switch (statusCode) {
      case 400:
        return ValidationException(
          message: message,
          errors: data?['errors'] != null
              ? (data!['errors'] as Map<String, dynamic>).map(
                  (k, v) => MapEntry(k, List<String>.from(v as List)),
                )
              : null,
        );
      case 401:
        return const AuthException(
            message: 'Sesi berakhir. Silakan login kembali.');
      case 403:
        return const AuthException(message: 'Anda tidak memiliki akses.');
      case 404:
        return const NotFoundException(message: 'Data tidak ditemukan.');
      case 422:
        return ValidationException(
          message: message,
          errors: data?['errors'] != null
              ? (data!['errors'] as Map<String, dynamic>).map(
                  (k, v) => MapEntry(k, List<String>.from(v as List)),
                )
              : null,
        );
      case 429:
        return const ServerException(
            message: 'Terlalu banyak permintaan. Coba lagi nanti.');
      case 500:
      case 502:
      case 503:
        return const ServerException(
            message: 'Server sedang sibuk. Silakan coba lagi.');
      default:
        return ServerException(message: message);
    }
  }
}
