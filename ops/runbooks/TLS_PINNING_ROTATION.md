# Runbook — TLS Production + SPKI Pin Rotation (gate §13 #1 & #5)

**Runbook terkait:** [`DOC_MAP.md`](../../docs/DOC_MAP.md) · status canonical [`temuan2.md`](../../temuan2.md) §13 gate #1/#5

## A. Verifikasi endpoint TLS pertama kali
Prasyarat: domain terarah + certbot terpasang + `docker/nginx.production.conf`
diaktifkan.

```bash
./ops/tls/verify_production_tls.sh pdam.go.id api.pdam.go.id admin.pdam.go.id
./ops/tls/verify_spki_pins.sh api.pdam.go.id 443   # dengan CERT_SPKI_SHA256_PRIMARY/BACKUP terisi
```
Keduanya exit 0 → simpan evidence (`ops/tls/evidence/*.json`) → baru gate #1/#5
(sebagian) dianggap terverifikasi endpoint.

### Cara menghitung pin saat cert pertama terbit
```bash
echo | openssl s_client -connect api.pdam.go.id:443 -servername api.pdam.go.id 2>/dev/null \
 | openssl x509 -pubkey -noout | openssl pkey -pubin -outform DER \
 | openssl dgst -sha256 -binary | openssl base64          # → PRIMARY
# BACKUP: pin SPKI key CA intermediate yang dipakai LE (mis. R11) — ambil dari
# rantai verifikasi certbot, atau pin key cadangan milik sendiri (self-signed
# long-lived key di vault yang bisa di-swap ke leaf baru).
```
Nilai dipakai build: `flutter build apk --release --dart-define=CERT_SPKI_SHA256_PRIMARY=sha256/<...> --dart-define=CERT_SPKI_SHA256_BACKUP=sha256/<...>`

## B. Drill rotasi (wajib sebelum distribusi mobile production)
1. **Siapkan cert/key baru** (atau intermediate baru) — jangan revoke lama dulu.
2. Deploy cert di nginx → reload (`systemctl reload nginx`).
3. Karena primary leaf tetap dipinj lama + backup pin aktif, aplikasi versi
   lama **masih terhubung** (pin cadangan = jendela kompatibilitas).
4. Verifikasi kedua pin: `./ops/tls/verify_spki_pins.sh api.pdam.go.id`
   (harus PASS; PRIMARY pin lama masih cocok di rantai lama ATAU backup cocok).
5. Ganti `CERT_SPKI_SHA256_PRIMARY` dengan pin cert baru di pipeline build →
   rilis aplikasi baru; validasi 1 device staging ter-upgrade.
6. Setelah ≥ 99% device migrasi (ukur dari crash/telemetry versi lama),
   revoke/cabut cert lama → primary lama mati → hanya backup chain → drill
   balik (ulangi 1–5 sebagai primary lama↔baru tukar posisi).
7. Catat tanggal + device id + hasil di checklist penutupan gate §13 #5.

## C. HSTS / preload
Setelah dua masa rotasi sukses tanpa insiden, daftarkan `pdam.go.id` ke
preload list (butuh includeSubDomains + preload yang sudah ada di
`nginx.production.conf`).
