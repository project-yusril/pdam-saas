# PDAM SaaS Deployment Runbook Draft

**Updated:** 18 July 2026  
**Status:** operational draft, not production approval

> **Authoritative current status:** [`../temuan2.md`](../temuan2.md), including the canonical six production approval gates. Related docs: [`../README.md`](../README.md) · [`README.md`](README.md) · [`../PRD.md`](../PRD.md) and [`../02_flow.md`](../02_flow.md) as target/design · [`../task.md`](../task.md) and [`../temuan.md`](../temuan.md) as archives · [`../SECURITY_CHECKLIST.md`](../SECURITY_CHECKLIST.md) as an internal baseline · [`../HANDOVER.md`](../HANDOVER.md) · [`SEED_DATA.md`](SEED_DATA.md) · [`../ml/README.md`](../ml/README.md).

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
- Separate runtime, migration, backup, and audit database accounts. Runtime must not have schema-drop privileges. Apply append-only privileges where required, including `privacy_audit_events`.
- Remove `DemoTenantSeeder` from the production seed path. Provision production tenants through the approved workflow.
- Do not run `php artisan db:seed`, `migrate --seed`, or `migrate:fresh --seed` in production. The
  current `DatabaseSeeder` includes demo credentials. Production bootstrap is migration-only until a
  dedicated production seeder path is implemented and verified in `../temuan2.md`.
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
python -m unittest discover -s tests -v
```

Expected audit snapshot, not a substitute for release output: SQLite backend 89/89 and 664 assertions; MySQL 88 passed plus one intentional SQLite-only skip, 756 assertions, zero failures; 358 routes; web 5/5 and 322 modules; Flutter 44/44; Python 3.11 25/25; Composer/npm audits clean.

## ML Artifact Validation

Use supported Python 3.11 x64 in a trusted pipeline:

```bash
cd ../ml
python scripts/generate_validation_artifacts.py
```

Publish the four binaries together with manifest, schema, checksum, provenance, and validation summary. Binary artifacts are Git-ignored and must not be committed. The generated artifacts are `fixture_validation` with `production_calibrated=false`; they are smoke/contract artifacts only. Gate 6 requires representative historical data, approved metrics/thresholds, and a separately published production-calibrated artifact.

## Backup And Restore

Example commands must be adapted to the managed database and secret mechanism. Do not put passwords in shell history:

```bash
mysqldump --single-transaction --routines --triggers -u pdam_backup -p pdam_saas | gzip > backup_$(date +%Y%m%d_%H%M%S).sql.gz
```

```bash
gunzip < backup_YYYYMMDD_HHMMSS.sql.gz | mysql -u pdam_restore -p pdam_restore_validation
```

Record checksum, encryption/storage location, restore duration, row/schema integrity checks, and approved RPO/RTO. Merely creating a dump does not close the backup/restore gate.

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
codes. The current repository does not yet define a production-safe identity seeder; this is tracked as an
open operational item in `../temuan2.md`.

## Queue And Scheduler

Run workers as supervised long-running services and monitor `failed_jobs`:

```bash
php artisan queue:work --tries=3 --timeout=90 --queue=default,notifications,payments
```

The `default` queue is required for scheduled reports. Run Laravel scheduling every minute from the host:

```cron
* * * * * cd /var/www/backend && php artisan schedule:run >> /dev/null 2>&1
```

Verify scheduled-report CRUD, manual run, dispatcher, timezone-to-UTC conversion, unique run slots, private artifact storage, permission/module reauthorization, history, and authorized download on staging/target.

## TLS And Mobile Pins

Verify the real production domain, certificate chain, hostname, protocol/cipher policy, redirects, HSTS, web cookies, CORS, and CSRF bootstrap. Build mobile with two distinct canonical SPKI values:

```bash
flutter build apk --release \
  --dart-define=CERT_SPKI_SHA256_PRIMARY="$CERT_SPKI_SHA256_PRIMARY" \
  --dart-define=CERT_SPKI_SHA256_BACKUP="$CERT_SPKI_SHA256_BACKUP"
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
