#!/usr/bin/env bash
# ============================================================================
# verify_spki_pins.sh — gate §13 #5 (bagian verifikasi): bandingkan pin SPKI
# primary/backup aplikasi dengan rantai sertifikat riil pada endpoint.
#
#   CERT_SPKI_SHA256_PRIMARY=sha256/xxxx CERT_SPKI_SHA256_BACKUP=sha256/yyyy \
#     ./ops/tls/verify_spki_pins.sh api.pdam.go.id
#
# Pin HARUS cocok leaf-primary ATAU salah satu chain (untuk backup pin =
# key intermediate/gandeng yang bakal dirotasi).
# ============================================================================
set -u
HOST="${1:-api.pdam.go.id}"
PORT="${2:-443}"
P="${CERT_SPKI_SHA256_PRIMARY:-}"
B="${CERT_SPKI_SHA256_BACKUP:-}"

if [ -z "$P" ] || [ -z "$B" ]; then
  echo "WAJIB set CERT_SPKI_SHA256_PRIMARY + CERT_SPKI_SHA256_BACKUP (nilai dart-define)" >&2
  exit 64
fi
[ "$P" = "$B" ] && { echo "FAIL primary == backup (mobile menolak kombinasi ini)"; exit 1; }

SPKI_OF() { # cert PEM via stdin → base64 sha256 of DER SubjectPublicKeyInfo
  openssl x509 -pubkey -noout 2>/dev/null | openssl pkey -pubin -outform DER 2>/dev/null | openssl dgst -sha256 -binary | openssl base64
}

CH=$(echo | openssl s_client -connect "$HOST:$PORT" -servername "$HOST" -showcerts 2>/dev/null)
[ -z "$CH" ] && { echo "FAIL tidak bisa connect ke $HOST:$PORT"; exit 1; }

PINS=""
# leaf + tiap sertifikat chain
IDX=0
while IFS= read -r line; do
  case $line in
    "-----BEGIN CERTIFICATE-----") IDX=$((IDX+1)); : > "/tmp/pdam_chain_$IDX.pem"; ;;
    "-----END CERTIFICATE-----") echo "$line" >> "/tmp/pdam_chain_$IDX.pem"; ;;
    *) [ "$IDX" -gt 0 ] && echo "$line" >> "/tmp/pdam_chain_$IDX.pem" ;;
  esac
done <<< "$CH"

for f in /tmp/pdam_chain_*.pem; do
  [ -e "$f" ] || continue
  V=$(SPKI_OF < "$f")
  PINS="$PINS sha256/$V"
  echo "  $(basename "$f"): sha256/$V"
done
rm -f /tmp/pdam_chain_*.pem

OK=0
for WANT in "$P" "$B"; do
  if echo "$PINS" | grep -q "$(echo "$WANT" | sed 's|/|\\/|g')"; then
    echo "PASS pin $WANT ada pada rantai $HOST"
    OK=$((OK+1))
  else
    echo "FAIL pin $WANT TIDAK ditemukan pada rantai $HOST"
  fi
done

if [ "$OK" -eq 2 ]; then
  echo "KEDUA PIN terverifikasi — lanjut drill rotasi (lihat TLS_PINNING_ROTATION.md)."
  exit 0
fi
exit 1
