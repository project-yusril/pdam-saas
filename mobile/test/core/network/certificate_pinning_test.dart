import 'dart:convert';
import 'dart:typed_data';

import 'package:crypto/crypto.dart';
import 'package:dio/io.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pdam_mobile/core/network/certificate_pinning.dart';

void main() {
  final spki = Uint8List.fromList(
    _sequence([
      ..._sequence([0x06, 0x01, 0x2a]),
      ..._element(0x03, [0x00, 0x01, 0x02, 0x03]),
    ]),
  );
  final certificate = _certificateWithSpki(spki);
  final primaryPin = _pinFor(spki);
  final backupSpki = Uint8List.fromList(_sequence([0x02, 0x01, 0x01]));
  final backupPin = _pinFor(backupSpki);

  group('pin config', () {
    test('parses canonical SHA-256 pins', () {
      expect(
        CertificatePinning.parsePin(primaryPin),
        sha256.convert(spki).bytes,
      );
    });

    test('rejects missing, malformed, and non-SHA-256 pins', () {
      expect(
        () => CertificatePinning.fromConfig(
          primaryPin: primaryPin,
          backupPin: '',
        ),
        throwsFormatException,
      );
      expect(
        () => CertificatePinning.parsePin('sha256/not-base64'),
        throwsFormatException,
      );
      expect(
        () => CertificatePinning.parsePin('sha256/${base64Encode([1, 2])}'),
        throwsFormatException,
      );
      expect(
        () =>
            CertificatePinning.parsePin(primaryPin.replaceFirst('sha256/', '')),
        throwsFormatException,
      );
      expect(
        () => CertificatePinning.fromConfig(
          primaryPin: primaryPin,
          backupPin: primaryPin,
        ),
        throwsFormatException,
      );
    });
  });

  group('SPKI matcher', () {
    test('extracts and matches the primary or backup SPKI pin', () {
      final primary = CertificatePinning.fromConfig(
        primaryPin: primaryPin,
        backupPin: backupPin,
      );
      final backup = CertificatePinning.fromConfig(
        primaryPin: backupPin,
        backupPin: primaryPin,
      );

      expect(
        CertificatePinning.extractSubjectPublicKeyInfo(certificate),
        spki,
      );
      expect(primary.matchesCertificateDer(certificate), isTrue);
      expect(backup.matchesCertificateDer(certificate), isTrue);
    });

    test('rejects an unmatched SPKI and malformed certificate DER', () {
      final pinning = CertificatePinning.fromConfig(
        primaryPin: backupPin,
        backupPin: _pinFor(Uint8List.fromList([1, 2, 3])),
      );

      expect(pinning.matchesCertificateDer(certificate), isFalse);
      expect(
        () => pinning.matchesCertificateDer(Uint8List.fromList([0x30, 0x01])),
        throwsFormatException,
      );
    });
  });

  test('adapter wires leaf validation without overriding platform TLS trust',
      () {
    final adapter = CertificatePinning.fromConfig(
      primaryPin: primaryPin,
      backupPin: backupPin,
    ).createAdapter();

    expect(adapter, isA<IOHttpClientAdapter>());
    expect(adapter.validateCertificate, isNotNull);
    expect(adapter.createHttpClient, isNull);
    expect(
        adapter.validateCertificate!(null, 'api.example.test', 443), isFalse);
  });
}

String _pinFor(Uint8List spki) {
  return 'sha256/${base64Encode(sha256.convert(spki).bytes)}';
}

Uint8List _certificateWithSpki(Uint8List spki) {
  final tbs = _sequence([
    ..._element(0xa0, [0x02, 0x01, 0x02]),
    ..._element(0x02, [0x01]),
    ..._sequence(const <int>[]),
    ..._sequence(const <int>[]),
    ..._sequence(const <int>[]),
    ..._sequence(const <int>[]),
    ...spki,
  ]);
  return Uint8List.fromList(_sequence([
    ...tbs,
    ..._sequence(const <int>[]),
    ..._element(0x03, [0x00]),
  ]));
}

List<int> _sequence(List<int> content) => _element(0x30, content);

List<int> _element(int tag, List<int> content) {
  if (content.length >= 0x80) {
    throw ArgumentError('Test DER helper only supports short lengths.');
  }
  return [tag, content.length, ...content];
}
