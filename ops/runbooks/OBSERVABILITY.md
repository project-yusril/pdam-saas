# Runbook — Observability, Alerting & Kapasitas (rekomendasi §13)

**Runbook terkait:** [`DOC_MAP.md`](../../docs/DOC_MAP.md) · [`CAPACITY_BASELINE.md`](../../docs/CAPACITY_BASELINE.md) · status canonical [`temuan2.md`](../../temuan2.md)

## Sinyal wajib produksi
| Sinyal | Sumber | Alert |
|--------|--------|-------|
| HTTP 5xx rate > 1% / p95 > 1s | nginx access log → Loki/Prom tail | P2 |
| `pdam:health-check` (DB/Redis/Disk/Queue) | `*/5 * * * *` → exit code + log | P1 saat FAIL |
| Queue backlog > 1000 / failed_jobs > 20 | `pdam:queue-health` (supervisord loop + syslog) | P2 |
| Scheduler heartbeat (`reports:dispatch-scheduled` tiap menit) | log scheduler + `scheduled_report_runs` stagnan > 3 menit | P3 |
| Webhook Midtrans gagal verifikasi beruntun ≥ 5 | `midtrans_webhook_logs` | P3 |
| ML `/health/ready` 503 | endpoint service + LB health | P3 |
| FailedLogin spike (login 429/lock > 50/menit) | log auth | P3 |

Format alert di syslog: `logger -t pdam-supervisor` (supervisord
eventlistener `ops/.../supervisord.production.conf`). Hook
rsyslog→PagerDuty/Telegram sesuai stack monitoring yang dipilih.

Contoh rule siap pakai (prometheus/Grafana, datasource diadaptasi):
`ops/alerting/prometheus-rules.example.yml`. Rotasi log `ops/logrotate/pdam.conf`.

## Kapasitas baseline (proses)
1. Deploy staging cermin produksi (1 web+php-fpm, 1 mysql, 1 redis, worker).
2. `PROFILE=smoke ./ops/load/run_load_test.sh` → sanity
3. `PROFILE=load ./ops/load/run_load_test.sh` (50 VU); `PROFILE=stress` untuk
   mencari patah. Catat: p95 login/list, error rate, CPU/DB connections.
4. Simpan JSON + ringkasan tabel di `docs/CAPACITY_BASELINE.md`; target
   `capacity_utilisasi_year1 < 0.6 × puncak_stress_verified`.
5. Re-run tiap rilis besar atau perubahan skema besar.

## Insiden umum PDAM
- **Data tidak muncul di dashboard**: cek `pdam:integrations-health`
  + `pdam:health-check`; worker default aktif? (`supervisorctl status`).
- **Laporan terjadwal tidak jalan**: queue default penuh/mati →
  `php artisan queue:monitor`? atau restart `pdam-queue-*`; cek
  `failed_jobs` → `php artisan queue:retry all`.
- **Backup gagal**: `df` volume target; `BACKUP_REMOTE_CMD` timeout; retry
  manual satu file, lalu bandingkan `.sha256`.
