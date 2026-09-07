# Prosedur Kalibrasi ML Production (gate §13 #6)

**[DOC_MAP §3 gate #6](DOC_MAP.md)** = kalibrasi ML; status canonical [`../temuan2.md`](../temuan2.md) §13 · [panorama tooling](../ops/README.md)

## Prasyarat data
- Ekspor dari **produksi riil**: ≥ 12–24 bulan histori tagihan, ≥ 2.000 baris
  billing/readings, ≥ 100 pelanggan aktif (gerbang keras
  `production_acceptance.dataset` di `ml/config.yaml` — angka final
  disetujui tim data, bukan kode).
- Threshold penerimaan tiap model (`models.*.max_/min_` di config yang sama)
  HARUS disetujui pemilik produk sebelum dipakai promote.

## Langkah
```bash
# ── di server produksi (akun DB read-only cukup) ──
cd /var/www/pdam/backend
php artisan pdam:ml-export-training-data --org=1 --months=24 --out=/tmp/ml-dataset

# ── di mesin pipeline tepercaya (venv ml, copy folder ekspor) ──
cd ml
python scripts/calibrate_production.py --org 1 --export-dir /tmp/ml-dataset
# dry-run lolos? publish:
python scripts/calibrate_production.py --org 1 --export-dir /tmp/ml-dataset --promote
```

## Yang dijamin
- Artifact ditulis **candidate** dulu; `models/` hanya tertimpa saat
  `--promote`.
- Manifest menyimpan `provenance.production_calibrated = true` +
  dataset stats + ambang + metrics — diverifikasi ulang oleh kontrak
  `load_artifact` (checksum sha256 sebelum pickle).
- GAGAL threshold → exit 3 + laporan lengkap (`json`) tanpa mengubah
  artifact apa pun. Empat artifact lama tetap
  `fixture_validation` sampai proses ini lulus dengan data riil.

## Bukti penutupan gate
lampirkan: summary `production_calibration_summary.json`, tanggal ekspor,
jumlah baris dataset, git commit kandidat (`models/*.manifest.json` berubah
pada promote), nama approver threshold.
