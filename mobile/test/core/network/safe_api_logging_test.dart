import 'package:flutter_test/flutter_test.dart';
import 'package:pdam_mobile/core/network/api_client.dart';

void main() {
  test('safe API logger removes host, query values, and resource IDs', () {
    final route = SafeApiLoggingInterceptor.sanitizeRoute(
      Uri.parse(
        'https://api.example.test/api/v1/portal/bills/12345/pay'
        '?nik=3273010101010001&token=secret',
      ),
    );

    expect(route, '/api/v1/portal/bills/:id/pay');
    expect(route, isNot(contains('api.example.test')));
    expect(route, isNot(contains('3273010101010001')));
    expect(route, isNot(contains('secret')));
    expect(route, isNot(contains('12345')));
  });

  test('safe API logger masks UUID and opaque path identifiers', () {
    expect(
      SafeApiLoggingInterceptor.sanitizeRoute(
        Uri.parse('/sync/jobs/550e8400-e29b-41d4-a716-446655440000'),
      ),
      '/sync/jobs/:id',
    );
    expect(
      SafeApiLoggingInterceptor.sanitizeRoute(
        Uri.parse('/files/abcdefghijklmnopqrstuvwxyz012345'),
      ),
      '/files/:id',
    );
  });
}
