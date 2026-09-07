# PDAM SaaS Deployment Runbook Draft

**Updated:** 7 September 2026  
**Status:** operational draft, not production approval

> **Authoritative current status:** [`../temuan2.md`](../temuan2.md) (canonical six production approval gates). Doc map & fact sheet: [`../docs/DOC_MAP.md`](../docs/DOC_MAP.md) · gate tooling: [`../ops/README.md`](../ops/README.md) · CI: [`../.github/workflows/ci.yml`](../.github/workflows/ci.yml) · Keputusan bisnis: [`../docs/BUSINESS_DECISIONS.md`](../docs/BUSINESS_DECISIONS.md) · Related: [`../README.md`](../README.md) · [`README.md`](README.md) · [`../PRD.md`](../PRD.md), [`../02_flow.md`](../02_flow.md) · archives [`../task.md`](../task.md), [`../temuan.md`](../temuan.md) · [`../SECURITY_CHECKLIST.md`](../SECURITY_CHECKLIST.md) · [`../HANDOVER.md`](../HANDOVER.md) · [`SEED_DATA.md`](SEED_DATA.md) · [`../ml/README.md`](../ml/README.md).

The MySQL 8.4.9 result in `temuan2.md` used a disposable, non-user, non-production database. It validates migrations, rollback/remigration, seeders, constraints, and tests; it does not approve a production environment. Never run `migrate:fresh` or the demo seeder against production data.

## Canonical Blocking Gates

Production approval requires evidence for exactly these six gates. The definitions in `../temuan2.md` are authoritative:

1. Production TLS endpoint verification.
2. External penetration test and blocking-finding closure.
3. Tested backup and restore.
4. Production database least privileges.
5. Certificate primary/backup rotation drill.
6. Representative-data ML calibration and acceptance.

## Preflight

- Confirm change approval, maintenance window, rollback owner, release artifact checksums, and incident contacts.
- Confirm `APP_ENV=production`, `APP_DEBUG=false`, strong `APP_KEY`, production domains, stateful Sanctum/CORS settings, secure cookie settings, and non-demo secrets.
- Separate runtime, migration, backup, and audit database accounts using
  `database/provisioning/mysql-privileges.sql` (least privileges + INSERT/SELECT-only table grants
  for `privacy_audit_events`). Verify with `php artisan pdam:audit-db-privileges` after `config:cache`.
- Seeder path is production-safe by construction: `DatabaseSeeder` routes to
  `ProductionKernelSeeder` only when `APP_ENV=production` — demo tenants, demo users,
  `DemoTenantSeeder`, and `SambasTenantSeeder` are hard-blocked by `Database\Seeders\Support\DemoGuard`
  (blockade is **absolute** for `APP_ENV=production` — no env escape hatch)
  (see `ProductionSeederIsolationTest`). Production bootstrap:
  `php artisan migrate --force && php artisan db:seed --class=ProductionKernelSeeder --force`.
  Optional first super-admin comes from `PLATFORM_ADMIN_EMAIL/NAME/PASSWORD` env (demo credentials and
  passwords shorter than 12 chars are rejected). Provision production tenants through the approved
  workflow (`TenantProvisioningService` / marketplace), never seeders.
- Do not run `php artisan db:seed` without `--class=ProductionKernelSeeder` in production; the default
  router already skips `DemoSeeder` there, and direct `--class=DemoSeeder` calls abort.
- Confirm the current backup has already passed a restore drill in an isolated environment.
- Run dependency checks from `backend/`:

```bash
composer audit
npm audit --audit-level=high
```

The 15 July snapshot was clean, but audits must be rerun for the release lockfiles.

## Application Verification

Run from `backend/` unless noted:

```bash
php artisan migrate:status
php artisan test
php artisan route:list --json
php artisan route:cache
npm test -- --run
npm run build
```

```bash
cd ../mobile
flutter analyze
flutter test --no-pub
```

```bash
cd ../ml
python -m compileall -q src scripts
python -m pytest tests -q   # atau unittest discover -s tests -v
```

Expected audit snapshot (7 Sep 2026), not a substitute for release output: SQLite backend **117/117
and 812 assertions**; route registry **388 rows (369 API + 19 web)**; Vitest **13** + build; Flutter**44/44**;
ML **29** tests (CI); `php artisan pdam:counts` sinkron vs `docs/COUNTS.json`. Live numbers always come from
`../docs/COUNTS.json` — regenerate with `php artisan pdam:counts --write`.

## ML Artifact Validation (runbook: `../docs/ML_CALIBRATION.md`)

Use supported Python 3.11 x64 in a trusted pipeline:

```bash
cd ../ml
python scripts/generate_validation_artifacts.py
```

Publish the four binaries together with manifest, schema, checksum, provenance, and validation summary. Binary artifacts are Git-ignored and must not be committed. Generated artifacts are gated by `ml/scripts/calibrate_production.py`: dataset representativeness (>=12 months, minima baris/customer) + per-model thresholds, then `--promote`. Today all four are `fixture_validation` with `production_calibrated=false`; Gate 6 requires representative historical data, approved thresholds, and a separately published production-calibrated artifact.

## Backup And Restore

Use the repo tooling — do not hand-roll ad-hoc dumps:

```bash
# tiap jam (cron host backup) — output .enc + .sha256 + .meta (RPO evidence)
BACKUP_PASSPHRASE_FILE=/etc/pdam/backup.pass ./ops/backup/backup_production.sh

# drill wajib end-to-end (decrypt → restore → CHECKSUM/row compare → RTO/RPO JSON)
./ops/backup/restore_drill.sh          # hasil: drill-work/restor-evidence.json
```

Record checksum, encryption/storage location, restore duration, row/schema integrity checks, and approved RPO/RTO (runbook: `../ops/runbooks/BACKUP_RESTORE.md`). Merely creating a dump does not close the backup/restore gate; the CI job `mysql-production-gates` runs this drill automatically on every PR.

## Migration And Maintenance

1. Enable maintenance mode and stop writes/background consumers that can conflict with schema changes.
2. Confirm the tested backup and rollback decision point.
3. Run migration preflight without mutating business data; tenant orphans and cross-tenant actors must be zero.
4. Apply migrations with the dedicated migration account.
5. Verify migration status, FK/index metadata, application tests, and smoke flows before reopening traffic.
6. Restart workers only after schema and config are verified.

```bash
php artisan down --retry=60
php artisan migrate --force
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

Do not physically delete a tenant. The 97 tenant FKs use `RESTRICT`, and eight actor FKs enforce same-tenant references. Use the approved decommission, retention, anonymization, and audit workflow.

For an empty production database, run the same migration-only command, verify the schema, then create
the first platform identity through an approved secret-injection/bootstrap procedure and provision tenants
through the platform workflow. Never reuse `superadmin@gmail.com`, password `12345678`, or demo tenant
For the platform super-admin, `ProductionKernelSeeder` reads `PLATFORM_ADMIN_EMAIL/NAME/PASSWORD`
from the environment and rejects demo credentials/weak passwords (<12 chars). No demo tenant or
password `12345678` can ever be created on a production environment — `DemoGuard` fails closed. If no
`PLATFORM_ADMIN_EMAIL` is set, bootstrap the first admin via the approved provisioning workflow.

## Queue And Scheduler

Run workers as supervised long-running services and monitor `failed_jobs`:

```bash
# install this managed file on the app host (2 worker procs + scheduler loop + health alarm)
cp docker/supervisord.production.conf /etc/supervisor/conf.d/pdam-queue.conf
supervisorctl reread && supervisorctl update && supervisorctl status
php artisan queue:work --queue=default --tries=3 --backoff=5,30,120 --max-time=3600   # manual/debug
```

The `default` queue is required for scheduled reports. Prefer the supervisord `pdam-scheduler`
program (60s loop of `schedule:run --force`); classic cron remains valid:

```cron
* * * * * cd /var/www/backend && php artisan schedule:run >> /dev/null 2>&1
```

Verify scheduled-report CRUD, manual run, dispatcher, timezone-to-UTC conversion, unique run slots, private artifact storage, permission/module reauthorization, history, and authorized download on staging/target.

## Operational Evidence Commands

Every production-supporting claim must be reproducible with repo tools:

```bash
php artisan pdam:audit-db-privileges       # least-priv gate check (MySQL only; fails closed)
php artisan pdam:queue-health              # backlog/failed_jobs alarm (exit!=0 on threshold)
php artisan pdam:integrations-health --ping # live checks (Midtrans/OCR/ML/mail/DB)
php artisan pdam:ml-export-training-data --org=1 --months=24 --out=ml-dataset
php artisan pdam:counts                    # drift detector for docs/COUNTS.json (CI gate)
```

See `../ops/README.md` for full runbooks.

## TLS And Mobile Pins

Verify the real production domain with the repo verifier (writes JSON evidence
under `ops/tls/evidence/` on the run host):

```bash
../ops/tls/verify_production_tls.sh pdam.go.id api.pdam.go.id admin.pdam.go.id   # chain/hostname/HSTS/protocol/cipher/endpoints
```

Build mobile with two distinct canonical SPKI values:

```bash
flutter build apk --release \
  --dart-define=CERT_SPKI_SHA256_PRIMARY="$CERT_SPKI_SHA256_PRIMARY" \
  --dart-define=CERT_SPKI_SHA256_BACKUP="$CERT_SPKI_SHA256_BACKUP"
```

Verify pins against the live endpoint and rehearse the swap drill (see
`../ops/runbooks/TLS_PINNING_ROTATION.md`):

```bash
CERT_SPKI_SHA256_PRIMARY="$CERT_SPKI_SHA256_PRIMARY" CERT_SPKI_SHA256_BACKUP="$CERT_SPKI_SHA256_BACKUP" \
  ../ops/tls/verify_spki_pins.sh api.pdam.go.id
```

Test the primary pin against the real endpoint, execute the backup-pin rotation drill, and prepare the next release before retiring a key. Configuration presence alone does not close TLS or rotation gates.

## Privacy Operations

Before an approved privacy purge:

1. Confirm a restorable backup and applicable legal retention rules.
2. Confirm least-privilege/append-only database protection for privacy audit events.
3. Use dry-run and review the exact scope.
4. Require a distinct second admin/compliance approver before expiry.
5. Verify the hash-linked audit event after execution.

## Health And Recovery

```bash
php artisan pdam:health-check
docker compose restart
```

Validate DB, Redis, disk, queue depth/age, failed jobs, scheduler heartbeat, external provider health, private-storage access, and ML readiness. A process liveness response alone is insufficient.

## Additional Operational Recommendations

These are important but are not extra canonical approval gates:

- Exercise queue/scheduler retry, failure, and recovery behavior on target topology.
- Verify Midtrans settlement, OCR, email/push, and each enabled external integration end-to-end.
- Keep demo seeders and credentials out of production provisioning.
- Establish logs, metrics, traces, alerts, on-call ownership, load/capacity tests, and incident drills.
