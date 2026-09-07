// Test drift client Dio generated ↔ docs/openapi.json (H-10).
import 'package:flutter_test/flutter_test.dart';
import 'package:pdam_mobile/api/generated/api_client.g.dart';

void main() {
  test('client punya semua operasi & path yang diharapkan', () {
    expect(allEndpoints.length, generatedOperationCount);
    expect(allEndpoints.length, greaterThan(300));

    bool has(String verb, String path) =>
        allEndpoints.any((e) => e.verb == verb && e.path == path);

    expect(has('POST', '/login'), isTrue);
    expect(has('POST', '/logout'), isTrue);
    expect(has('GET', '/dashboard/director'), isTrue);
    expect(has('POST', '/prospects/{prospect}/order-materials'), isTrue);
    expect(has('GET', '/prospects/{prospect}/material-orders'), isTrue);
    expect(has('POST', '/material-orders/{order}/issue'), isTrue);
    expect(has('GET', '/bi/scheduled-reports'), isTrue);
    expect(has('POST', '/iot/ingest'), isTrue);
  });

  test('path params terekap', () {
    final restore = allEndpoints.where((e) => e.path.endsWith('/restore')).toList();
    expect(restore, isNotEmpty);
    for (final e in restore) {
      expect(e.params.length, 1, reason: '${e.path} harus 1 param path');
    }
  });

  test('summary tidak kosong utk endpoint generik', () {
    expect(allEndpoints.where((e) => e.summary == '').length, 0);
  });

  test('PdamApiClient & manifest dasar', () {
    final paths = allEndpoints.map((e) => e.path).toSet();
    expect(paths, contains('/sync/upload'));
    expect(PdamApiClient, isNotNull);
  });
}
