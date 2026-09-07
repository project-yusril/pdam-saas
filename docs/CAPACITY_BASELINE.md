# Capacity Baseline — PDAM SaaS API

**Diperbarui:** — (template; isi saat dijalankan pada staging mirror production)
**Dokumen terkait:** [`../ops/runbooks/OBSERVABILITY.md`](../ops/runbooks/OBSERVABILITY.md) · [`../ops/load/run_load_test.sh`](../ops/load/run_load_test.sh) · [`../tests/load/k6-load_test.js`](../tests/load/k6-load-test.js) · status gate: [`../temuan2.md`](../temuan2.md) §13.

> Isi file ini **hanya** dari run k6 pada topology staging yang mewakili production
> (1× nginx+php-fpm, 1× MySQL 8, 1× Redis, 1× worker `default`). Nilai `localhost` tidak
> mewakili kapasitas produksi; jangan dikutip sebagai baseline.

## Topologi uji (isi saat run)

| Komponen | Spec target | Catatan |
|---|---|---|
| Web/API | — (vCPU/RAM/node) | nginx production conf + php-fpm |
| MySQL | — | versi & tuning |
| Redis/queue | — | worker supervised (`supervisord.production.conf`) |
| ML service | — / tidak disertakan | kalau diikutkan, pakai base internal (mTLS) |

## Hasil per profil (isi dari artefak k6 `RESULT_JSON`/summary)

| Profil | VUs puncak | p95 login | p95 list | p95 dashboard | error rate | CPU peak | DB conns | Catatan |
|---|---|---|---|---|---|---|---|---|
| smoke | | | | | | | | sanity |
| load (50) | | | | | | | | harus < threshold k6 |
| stress | | | | | | | | titik patah pertama |

## Keputusan target

- Utilisasi puncak tahun-1 ditargetkan **< 0.6 × kapasitas stress-verified**; eksekusi
  scaling vertikal/horizontal di atas angka ini.
- Re-run tiap rilis besar/ubah skema besar/infrastruktur berubah; simpan run-id k6 +
  tanggal + git SHA runner di bawah.

| Run | Tanggal | Git SHA | Artefak JSON | p95 list | error rate | Verdict |
|---|---|---|---|---|---|---|
| 1 | | | | | | |
