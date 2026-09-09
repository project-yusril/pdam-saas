# Peta Pelanggan Sambas — OpenStreetMap + Leaflet (gratis): routing + geocoding

## Jawaban singkat: **Ya, memungkinkan dan cocok dengan kondisi proyek saat ini.**

Semua layanan yang dipakai 100% gratis: peta dasar **OpenStreetMap tiles**, **Leaflet 1.9.4** (sudah dipakai di `resources/views/admin/prospects/show.blade.php` via unpkg CDN), routing via **OSRM demo server**, geocoding via **Nominatim** (di-proxy server-side agar patuh aturan 1 req/s + User-Agent). Data demo **PDAM Kab. Sambas** sudah punya koordinat pelanggan (SambasTenantSeeder ~`1.35, 109.30`), status `isolir`, dan tagihan — jadi titik rumah langsung muncul di peta.

## Temuan kondisi saat ini

| Item | Status |
|---|---|
| `Customer.status` punya `latitude`/`longitude` | ✅ ada (nullable) |
| Endpoint API `GET api/v1/gis/customers` (GeoJSON) + `status-summary`, gate `module:GIS` | ✅ ada (`GisController`) |
| `CustomerMapStatusService` (warna status bayar) | ⚠️ ada tapi **bug** |
| Halaman web admin "Peta" untuk direktur/admin | ❌ belum ada |
| Geocoding & routing | ❌ belum ada |
| Legenda warna sesuai request user | ⚠️ perlu disesuaikan |

**Bug yang harus diperbaiki:**
1. `getStatusColor()` cek `$customer->status === 'disconnected'`, padahal status riil: `isolir | terminated | temporary_closed | inactive` (plus `'disconnected'` dari command `AutoIsolirFlag`). Efek: item **hitam tidak pernah muncul** kecuali command cron jalan.
2. `Disconnection::where('status','active')` — tabel `disconnections` **tidak punya kolom `status`** → SQL error saat cabang itu tersentuh. Sumber kebenaran cukup `Customer.status`.
3. N+1 parah: 4 query per pelanggan × limit 5000 → perlu agregasi per-batch 1–2 query.

## Desain warna ikon rumah (sesuai permintaan user)

Ikon = **SVG rumah** (divIcon Leaflet) diwarnai per status:

| Warna | Arti | Aturan |
|---|---|---|
| 🟢 Hijau | Lunas | tidak ada tagihan `unpaid/overdue` |
| 🔵 Biru muda | Ada tagihan, belum jatuh tempo | unpaid tapi `due_date >= hari ini` |
| 🟡 Kuning | Menunggak 1 bulan | 1 periode lewat jatuh tempo |
| 🟠 Oranye | Menunggak 2 bulan | 2 periode lewat jatuh tempo |
| 🔴 Merah | Menunggak ≥3 bulan | ≥3 periode |
| ⚫ Hitam | Sudah diputus/isolir | status ∈ {isolir, terminated, disconnected} |

Pelanggan `inactive` tidak ditampilkan di peta. Legenda interaktif (klik = filter).

## Implementasi

### A. Backend — perbaiki service peta (`app/Services/CustomerMapStatusService.php`)
- Rewriting kelas: 6 level warna di atas; hitung **periode** menunggak via tagihan (`period` distinct, `due_date < now()`, status unpaid/overdue).
- Metode batch baru: `classifyBatch(Collection $customers): array` → hanya 2 query GROUP BY (aggregate tunggakan + list pelanggan putus dari `Customer.status`), dipakai `GisController` (fix N+1) dan web controller.
- `buildGeoJsonFeature`: properties tetap (`status_color`, `status_label`, `marker_color`, `arrears_months`, `total_arrears`, `customer_status`, `zone_id`, `meter_route_id`, `address_detail`) + ikon `house-<color>`.
- `GisController::statusSummary` → pakai classifyBatch (keys: green/blue/yellow/orange/red/black).

### B. Service geo eksternal (gratis, URL bisa dioverride via env)
`config/services.php` baru:
```php
'osrm'      => ['base_url' => env('OSRM_BASE_URL', 'https://router.project-osrm.org')],
'nominatim' => ['base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
                'user_agent' => env('NOMINATIM_USER_AGENT', 'PDAM-Admin/1.0 (contact pdam-sambas)')],
```
- `app/Services/Geo/NominatimService.php` — `search(q, viewbox?)`: forward geocode (dipakai box pencarian alamat di peta + "isi koordinat otomatis" dari alamat pelanggan). Selalu kirim header User-Agent (wajib sesuai kebijakan Nominatim).
- `app/Services/Geo/OsrmService.php` — `route(points[], profile)` (polyline GeoJSON, distance, duration) dan `matrix(points[])` → urutan greedy nearest-neighbor untuk **rute baca meter** (sumber koordinat kantor/awal = titik pertama rute/pilih manual; maks ±15 titik/permintaan agar ramah server demo).
- ⚠️ Batasan (didokumentasikan): OSRM demo & Nominatim publik gratis dengan fair-use policy (1 req/s, bukan untuk traffic produksi). Untuk produksi: self-host OSRM/Photon/Nominatim via Docker atau ganti `*_BASE_URL` — tidak ada perubahan kode.

### C. Halaman web admin "Peta Pelanggan" (menu baru direktur/admin)
Controller `app/Http/Controllers/Web/GisWebController.php` + view `resources/views/admin/gis/peta.blade.php`, route baru di `routes/web.php` (grup yang sama, tambah gate `module:GIS`):
```
GET  admin/gis/map                      → halaman peta (view('admin.gis.peta'))
GET  admin/gis/customers.json           → GeoJSON (filter: zone, meter_route_id, color[], q, bbox)
GET  admin/gis/summary.json             → jumlah per warna
GET  admin/gis/search.json?q=           → proxy Nominatim (hasil kandidat alamat → pan peta)
GET  admin/gis/route.json?points=..     → proxy OSRM route (garis biru antar titik)
GET  admin/gis/tour.json?route_id=..    → urutan nearest-neighbor + polyline rute baca
POST admin/gis/customers/{customer}/geocode   → isi lat/lng dari alamat (throttle ketat)
POST admin/gis/geocode-missing          → batch isi koordinat kosong (cap 20x/call, sleep 1s antar request)
PATCH admin/gis/customers/{customer}/coordinates → set manual (mode "klik titik di peta" utk rumah yg tak ketemu geocoder)
```
Fitur view (Leaflet + OSM tiles + plugin `leaflet.markercluster` 1.5.3 via unpkg — pola sama dgn halaman prospek):
- Peta full-width (area Sambas: `fitBounds` dari data; default `[1.35, 109.30]` z11), zoom + skala.
- Marker rumah SVG berwarna + clustering; popup: nama, no. pelanggan, alamat, status, lama tunggakan, total tunggakan; tombol **"Rute ke sini"** (dari posisi peta) dan **"Rute dr sini"**.
- Panel kiri/statistik: kartu jumlah per warna (hijau/biru/kuning/oranye/merah/hitam) + jumlah pelanggan **tanpa koordinat**.
- Filter: zona (Sambas Kota, Pemangkat, ...), rute baca, status warna (checkbox), cari nama/nomor pelanggan.
- Tombol **"📍 Cari alamat"** (Nominatim → pan + pin kandidat) dan **"Isi koordinat otomatis"** (progress feedback), mode **"Pilih titik manual"** utk admin menandai lokasi rumah.
- Tab **"Rute Baca Meter"**: pilih rute → garis OSRM urutan kunjungan + urutan stop bernomor + jarak/waktu total.
- Menu navigasi `layouts/app.blade.php`: tambah link **Peta** (dan sekalian lengkapi: Rute Baca, Prospek) supaya direktur/admin punya akses.

### D. Konsistensi
- `DashboardController` juga pakai `where('status','disconnected')` → ubah `whereIn(['isolir','terminated','disconnected'])` agar angka "disconnected_customers" tidak selalu 0.
- `AutoIsolirFlag` memakai `changeStatus('disconnected', ...)` — dibiarkan (logic baru sudah men-cover nilainya).
- Verifikasi tenant demo Sambas punya subscription modul `GIS` (line 212 SambasTenantSeeder); jika belum, tandai aktif di seeder.

## Testing (mengikuti pola `tests/` yang ada)
- `Unit/CustomerMapStatusServiceTest`: 6 level warna, termasuk isolir→hitam, belum jatuh tempo→biru, penghitungan periode (bukan jumlah tagihan).
- `Feature/GisWebTest`: auth required + tenant isolation (pelanggan org lain tidak muncul di GeoJSON), summary keys, geocode endpoint (Http::fake Nominatim + assert header User-Agent), route proxy (Http::fake OSRM), throttling.
- `Feature/GisApiTest` (perbarui): `gis/customers` response shape tetap, hanya warna baru; cek tidak ada call-site lain yang pecah.
- Manual: `php artisan migrate --seed` (demo) → buka `/admin/gis/map` → titik Sambas muncul berwarna → cari "Sambas" → pan → klik marker → gambar rute OSRM.

## Dokumen
- Tambah bagian **"Peta Pelanggan (OSM)"** di `backend/SEED_DATA.md`: cara buka, legend, demo login Sambas, kebijakan fair-use Nominatim/OSRM + cara self-host gratis.

## Di luar scope (dicatat untuk fase berikutnya)
- Peta offline di mobile (Flutter) — data GeoJSON-nya sudah siap dipakai via API yang sama.
- Self-host tile server (belum perlu untuk jumlah pelanggan PDAM kabupaten; sesuai kebijakan usage OSM untuk pemakaian admin wajar).

## Perkiraan hasil akhir
Direktur/admin login web → menu **Peta** → melihat seluruh rumah pelanggan Sambas sebagai ikon rumah berwarna (hijau lunas → kuning 1 bln → oranye 2 bln → merah ≥3 bln → hitam putus), bisa cluster/zona/rute, cari alamat & geocode gratis, dan merender rute baca meter optimum sederhana via OSRM.
