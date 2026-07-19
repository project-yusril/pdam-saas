import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:pdam_mobile/core/network/base_url_policy.dart';

void main() {
  test('production accepts HTTPS and removes trailing slash', () {
    expect(
      BaseUrlPolicy.select(
        production: true,
        productionUrl: 'https://api.example.test/api/v1/',
        debugUrl: 'http://10.0.2.2:8000/api/v1',
      ),
      'https://api.example.test/api/v1',
    );
  });

  test('production rejects cleartext URL', () {
    expect(
      () => BaseUrlPolicy.select(
        production: true,
        productionUrl: 'http://api.example.test/api/v1',
        debugUrl: 'http://10.0.2.2:8000/api/v1',
      ),
      throwsStateError,
    );
  });

  test('debug may explicitly use emulator cleartext URL', () {
    expect(
      BaseUrlPolicy.select(
        production: false,
        productionUrl: 'https://api.example.test/api/v1',
        debugUrl: 'http://10.0.2.2:8000/api/v1',
      ),
      startsWith('http://10.0.2.2'),
    );
  });

  test('rejects relative URLs and URL credentials', () {
    expect(
      () => BaseUrlPolicy.select(
        production: true,
        productionUrl: '/api/v1',
        debugUrl: '',
      ),
      throwsArgumentError,
    );
    expect(
      () => BaseUrlPolicy.select(
        production: true,
        productionUrl: 'https://user:pass@example.test/api/v1',
        debugUrl: '',
      ),
      throwsArgumentError,
    );
  });

  test('release Android manifest and config prohibit cleartext', () {
    final manifest =
        File('android/app/src/main/AndroidManifest.xml').readAsStringSync();
    final config =
        File('android/app/src/main/res/xml/network_security_config.xml')
            .readAsStringSync();
    expect(manifest, contains('android:usesCleartextTraffic="false"'));
    expect(manifest, contains('@xml/network_security_config'));
    expect(config, contains('cleartextTrafficPermitted="false"'));
    expect(config, isNot(contains('10.0.2.2')));
  });

  test('debug cleartext exception is scoped to emulator host', () {
    final config =
        File('android/app/src/debug/res/xml/network_security_config.xml')
            .readAsStringSync();
    expect(config,
        contains('<domain includeSubdomains="false">10.0.2.2</domain>'));
    expect(config, contains('<base-config cleartextTrafficPermitted="false"'));
  });
}
