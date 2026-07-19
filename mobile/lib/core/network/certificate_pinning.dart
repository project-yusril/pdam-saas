import 'dart:convert';
import 'dart:typed_data';

import 'package:crypto/crypto.dart';
import 'package:dio/io.dart';

class CertificatePinning {
  CertificatePinning._(this._pins);

  final List<Uint8List> _pins;

  factory CertificatePinning.fromConfig({
    required String primaryPin,
    required String backupPin,
  }) {
    if (primaryPin.isEmpty || backupPin.isEmpty) {
      throw const FormatException(
        'CERT_SPKI_SHA256_PRIMARY dan CERT_SPKI_SHA256_BACKUP wajib diisi.',
      );
    }

    final primary = parsePin(primaryPin);
    final backup = parsePin(backupPin);
    if (primaryPin == backupPin) {
      throw const FormatException('Pin primary dan backup harus berbeda.');
    }

    return CertificatePinning._([primary, backup]);
  }

  static Uint8List parsePin(String value) {
    const prefix = 'sha256/';
    if (!value.startsWith(prefix)) {
      throw const FormatException('Pin SPKI harus berformat sha256/<base64>.');
    }

    final encodedDigest = value.substring(prefix.length);
    try {
      final digest = base64Decode(encodedDigest);
      if (digest.length != sha256.convert(const <int>[]).bytes.length ||
          base64Encode(digest) != encodedDigest) {
        throw const FormatException('Digest pin SPKI tidak valid.');
      }
      return digest;
    } on FormatException {
      throw const FormatException('Digest pin SPKI tidak valid.');
    }
  }

  bool matchesCertificateDer(Uint8List certificateDer) {
    final spki = extractSubjectPublicKeyInfo(certificateDer);
    return matchesSpki(spki);
  }

  bool matchesSpki(Uint8List spkiDer) {
    final actual = sha256.convert(spkiDer).bytes;
    var matched = false;
    for (final pin in _pins) {
      var difference = 0;
      for (var index = 0; index < actual.length; index++) {
        difference |= actual[index] ^ pin[index];
      }
      matched |= difference == 0;
    }
    return matched;
  }

  IOHttpClientAdapter createAdapter() {
    return IOHttpClientAdapter(
      validateCertificate: (certificate, host, port) {
        if (certificate == null) {
          return false;
        }
        try {
          return matchesCertificateDer(certificate.der);
        } on FormatException {
          return false;
        }
      },
    );
  }

  static Uint8List extractSubjectPublicKeyInfo(Uint8List certificateDer) {
    final certificate = _readElement(certificateDer, 0, expectedTag: 0x30);
    final tbsCertificate = _readElement(
      certificateDer,
      certificate.contentOffset,
      expectedTag: 0x30,
      limit: certificate.endOffset,
    );

    var offset = tbsCertificate.contentOffset;
    var element = _readElement(
      certificateDer,
      offset,
      limit: tbsCertificate.endOffset,
    );
    if (element.tag == 0xa0) {
      offset = element.endOffset;
    }

    // Skip serial number, signature, issuer, validity, and subject.
    for (var index = 0; index < 5; index++) {
      element = _readElement(
        certificateDer,
        offset,
        limit: tbsCertificate.endOffset,
      );
      offset = element.endOffset;
    }

    final spki = _readElement(
      certificateDer,
      offset,
      expectedTag: 0x30,
      limit: tbsCertificate.endOffset,
    );
    return Uint8List.sublistView(
      certificateDer,
      spki.offset,
      spki.endOffset,
    );
  }

  static _DerElement _readElement(
    Uint8List bytes,
    int offset, {
    int? expectedTag,
    int? limit,
  }) {
    final endLimit = limit ?? bytes.length;
    if (offset < 0 || offset + 2 > endLimit || endLimit > bytes.length) {
      throw const FormatException('Struktur sertifikat DER tidak valid.');
    }

    final tag = bytes[offset];
    if (expectedTag != null && tag != expectedTag) {
      throw const FormatException('Struktur sertifikat DER tidak valid.');
    }

    final firstLengthByte = bytes[offset + 1];
    var headerLength = 2;
    late final int contentLength;
    if (firstLengthByte < 0x80) {
      contentLength = firstLengthByte;
    } else {
      final lengthByteCount = firstLengthByte & 0x7f;
      if (lengthByteCount == 0 ||
          lengthByteCount > 4 ||
          offset + 2 + lengthByteCount > endLimit) {
        throw const FormatException('Struktur sertifikat DER tidak valid.');
      }

      var decodedLength = 0;
      for (var index = 0; index < lengthByteCount; index++) {
        decodedLength = (decodedLength << 8) | bytes[offset + 2 + index];
      }
      if (decodedLength < 0x80 || bytes[offset + 2] == 0) {
        throw const FormatException('Struktur sertifikat DER tidak valid.');
      }
      headerLength += lengthByteCount;
      contentLength = decodedLength;
    }

    final endOffset = offset + headerLength + contentLength;
    if (endOffset > endLimit) {
      throw const FormatException('Struktur sertifikat DER tidak valid.');
    }
    return _DerElement(
      tag: tag,
      offset: offset,
      contentOffset: offset + headerLength,
      endOffset: endOffset,
    );
  }
}

class _DerElement {
  const _DerElement({
    required this.tag,
    required this.offset,
    required this.contentOffset,
    required this.endOffset,
  });

  final int tag;
  final int offset;
  final int contentOffset;
  final int endOffset;
}
