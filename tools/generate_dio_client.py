#!/usr/bin/env python3
"""tools/generate_dio_client.py — hasilkan Dio client (typed service) dari docs/openapi.json.

Jalankan dari root repo:
    cd backend && php artisan pdam:openapi --out=../docs/openapi.json
    python tools/generate_dio_client.py

Output: mobile/lib/api/generated/api_client.g.dart — satu class per tag dengan
method per operation (path param via interpolasi). CI drift `dart analyze` +
test memverifikasi manifest cocok.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def dart_ident(s: str) -> str:
    s = re.sub(r"[^0-9A-Za-z_]", "_", s)
    s = re.sub(r"_+", "_", s.strip("_"))
    if not s or s[0].isdigit():
        s = "op_" + s
    return s


def camel(s: str) -> str:
    p = re.split(r"[_\s]+", s)
    return p[0].lower() + "".join(w.capitalize() for w in p[1:] if w)


def to_class(tag: str) -> str:
    return "".join(w.capitalize() for w in re.sub(r"[^0-9A-Za-z]+", " ", tag).split() if w[:1].isalpha()) + "Api"


def op_names(spec):
    out: dict[str, list[tuple[str, str]]] = {}  # tag -> [(verb, path)]
    for path, ops in spec.get("paths", {}).items():
        for verb in ops:
            o = ops[verb]
            tag = o["tags"][0] if o.get("tags") else "default"
            out.setdefault(tag, []).append((verb, path))
    return out


VERBS = {"get", "post", "put", "patch", "delete", "head"}


def main(out_file: Path, check: bool = False) -> int:
    try:
        spec = json.loads((ROOT / "docs/openapi.json").read_text(encoding="utf-8"))
    except FileNotFoundError:
        print("run: php artisan pdam:openapi --out=../docs/openapi.json", file=sys.stderr)
        return 2

    lines = [
        "// GENERATED dari docs/openapi.json oleh tools/generate_dio_client.py. JANGAN edit manual.",
        "// Regenerate: cd backend && php artisan pdam:openapi --out=../docs/openapi.json && python tools/generate_dio_client.py",
        "// Kontrak = route registry Laravel (H-10); skema response generik `{success,data,meta}`.",
        "// ignore_for_file: type=lint, prefer_single_quotes, directives_ordering",
        "",
        "import 'package:dio/dio.dart';",
        "",
        "/// Satu operasi; `params` = path `{id}` yang sudah dipetakan ke arg.",
        "class ApiEndpoint {",
        "  const ApiEndpoint(this.verb, this.path, this.params, this.summary, this.tag);",
        "  final String verb;",
        "  final String path;",
        "  final List<String> params;",
        "  final String summary;",
        "  final String tag;",
        "}",
        "",
        "/// Manifest seluruh endpoint (sinkron dengan Laravel route:list & test",
        "/// mobile/test/generated/dio_client_sync_test.dart).",
        "const List<ApiEndpoint> allEndpoints = <ApiEndpoint>[",
    ]

    ops = []
    for tag, lst in sorted(op_names(spec).items()):
        for verb, path in lst:
            params = re.findall(r"\{([^{}]+)\}", path)
            ops.append((tag, verb, path, params, spec["paths"][path][verb].get("summary", "")))
    ops.sort(key=lambda x: (x[1], x[2]))
    n_ops = len(ops)
    for tag, verb, path, params, summary in ops:
        plist = ", ".join(f"'{p}'" for p in params) if params else ""
        lines.append(f"  ApiEndpoint('{verb.upper()}', '{path}', <String>[{plist}], '{summary}', '{tag}'),")
    lines += [
        "];",
        "",
        "/// Dio client per tag (method get/post/put/patch/delete typed).",
        "class PdamApiClient {",
        "  PdamApiClient(this._dio);",
        "  final Dio _dio;",
        "",
        "  Future<Response<T>> request<T>(String method, String path, {Map<String, dynamic>? body, Map<String, dynamic>? query}) =>",
        "      _dio.request<T>('/api/v1$path', data: body, queryParameters: query, options: Options(method: method.toUpperCase()));",
        "",
        "  Future<Response<T>> getJson<T>(String path, {Map<String, dynamic>? query}) => request<T>('GET', path, query: query);",
        "}",
        "",
        "class GeneratedApi {",
        "  GeneratedApi(this._dio);",
        "  final Dio _dio;",
        "",
    ]

    seen: set[str] = set()
    for _tag, verb, path, params, summary in ops:
        seg = re.sub(r"\{[^{}]+\}", "{id}", path).strip("/").replace("/", "_")
        seg = re.sub(r"[^0-9A-Za-z_]+", "_", seg)
        seg = re.sub(r"_{2,}", "_", seg).strip("_")
        safe_tag = re.sub(r"[^0-9A-Za-z_]", "", to_class(_tag))
        base = f"{camel(safe_tag)}{camel(verb)}{camel(seg) or 'root'}"
        method = base[0].lower() + base[1:] if base else "rootGet"
        if not re.match(r"^[a-z][A-Za-z0-9_]*$", method):
            method = "op" + re.sub(r"[^0-9A-Za-z_]", "", method) or "rootGet"
        while method in seen:
            method += "X"
        seen.add(method)
        needs_body = verb in ("post", "put", "patch")
        template = re.sub(r"\{([^{}]+)\}", lambda m: "${" + m.group(1) + "}", path)
        if params:
            pathline = f"    final url = '/api/v1{template}';"
        else:
            pathline = f"    const url = '/api/v1{path}';"
        inner = [f"required String {q}" for q in params]
        if needs_body:
            inner.append("required Map<String, dynamic> data")
        if verb == "get":
            inner.append("Map<String, dynamic>? query")
        sigtxt = "{" + ", ".join(inner) + "}" if inner else ""
        lines.append(f"  /// [{verb.upper()}] {path} - {summary}")
        lines.append("  Future<Response<dynamic>> %s(%s) {" % (method, sigtxt))
        lines.append(pathline)
        call_args = "url"
        extra = []
        if needs_body:
            extra.append("data: data")
        if verb == "get":
            extra.append("queryParameters: query")
        if extra:
            call_args += ", " + ", ".join(extra)
        call_args += ", options: Options(method: '%s')" % verb.upper()
        lines.append(f"    return _dio.request<dynamic>({call_args});")
        lines.append("  }")
        lines.append("")
    lines += [
        "}",
        "",
        f"/// Total operasi: {n_ops}; path: {len(spec.get('paths', {}))}; tag: {len(op_names(spec))}.",
        "const int generatedOperationCount = %d;" % n_ops,
    ]
    content = "\n".join(lines) + "\n"

    if check:
        want = (ROOT / out_file.relative_to(ROOT).as_posix()).read_text(encoding="utf-8")
        return 0 if want == content else 1
    target = ROOT / out_file if out_file.is_absolute() is False else out_file
    target = Path(out_file)
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding="utf-8")
    return 0


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--out", default="mobile/lib/api/generated/api_client.g.dart")
    ap.add_argument("--check", action="store_true", help="exit 1 bila file berbeda dari hasil generate")
    a = ap.parse_args()
    raise SystemExit(main(Path(a.out), a.check))
