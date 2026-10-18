# IRIS-BBWSNTII

**Irrigation Information System — BBWS Nusa Tenggara II**

Peta interaktif dan laporan harian kondisi 26 Daerah Irigasi (D.I.) kewenangan pusat di Nusa Tenggara Timur, untuk memantau air irigasi dan memprediksi kekeringan sawah per musim tanam (MT I, II, III).

Stack: Laravel 11 · MySQL/MariaDB · Blade + Alpine.js · Tailwind CSS · Leaflet. Tanpa paket Composer tambahan, jadi aman untuk shared hosting.

## Fitur Tahap 1

| Modul | Isi |
| --- | --- |
| Peta publik (`/`) | Poligon D.I. fungsional/baku/potensial dari Peta IGT, warna status hari ini, titik kejadian kekeringan. Tanpa nomor HP dan data internal. |
| Peta internal (`/peta`) | Ditambah titik Sub-D.I. dengan laporan terakhir, foto, dan tombol WhatsApp petugas. Isinya dibatasi sesuai wilayah pengguna. |
| Laporan harian | Form untuk HP: debit intake & TMA (wajib), kondisi saluran, bukaan pintu, fase tanaman, luas tanam/hijau/kekeringan/puso/panen, maks. 5 foto (dikompres di browser lalu di server), GPS otomatis, draf tersimpan saat offline. |
| Verifikasi | Koordinator Satgas, Manager D.I., dan Admin OP menyetujui laporan atau mengembalikannya untuk perbaikan. Laporan yang sudah disetujui terkunci. |
| Kejadian kekeringan | 95 lokasi dari rekapan, laporan kejadian baru, status penanganan, dan luas tertangani. |
| Dashboard | Strip status D.I., Ha berisiko, kepatuhan lapor per Satgas, luas lahan, kejadian, dan 10 Sub-D.I. paling kritis. |
| Master data | D.I., Sub-D.I. (atur titik di peta), jadwal MT resmi Bidang OP (timeline), P3A, pengguna dan penugasan Sub-D.I. |
| Lainnya | Direktori kontak petugas + WhatsApp, ekspor CSV, log aktivitas, PWA (bisa dipasang di layar utama HP). |

Status tahap 1 dihitung dari laporan 3 hari terakhir (`app/Support/StatusKondisi.php`):

- kondisi saluran penuh/normal → **Aman**, kurang → **Waspada**, kering → **Siaga**
- tanaman kekeringan ≥ 10% luas tanam → minimal Waspada; ≥ 25% → minimal Siaga; ≥ 50% atau ada puso → **Kritis**
- status D.I. = status Sub-D.I. terberat

Tahap 2 akan menggantinya dengan Indeks Risiko Kekeringan berbasis neraca air (debit vs kebutuhan per fase).

## Peran pengguna

| Role | Akses |
| --- | --- |
| `super_admin` | Semua, termasuk log aktivitas |
| `admin_op` | Semua data, kelola master & pengguna (tidak bisa membuat super admin) |
| `pimpinan` | Lihat semua, tanpa mengubah data |
| `koordinator` | Wilayah Satgas-nya: lihat, verifikasi, tangani kejadian |
| `manager` | D.I.-nya: lihat, lapor, verifikasi |
| `petugas`, `pob` | Lapor untuk Sub-D.I. yang ditugaskan; tambah, ubah, hapus laporannya sendiri sebelum disetujui |

Login memakai username **atau nomor HP** (format 08…, +62…, atau dengan spasi/tanda hubung).

## Instalasi lokal (Laravel Herd, Windows)

```powershell
cd E:\Herd\iris-bbwsnt2
composer dump-autoload
# buat database kosong "iris_bbwsnt2" (utf8mb4) di MySQL, lalu sesuaikan DB_* di .env
php artisan migrate --seed
npm install       # hanya bila ingin mengubah tampilan; hasil build sudah ada di public/build
```

`--seed` mengisi data awal dari file Excel dan Peta IGT (`database/seeders/data/master.json`, `public/geojson/*.geojson`):
5 Satgas, 26 D.I., 179 Sub-D.I., 173 bangunan utama, 50 P3A, 95 kejadian, dan sekitar 620 akun (1 admin + petugas dari "Daftar Kontak Petugas Irigasi").

Setiap akun mendapat **password acak** yang ditulis ke `storage/app/akun-awal-YYYYMMDD-HHMMSS.csv`. Bagikan ke petugas (mis. lewat WhatsApp), lalu **hapus file itu**. Semua akun wajib mengganti password saat login pertama. Isi `IRIS_ADMIN_PASSWORD` di `.env` sebelum seeding bila ingin menentukan password admin sendiri.

Data contoh untuk demo (jangan di produksi):

```powershell
php artisan db:seed --class=DemoLaporanSeeder
# hapus lagi:
php artisan tinker --execute="App\Models\LaporanHarian::where('kendala','like','[DEMO]%')->delete(); App\Models\JadwalTanam::where('sumber','[DEMO]')->delete();"
```

## Deploy ke shared hosting (cPanel)

1. **Versi PHP.** `composer.lock` saat ini dibuat dengan PHP 8.4 (paket Symfony 8). Bila hosting masih PHP 8.2/8.3, jalankan di laptop lalu commit:
   ```powershell
   composer config platform.php 8.2.0
   composer update
   ```
2. **MySQL/MariaDB.** Collation default sudah `utf8mb4_unicode_ci` sehingga cocok untuk MySQL 5.7/8 dan MariaDB.
3. **Upload.** Lakukan `git pull` ke folder di luar `public_html` (mis. `~/iris`), lalu `composer install --no-dev --optimize-autoloader`. Arahkan domain/subdomain ke `~/iris/public`, atau symlink `public_html` → `~/iris/public`.
4. **.env produksi.** Atur `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `APP_TIMEZONE=Asia/Makassar`, dan `DB_*`. Lalu jalankan:
   ```bash
   php artisan key:generate   # hanya sekali, bila APP_KEY kosong
   php artisan migrate --force --seed
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
5. **Foto lapangan** disimpan di `public/uploads/` (tidak perlu `storage:link`). Pastikan folder bisa ditulis web server. Folder ini ada di `.gitignore`; backup terpisah.
6. **Aset tampilan** (`public/build/`) ikut di-commit, jadi server tidak butuh Node.js. Setelah mengubah tampilan, jalankan `npm run build` di laptop lalu commit.
7. **HTTPS wajib** agar GPS di HP dan PWA (service worker) berfungsi.

## Data sumber

- `Kompilasi_Template_Kekeringan_Sawah_BBWS_NT_II.xlsx` → `database/seeders/data/master.json`
- `D:\OP\BBWS NT2 _ Peta IGT` (BAKU_NTT, Fungsional_BWS_NTII, Potensial_BWS_NTII) → `public/geojson/{baku,fungsional,potensial}.geojson`
  - diproyeksikan ulang dari World Cylindrical Equal Area ke WGS84, digabung per D.I., disederhanakan ±15 m
  - luas dihitung dari geometri (kolom `L_Baku` di layer Fungsional terulang per potongan poligon, jadi tidak dipakai)
  - D.I. Pota, Tanah Raing (fungsional), dan Netemnanu (baku/potensial) belum terhubung ke 26 D.I. Permen

Kabupaten yang Satgas-nya belum pasti dari data (Timor Tengah Selatan, Ngada, Nagekeo) dibiarkan kosong. Atur lewat database atau tambahkan ke `WilayahSatgasSeeder`.

## Tes

```powershell
php artisan test
```

Tes memakai SQLite in-memory dan mencakup alur lapor, verifikasi, batas akses per wilayah, privasi peta publik, jadwal tanam, kejadian, dan pengguna.
