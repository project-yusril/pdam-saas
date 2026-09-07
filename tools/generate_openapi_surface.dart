// generate_openapi_surface.dart — hasilkan surface API dari docs/openapi.json
// Run dari root repo (setelah `php artisan pdam:openapi`):
//   dart tools/generate_openapi_surface.dart
// Output: mobile/lib/api/generated/openapi_surface.dart  (JANGAN edit manual).
import 'dart:convert';
import 'dart:io';

String dartStr(String s) {
  final String esc = s
      .replaceAll(r'\', r'\\')
      .replaceAll("'", r"\'")
      .replaceAll('\n', r'\n')
      .replaceAll('\r', r'\r')
      .replaceAll('\$', r'\$');
  return "'$esc'";
}

void main(List<String> args) {
  final File spec = File('docs/openapi.json');
  if (!spec.existsSync()) {
    stderr.writeln('docs/openapi.json tidak ditemukan — jalankan dari root repo: '
        'cd backend && php artisan pdam:openapi --out=../docs/openapi.json');
    exit(2);
  }

  final Map<String, dynamic> doc =
      jsonDecode(spec.readAsStringSync()) as Map<String, dynamic>;
  final Map<String, dynamic> paths =
      (doc['paths'] as Map<String, dynamic>? ?? const <String, dynamic>{});

  final List<String> allPaths = paths.keys.toList()..sort();
  int nOps = 0;
  for (final dynamic p in paths.values) {
    nOps += (p as Map<String, dynamic>).length;
  }

  final List<String> out = <String>[];
  out.add('// openapi_surface.dart — GENERATED oleh tools/generate_openapi_surface.dart');
  out.add('// dari docs/openapi.json (route registry Laravel via pdam:openapi). JANGAN EDIT MANUAL.');
  out.add('// Regenerate (root repo):');
  out.add('//   cd backend && php artisan pdam:openapi --out=../docs/openapi.json');
  out.add('//   dart tools/generate_openapi_surface.dart');
  out.add('// ignore_for_file: type=lint');
  out.add('');
  out.add('/// Satu operasi API.');
  out.add('class ApiOp {');
  out.add('  const ApiOp({');
  out.add('    required this.method,');
  out.add('    required this.tag,');
  out.add('    required this.summary,');
  out.add('    required this.pathParams,');
  out.add('  });');
  out.add('');
  out.add('  final String method;');
  out.add('  final String tag;');
  out.add('  final String summary;');
  out.add('  final List<String> pathParams;');
  out.add('');
  out.add('  bool get isTemplate => pathParams.isNotEmpty;');
  out.add('}');
  out.add('');
  out.add('/// Path -> {METHOD: ApiOp}. Total operasi: $nOps; path unik: ${allPaths.length}.');
  out.add('const Map<String, Map<String, ApiOp>> apiSurface = <String, Map<String, ApiOp>>{');

  for (final String path in allPaths) {
    final Map<String, dynamic> ops = paths[path]! as Map<String, dynamic>;
    final List<String> params = RegExp(r'\{([^}]+)\}')
        .allMatches(path)
        .map((Match m) => m.group(1)!)
        .toList();

    out.add('  ${dartStr(path)}: <String, ApiOp>{');
    final List<String> verbs = ops.keys.toList()..sort();
    for (final String verb in verbs) {
      final Map<String, dynamic> op = ops[verb]! as Map<String, dynamic>;
      final String tag = (op['tags'] as List<dynamic>).isEmpty
          ? 'Default'
          : '${(op['tags'] as List<dynamic>).first}';
      final String summary = '${op['summary'] ?? ''}';
      final String paramList = params.isEmpty
          ? 'const <String>[]'
          : 'const <String>[${params.map((String p) => dartStr(p)).join(', ')}]';
      out.add('    ${dartStr(verb.toUpperCase())}: ApiOp(');
      out.add('      method: ${dartStr(verb.toUpperCase())},');
      out.add('      tag: ${dartStr(tag)},');
      out.add('      summary: ${dartStr(summary)},');
      out.add('      pathParams: $paramList,');
      out.add('    ),');
    }
    out.add('  },');
  }

  out.add('};');
  out.add('');
  out.add('/// true bila pasangan path+method dikenal oleh registry OpenAPI.');
  out.add('bool apiKnows(String path, String method) {');
  out.add('  final Map<String, ApiOp>? ops = apiSurface[path];');
  out.add('  if (ops == null) return false;');
  out.add('  return ops.containsKey(method.toUpperCase());');
  out.add('}');
  out.add('');

  final File target = File('mobile/lib/api/generated/openapi_surface.dart');
  target.parent.createSync(recursive: true);
  target.writeAsStringSync(out.join('\n'));
  stdout.writeln('Wrote ${target.path}: ${allPaths.length} path, $nOps operation');
}
