import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pdam_mobile/core/network/api_client.dart';
import 'package:pdam_mobile/features/auth/data/datasources/auth_remote_source.dart';
import 'package:pdam_mobile/features/auth/data/models/login_response.dart';

void main() {
  group('auth request contract', () {
    test('login sends tenant code, credentials, and device name', () async {
      final api = _Api({'token': 'new', 'user': <String, dynamic>{}});

      await AuthRemoteSource(apiClient: api).login(
        pdamCode: 'PDAM-01',
        email: 'user@example.test',
        password: 'secret',
      );

      expect(api.path, '/login');
      expect(api.data, {
        'pdam_code': 'PDAM-01',
        'email': 'user@example.test',
        'password': 'secret',
        'device_name': 'pdam-mobile',
      });
      expect(api.token, 'new');
    });

    test('refresh uses bearer context and sends no refresh-token body',
        () async {
      final api = _Api({'token': 'rotated'});

      final token = await AuthRemoteSource(apiClient: api).refreshToken();

      expect(token, 'rotated');
      expect(api.path, '/refresh-token');
      expect(api.data, isNull);
      expect(api.token, 'rotated');
    });

    test('refresh rejects missing token and does not update auth', () async {
      final api = _Api({'message': 'bad'});

      expect(
        () => AuthRemoteSource(apiClient: api).refreshToken(),
        throwsFormatException,
      );
      expect(api.token, isNull);
    });
  });

  group('auth response contract', () {
    test('parses flat login response and nested user roles', () {
      final response = LoginResponse.fromJson({
        'token': 'abc',
        'user': {
          'id': 7,
          'name': 'Petugas',
          'email': 'p@example.test',
          'roles': ['meter_officer'],
        },
        'organization': {'id': 2, 'code': 'P2', 'name': 'PDAM 2'},
      });

      expect(response.success, isTrue);
      expect(response.data!.token, 'abc');
      expect(response.data!.refreshToken, 'abc');
      expect(response.data!.roles, ['meter_officer']);
      expect(response.data!.organization!.code, 'P2');
    });

    test('parses top-level roles fallback', () {
      final response = LoginResponse.fromJson({
        'token': 'abc',
        'user': {'id': 1},
        'roles': ['customer'],
      });
      expect(response.data!.roles, ['customer']);
    });

    test('missing token is an unsuccessful login response', () {
      final response = LoginResponse.fromJson({'message': 'Invalid'});
      expect(response.success, isFalse);
      expect(response.message, 'Invalid');
      expect(response.data, isNull);
    });
  });
}

class _Api implements ApiTransport {
  _Api(this.response);
  final Map<String, dynamic> response;
  String? path;
  dynamic data;
  String? token;

  Response<T> _response<T>(String path) => Response<T>(
        requestOptions: RequestOptions(path: path),
        data: response as T,
      );

  @override
  Future<Response<T>> post<T>(String path,
      {data, queryParameters, cancelToken, options}) async {
    this.path = path;
    this.data = data;
    return _response<T>(path);
  }

  @override
  Future<Response<T>> get<T>(String path,
          {queryParameters, cancelToken, options}) async =>
      _response<T>(path);

  @override
  Future<Response<T>> put<T>(String path,
          {data, queryParameters, cancelToken, options}) async =>
      _response<T>(path);

  @override
  void updateToken(String token) => this.token = token;
}
