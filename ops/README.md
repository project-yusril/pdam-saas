# ops/ — Runbook & Tooling Operasional (gate §13)

Peta dokumen/fact sheet: [`../docs/DOC_MAP.md`](../docs/DOC_MAP.md) · status canonical: [`../temuan2.md`](../temuan2.md)
· angka live: [`../docs/COUNTS.json`](../docs/COUNTS.json). Alat operasional di bawah memisahkan **bukti
bisa dibuat sekarang** (CI/script) dari **bukti butuh dunia nyata** (domain/vendor/data produksi riil).

```
ops/
├── tls/verify_production_tls.sh   # gate #1: chain/HSTS/protocol/cipher/endpoint bukti JSON
├── tls/verify_spki_pins.sh        # gate #5 (verifikasi): pin primary/backup vs endpoint riil
├── runbooks/TLS_PINNING_ROTATION.md # gate #5 (drill rotasi primary<->backup, langkah demi langkah)
├── backup/backup_production.sh    # gate #3: dump single-DB→gzip→AES-256-GCM(+PBKDF2)+sha256+umask077 (kredensial via env)
├── backup/restore_production.sh   # gate #3: restore ke TARGET_DB eksplisit (refuse schema sumber, strip DEFINER)
├── backup/restore_drill.sh        # gate #3: backup→restore→row/CHECKSUM compare→RPO/RTO JSON
├── runbooks/BACKUP_RESTORE.md      # RPO≤60mnt / RTO≤4j + prosedur insiden
├── mysql/create_users.sh          # gate #4: render+apply provisioning least-priv (pdam_app/migrate/backup/audit/restore)
├── mysql/verify_privileges.sh     # gate #4: SHOW GRANTS parsing + probe penolakan (harus ditolak MySQL)
├── runbooks/DB_PRIVILEGES.md       # akun matriks + append-only + rotasi
├── PENTEST_SCOPE.md               # gate #2: scope paket utk pentester eksternal (belum vendor)
├── supervisor/crash_alert.py      # event-listener protokol supervisor (crash alert)
├── runbooks/OBSERVABILITY.md      # alerting rules + incident runbook + capacity baseline proses
├── load/run_load_test.sh          # k6 parameterized smoke|load|stress → docs/CAPACITY_BASELINE.md
├── simulators/telemetry_simulator.py # simulator Tier-3 IOT/PROD/DIST (AMR/SCADA/DMA) — kontrak = ml/tests+php
├── alerting/prometheus-rules.example.yml # contoh rule 5xx/queue/ML (diadaptasi ke Prometheus/Grafana)
├── logrotate/pdam.conf              # rotasi /var/log/pdam (worker/scheduler/queue-health)
└── nginx/ml-service.mtls.conf     # hardening C-05: mTLS utk private ML service
```

Dari sisi aplikasi tersedia artisan evidence-command:
`pdam:audit-db-privileges`, `pdam:queue-health [--max-failed …]`,
`pdam:integrations-health [--ping]`, `pdam:ml-export-training-data`, `pdam:counts` (inventaris drift),
`pdam:openapi` (spek dari route registry; committed `docs/openapi.json` + drift gate CI), `pdam:mobile-coverage`
(gate endpoint `endpoints.dart` ↔ registry), `tools/generate_openapi_surface.dart` (surface Dart utk test).
CI `workflow ci.yml (job mysql-production-gates)` menjalankan drill privilege
+ backup/restore otomatis di setiap PR.
Nightly `.github/workflows/nightly-ops.yml`: k6 capacity + telemetri simulator Tier-3
(hanya jalan bila repo-var `LOAD_ENABLED=true` + `LOAD_BASE_URL` staging diset;
jangan arahkan ke production). Rule contoh & rotasi log: `ops/alerting`, `ops/logrotate`.

## Urutan eksekusi saat production pertama kali berdiri
1. Deploy DB + `ops/mysql/create_users.sh` → jalankan migrate sebagai
   pdam_migrate, app pakai pdam_app (opsional pdam_audit via DB_AUDIT_*).
2. `php artisan migrate --force && php artisan db:seed --class=ProductionKernelSeeder --force`
   (demo tidak mungkin jalan — DemoGuard).
3. Domain live → `ops/tls/verify_production_tls.sh` + pasang pin hasil leaf/chain;
   simpan evidence.
4. `ops/backup/restore_drill.sh` di staging mirror → arsipkan RPO/RTO.
5. `ops/load/run_load_test.sh` di staging → capacity baseline.
6. Engage pentest (ops/PENTEST_SCOPE.md) → triage → sign-off.
7. Ekspor data ML `php artisan pdam:ml-export-training-data …` → setujui
   threshold → `calibrate_production.py --promote` → manifest
   production_calibrated=true + evidence.
8. `pdam:audit-db-privileges` + `pdam:queue-health` + `pdam:integrations-health`
   lulus → tandai checklist temuan2 §13 + SECURITY_CHECKLIST sebagai closed.


<!-- gap ops (pdam:mobile-coverage --report) tidak ada lagi silent gap: 261/293 jalur API memang bukan untuk aplikasi mobile; tinjau manual -->
