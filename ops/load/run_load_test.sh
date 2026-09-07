#!/usr/bin/env bash
# ============================================================================
# run_load_test.sh — runner k6 untuk observability + capacity baseline di
# topology representatif (rekomendasi §13). Parameterisasi penuh:
#
#   BASE_URL=https://api.pdam.go.id/api/v1 \
#   PDAM_CODE=demo? -> GUNAKAN AKUN SERVICE / tenant uji khusus
#   LOGIN_EMAIL=loadtest@pdam.go.id LOGIN_PASSWORD=... \
#   PROFILE=load k6 run tests/load/k6-load-test.js
#
# k6 menolak jalan tanpa kredensial via env (tidak ada lagi hardcoded creds).
# ============================================================================
set -euo pipefail
: "${BASE_URL:?BASE_URL wajib, mis. https://api.pdam.go.id/api/v1}"
: "${LOGIN_EMAIL:?LOGIN_EMAIL akun service uji}"
: "${LOGIN_PASSWORD:?LOGIN_PASSWORD}"
: "${PDAM_CODE:?PDAM_CODE tenant uji}"
export BASE_URL LOGIN_EMAIL LOGIN_PASSWORD PDAM_CODE
export PROFILE="${PROFILE:-load}"
OUT="${RESULT_JSON:-k6-$(date -u +%Y%m%dT%H%M%SZ)-$PROFILE.json}"

k6 run --out json="$OUT" --tag meta:profile="$PROFILE" \
  tests/load/k6-load-test.js

echo "Ringkasan thresholds tertulis oleh k6 di atas; artefak raw: $OUT"
echo "Simpan bersama capacity-baseline.md (p95/login, p95/list, VUs peak) sebagai bukti §13 load test."
