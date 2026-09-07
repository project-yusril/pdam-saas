// Test drift H-10: path API yang dipakai mobile (endpoints.dart + datasource
// yang memanggil Dio) harus terdaftar di surface OpenAPI registry.
// Rute navigasi (/home, /survey ...) sengaja tidak dipindai.
// Perbandingan parameter-agnostic ({id} vs {prospect} sama).
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:pdam_mobile/api/generated/openapi_surface.dart';

bool _matches(String clientPath, String registryPath) {
  if (clientPath == registryPath) return true;
  final List<String> a = clientPath.split('/');
  final List<String> b = registryPath.split('/');
  if (a.length != b.length) return false;
  for (int i = 0; i < a.length; i++) {
    final bool paramA = a[i].startsWith('{');
    final bool paramB = b[i].startsWith('{');
    if (paramA || paramB) continue;
    if (a[i] != b[i]) return false;
  }
  return true;
}

/// `/${id}` & `{p}` → semua `{param}` jadi `{p}`.
String _paramNorm(String p) => p
    .replaceAllMapped(
        RegExp(r'\$\{[^}]*\}'), (Match m) => '{p}')
    .replaceAllMapped(RegExp(r'\{[^}]+\}'), (Match m) => '{p}');

bool _looksLikeApiPath(String p) {
  if (p == '/' || p.isEmpty) return false;
  if (p.contains('{')) return true;
  if (!p.startsWith('/')) return false;
  if (p.contains(r'$') && !p.contains(r'/$')) return false;
  // rute navigasi mobile yang diketahui (bukan API)
  const List<String> nav = <String>[
    '/login',
    '/home',
    '/splash',
    '/portal',
    '/meter-reading',
    '/survey',
    '/notifications',
    '/profile',
    '/onboarding',
    '/form',
    '/history',
    '/receipt',
    '/help',
  ];
  return !nav.any((String n) => p == n || p.startsWith('$n/'));
}

List<String> _collectPaths(List<String> files) {
  final List<String> found = <String>[];
  for (final String f in files) {
    final File file = File(f);
    if (!file.existsSync()) continue;
    final String src = file.readAsStringSync();
    for (final RegExpMatch m
        in RegExp(r"'(/[\w\-/]+(?:/\$\{[^}]+\})*)'").allMatches(src)) {
      found.add(m.group(1)!);
    }
    for (final RegExpMatch m
        in RegExp(r'''"/([\w\-/]+)"''' , multiLine: true).allMatches(src)) {
      found.add(m.group(1)!);
    }
  }
  return found;
}

void main() {
  final List<String> files = <String>[
    'lib/core/network/endpoints.dart',
    ...Directory('lib/features').listSync(recursive: true).whereType<File>()
        .map((FileSystemEntity e) => e.path)
        .where((String s) => s.contains(r'data\datasources') || s.contains('/data/datasources')),
  ];

  test('path API mobile ↔ surface OpenAPI registry (H-10)', () {
    final File endpoints = File('lib/core/network/endpoints.dart');
    expect(endpoints.existsSync(), isTrue);

    final List<String> all =
        _collectPaths(files).where(_looksLikeApiPath).toList();
    expect(all.length, greaterThanOrEqualTo(20),
        reason: 'scanner hanya nemu ${all.length} path — pastikan datasources mobile ada: $files');

    final List<String> surface = apiSurface.keys.toList();
    final List<String> missing = <String>[];
    for (final String raw in all) {
      final String norm = _paramNorm(raw);
      final bool ok = surface.any((String s) => _matches(norm, _paramNorm(s)));
      if (!ok) missing.add(raw);
    }

    expect(missing, isEmpty, reason: '''
Path API dipakai mobile tidak ditemukan di surface (docs/openapi.json via route registry):
${missing.map((String s) => '  - $s').join('\n')}
Fix: implement API path di backend/routes/api.php, lalu regenerate:
  cd backend && php artisan pdam:openapi --out=../docs/openapi.json
  dart tools/generate_openapi_surface.dart  (dari root repo)
''');
  });

  test('apiKnows dasar benar', () {
    final String first = apiSurface.keys.first;
    final String verb = apiSurface[first]!.keys.first;
    expect(apiKnows(first, verb), isTrue);
    expect(apiKnows('/___tidakada___', 'GET'), isFalse);
  });
}
