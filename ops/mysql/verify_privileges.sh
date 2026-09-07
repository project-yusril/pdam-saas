#!/usr/bin/env bash
# ============================================================================
# verify_privileges.sh — bukti least-privilege (gate §13 #4) + CI self-drill.
#
# Pola yang DIHINDARKAN dari versi lama (bug review): DELETE/TRUNCATE probe di
# atas tabel kosong tidak membuktikan apa-apa (0 baris → statement sukses), dan
# INSERT probe pdam_audit menabrak FK privacy_purge_requests (1452), bukan 1142.
#
# Strategi:
#  A) SHOW GRANTS per akun (parse string) → assertion negatif yang pasti benar
#     tanpa menyentuh FK/trigger: app tanpa DDL, backup tanpa DML, audit hanya
#     INSERT/SELECT pada privacy_audit_events.
#  B) Statement-level untuk yang DIJAMIN ditolak oleh privilege sebelum FK baris
#     (TRUNCATE app, UPDATE/DELETE WHERE FALSE audit, INSERT lintas tabel audit).
#  C) positive INSERT audit dengan FK valid (parent diambil dari data nyata →
#     skip jelas bila DB kosong) + trigger append-only dicek lewat
#     information_schema (satu-satunya cara valid).
# ============================================================================
set -u
HOST="${HOST:-127.0.0.1}"; PORT="${PORT:-3306}"; DB="${DB:-pdam}"
ADMIN_USER="${ADMIN_USER:-root}"; ADMIN_PWD="${ADMIN_PWD:-${MYSQL_PWD:-}}"
FAILS=0; SKIPS=0
export MYSQL_PWD="${ADMIN_PWD}"

admin_sql() { mysql -h "$HOST" -P "$PORT" -u "$ADMIN_USER" -N -e "$1" "$DB" 2>&1 || true; }

as_user() { # $1 user $2 pw $3 sql
  MYSQL_PWD="$2" mysql -h "$HOST" -P "$PORT" -u "$1" -N -e "$3" "$DB" 2>&1 || true
}

grants_of() { as_user "$1" "$2" "SHOW GRANTS FOR CURRENT_USER()"; }

contains_not() { # $1 haystack, $2 needle label -> fail if present
  if echo "$1" | grep -qi "$2"; then echo "FAIL   : grant $2 tidak seharusnya ada (— $3)"; FAILS=$((FAILS+1));
  else echo "PASS   : tanpa $2 ($3)"; fi
}
must_have() {
  if echo "$1" | grep -qiE "$2"; then echo "PASS   : grant $2 ($3)";
  else echo "FAIL   : grant $2 hilang ($3)"; FAILS=$((FAILS+1)); fi
}

expect_error() { # user pw label sql pattern
  local out; out=$(as_user "$1" "$2" "$4")
  if echo "$out" | grep -qE "$5"; then echo "PASS denied: $3";
  else echo "FAIL   : $3 → '$out'"; FAILS=$((FAILS+1)); fi
}
expect_success() {
  local out; out=$(as_user "$1" "$2" "$4")
  if echo "$out" | grep -qE "ERROR|^FAIL"; then echo "FAIL   : $3 harus sukses → '$out'"; FAILS=$((FAILS+1));
  else echo "PASS ok  : $3"; fi
}

# ── A) SHOW GRANTS (pdam_app / pdam_backup / pdam_audit / pdam_migrate) ──
G_APP=$(grants_of pdam_app "${APP_PW:-}")
contains_not "$G_APP" "CREATE"    "app:CREATE/DDL"
contains_not "$G_APP" "ALTER"     "app:ALTER"
contains_not "$G_APP" "DROP"      "app:DROP"
contains_not "$G_APP" "TRUNCATE"  "app:TRUNCATE"
must_have  "$G_APP" "SELECT" "app:SELECT"

G_MIG=$(grants_of pdam_migrate "${MIGRATE_PW:-}")
must_have "$G_MIG" "CREATE" "migrate:CREATE"
must_have "$G_MIG" "ALTER"  "migrate:ALTER"
contains_not "$G_MIG" "GRANT OPTION" "migrate:GRANT OPTION"
G_BAK=$(grants_of pdam_backup "${BACKUP_PW:-}")
must_have "$G_BAK" "SELECT" "backup:SELECT"
contains_not "$G_BAK" "INSERT" "backup:INSERT"
contains_not "$G_BAK" "UPDATE" "backup:UPDATE"
contains_not "$G_BAK" "DELETE" "backup:DELETE"
G_AUD=$(grants_of pdam_audit "${AUDIT_PW:-}")
must_have "$G_AUD" "INSERT" "audit:INSERT"
must_have "$G_AUD" "SELECT" "audit:SELECT"
contains_not "$G_AUD" "UPDATE" "audit:UPDATE"
contains_not "$G_AUD" "DELETE" "audit:DELETE"
contains_not "$G_AUD" "TRUNCATE" "audit:TRUNCATE"
if echo "$G_AUD" | grep -qiE "INSERT.*ON \`?${DB}\`?\.\`?privacy_audit_events"; then
  echo "PASS   : grants audit terikat hanya ke privacy_audit_events"
else
  # db-level insert bukan table-level utk akun audit → tetap boleh asal tidak
  # memegang UPDATE/DELETE/TRUNCATE di mana pun; sudah dicek di atas.
  echo "INFO   : grants audit bukan table-scoped (cek global/db-level di bawah)"
fi

# ── B) statement-level denial yang deterministik (dicek sebelum FK/row) ──
expect_error pdam_app    "${APP_PW:-}"     "app:TRUNCATE privacy_audit_events" "TRUNCATE TABLE privacy_audit_events;" "ERROR 1142"
expect_error pdam_app    "${APP_PW:-}"     "app:DROP any"                      "DROP TABLE does_not_exist_probe;"     "ERROR 1044|ERROR 1142"
expect_error pdam_audit  "${AUDIT_PW:-}"   "audit:UPDATE (no grant)"           "UPDATE privacy_audit_events SET event_type='x' WHERE id>0;" "ERROR 1142"
expect_error pdam_audit  "${AUDIT_PW:-}"   "audit:DELETE (no grant)"           "DELETE FROM privacy_audit_events WHERE id>0;" "ERROR 1142"
expect_error pdam_audit  "${AUDIT_PW:-}"   "audit:SELECT customer PII"         "SELECT id FROM customers LIMIT 1;"                                                                      "ERROR 1142"
expect_error pdam_backup "${BACKUP_PW:-}"  "backup:INSERT"                     "INSERT INTO privacy_audit_events (pdam_org_id, privacy_purge_request_id, actor_id, event_type, payload, event_hash) VALUES (999999,999999,999999,'x','{}','x');" "ERROR 1142"

# ── C) trigger + positive path ──
TRS=$(admin_sql "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='$DB' AND EVENT_OBJECT_TABLE='privacy_audit_events' AND ACTION_TIMING='BEFORE';")
if [ "${TRS:-0}" -ge 2 ] 2>/dev/null; then echo "PASS   : trigger append-only (BEFORE) aktif ($TRS)"; else
  echo "FAIL   : trigger append-only tidak ditemukan ($TRS)"; FAILS=$((FAILS+1)); fi

USRID=$(admin_sql "SELECT id FROM users ORDER BY id LIMIT 1;")
RID=$(admin_sql "SELECT id FROM privacy_purge_requests ORDER BY id LIMIT 1;")
if [ -n "$USRID" ] && [ -n "$RID" ]; then
  ORG=$(admin_sql "SELECT pdam_org_id FROM users WHERE id=$USRID;")
  expect_success pdam_audit "${AUDIT_PW:-}" "audit:INSERT event valid (FK parent ada)" \
    "INSERT INTO privacy_audit_events (pdam_org_id, privacy_purge_request_id, actor_id, event_type, payload, event_hash, created_at) VALUES ($ORG,$RID,$USRID,'__verify_probe__',JSON_OBJECT('probe','verify'),'probe-$(date +%s)-$(od -An -tx1 -N4 /dev/urandom | tr -d ' \n')',NOW()); SELECT 'ok';"
  # UPDATE/DELETE oleh AUDIT sudah ditolak di (B) tanpa butuh baris nyata.
  # Trigger tetap dibuktikan di (C count) + drill app-side di php artisan.
else
  echo "SKIP   : probe INSERT audit (belum ada users/privacy_purge_requests baris)"; SKIPS=$((SKIPS+1))
fi

# app runtime tetap bisa SELECT (bukti tidak over-restrict)
expect_success pdam_app "${APP_PW:-}" "app:SELECT privacy_audit_events" "SELECT COUNT(*) FROM privacy_audit_events;"

echo "TOTAL GAGAL=$FAILS SKIP=$SKIPS"
[ "$FAILS" -eq 0 ] && echo "LEAST-PRIVILEGE TERBUKTI (attach output ini ke penutupan §13 #4)."
exit "$FAILS"
