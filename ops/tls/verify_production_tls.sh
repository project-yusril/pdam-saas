#!/usr/bin/env bash
# ============================================================================
# verify_production_tls.sh — gate §13 #1: verifikasi TLS riil pada endpoint
# production (bukan config). Jalankan dari mesin eksternal terhadap target.
#
#   ./ops/tls/verify_production_tls.sh api.pdam.go.id [web.pdam.go.id ...]
#
# Bukti = output PASS/FAIL + file ./ops/tls/evidence/tls-<host>-<ts>.json
# (attach ke temuan2.md saat menutup gate).
# Exit 0 = semua host PASS.
# ============================================================================
set -u

if [ $# -lt 1 ]; then
  echo "usage: $0 <host> [host...]" >&2
  exit 64
fi

EVIDENCE_DIR="$(cd "$(dirname "$0")/.." && pwd)/tls/evidence"
mkdir -p "$EVIDENCE_DIR"
ALL_FAIL=0

check() { # $1 desc, $2 cmd...
  local desc="$1"; shift
  if "$@" >/dev/null 2>&1; then echo "PASS $desc"; return 0; else echo "FAIL $desc"; return 1; fi
}

for HOST in "$@"; do
  echo "══════ HOST: $HOST ══════"
  FAILS=0
  TS=$(date -u +%Y%m%dT%H%M%SZ)
  EV="$EVIDENCE_DIR/tls-$HOST-$TS.json"

  # 1) DNS
  if check "DNS resolve" getent hosts "$HOST"; then :; else FAILS=$((FAILS+1)); fi

  # 2) HTTP → HTTPS redirect 301/302/308
  CODE=$(curl -s -o /dev/null -w '%{http_code}' -L --max-redirs 0 "http://$HOST/" || true)
  LOC=$(curl -s -o /dev/null -w '%{redirect_url}' --max-redirs 0 "http://$HOST/" || true)
  if echo "$CODE" | grep -qE '^30[1-8]$' && echo "$LOC" | grep -q '^https://'; then
    echo "PASS HTTP→HTTPS redirect ($CODE $LOC)"
  else
    echo "FAIL HTTP→HTTPS redirect (code=$CODE loc=$LOC)"; FAILS=$((FAILS+1))
  fi

  # 3) Sertifikat: hostname, chain, expiry
  SRV=$(echo | openssl s_client -connect "$HOST:443" -servername "$HOST" 2>/dev/null | openssl x509 -noout -dates -subject -issuer 2>/dev/null || true)
  echo "$SRV" | sed 's/^/  cert: /'
  echo "$SRV" | grep -q "CN=\*\.$HOST\|DNS:$HOST\|CN=$HOST" && echo "PASS SAN/CN cocok" || { echo "FAIL SAN/CN tidak cocok"; FAILS=$((FAILS+1)); }

  # 4) Chain verifikasi (CA asli, tanpa -no_verify)
  echo | openssl s_client -connect "$HOST:443" -servername "$HOST" 2>/dev/null | grep -q "Verify return code: 0 (ok)" \
    && echo "PASS chain tervalidasi (ok)" || { echo "FAIL chain TIDAK tervalidasi"; FAILS=$((FAILS+1)); }

  # 5) HSTS
  HSTS=$(curl -sI --max-time 15 "https://$HOST/" -k | grep -i '^strict-transport-security:' || true)
  if echo "$HSTS" | grep -qi "max-age=63072000" && echo "$HSTS" | grep -qi "includeSubDomains"; then
    echo "PASS HSTS ($HSTS)"
  else
    echo "FAIL HSTS kurang ($HSTS)"; FAILS=$((FAILS+1))
  fi

  # 6) Protokol & cipher lemah ditolak
  for PROTO in tls1 tls1_1 dtls1; do
    if echo | openssl s_client -connect "$HOST:443" -$PROTO >/dev/null 2>&1; then
      echo "FAIL protokol lemah $PROTO diterima"; FAILS=$((FAILS+1))
    else
      echo "PASS $PROTO ditolak"
    fi
  done
  if echo | openssl s_client -connect "$HOST:443" -cipher 'aNULL:eNULL:EXPORT:DES:RC4' >/dev/null 2>&1; then
    echo "FAIL cipher lemah diterima"; FAILS=$((FAILS+1))
  else
    echo "PASS cipher lemah ditolak"
  fi

  # 7) TLS 1.3 aktif (disarankan)
  echo | openssl s_client -connect "$HOST:443" -tls1_3 >/dev/null 2>&1 \
    && echo "PASS TLS1.3" || echo "WARN TLS1.3 (fallback 1.2 diterima)"

  # 8) Endpoint aplikasi lewat HTTPS (API mobile + health)
  UP=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "https://$HOST/up" || true)
  API=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "https://$HOST/api/v1/mobile/provinces" || true)
  [ "$UP" = "200" ] && echo "PASS /up via https" || { echo "FAIL /up ($UP)"; FAILS=$((FAILS+1)); }
  [ "$API" = "200" ] && echo "PASS api publik via https" || echo "WARN api publik ($API) — cek apakah endpoint tsb memang publik"

  # 9) Info server bocor?
  if curl -sI "https://$HOST/" | grep -qiE '^server:.*(apache/[0-9]|php/[0-9])'; then
    echo "WARN header Server membocorkan versi"; fi

  echo "{" > "$EV"
  echo "  \"host\": \"$HOST\"," >> "$EV"
  echo "  \"checked_at\": \"$TS\"," >> "$EV"
  echo "  \"tls_fails\": $FAILS," >> "$EV"
  echo "  \"up_status\": \"$UP\", \"api_status\": \"$API\"" >> "$EV"
  echo "}" >> "$EV"
  echo "evidence → $EV"

  [ "$FAILS" -eq 0 ] || ALL_FAIL=1
done

if [ "$ALL_FAIL" -eq 0 ]; then
  echo "SEMUA HOST LULUS verifikasi TLS production."
else
  echo "ADA HOST GAGAL — jangan tandai gate §13 #1 tertutup." >&2
fi
exit $ALL_FAIL
