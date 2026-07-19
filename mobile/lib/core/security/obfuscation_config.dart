import 'dart:convert';
import 'package:flutter/services.dart';

class ObfuscationConfig {
  ObfuscationConfig._();

  static const String _encodedKey = 'UEFTX1M0Mjc4X1BEQU1f';
  static const String _encodedSalt = 'UE5HTUJFX0VTQV8yMDI0';

  static String get decodedKey => utf8.decode(base64.decode(_encodedKey));
  static String get decodedSalt => utf8.decode(base64.decode(_encodedSalt));

  static String _shift(String input, int shift) {
    return String.fromCharCodes(
      input.codeUnits.map((c) => ((c + shift) % 65536)),
    );
  }

  static String _unshift(String input, int shift) {
    return String.fromCharCodes(
      input.codeUnits.map((c) => ((c - shift + 65536) % 65536)),
    );
  }

  static String obfuscate(String plainText, [int shift = 13]) {
    return _shift(plainText, shift);
  }

  static String deobfuscate(String obfuscated, [int shift = 13]) {
    return _unshift(obfuscated, shift);
  }

  static void clearOnSecurityBreach() {
    // Placeholder hook for wiping sensitive in-memory data on a
    // detected security breach. Access the root isolate token statically.
    final _ = RootIsolateToken.instance;
  }

  static String get buildObfuscationCommand {
    return [
      'flutter build apk --release',
      '--obfuscate',
      '--split-debug-info=build/debug-info',
    ].join(' ');
  }

  static String get buildAppBundleObfuscationCommand {
    return [
      'flutter build appbundle --release',
      '--obfuscate',
      '--split-debug-info=build/debug-info',
    ].join(' ');
  }
}
