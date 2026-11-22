# Sistem Absensi

Sistem absensi berbasis web untuk mahasiswa, dosen, dan admin menggunakan QR Code serta verifikasi wajah.
Proyek ini berdiri terpisah dari folder `SIAKAD/`, yang hanya dipakai sebagai referensi tampilan dan pola kode.

## Persyaratan

- XAMPP dengan PHP 8 dan MySQL/MariaDB
- Ekstensi PHP `mysqli`
- Zona waktu `Asia/Makassar`

## Tahap 1: Fondasi dan database

1. Jalankan Apache dan MySQL dari XAMPP.
2. Import `database/schema.sql` melalui phpMyAdmin atau terminal MySQL.
3. Jalankan seed dari terminal:

```powershell
cd "C:\xampp\htdocs\Sistem Absensi"
C:\xampp\php\php.exe database\seed.php
```

4. Seed membuat akun admin berikut:

```text
Username: admin
Kata sandi: Admin123!
```

## Pengaturan awal

- Database: `absensi`
- Charset: `utf8mb4`
- Zona PHP: `Asia/Makassar`
- Zona MySQL: `+08:00`
- Ambang wajah bawaan: `0.5`
- Batas tepat waktu bawaan: `15` menit

## Tahap 2: Autentikasi dan pendaftaran

1. Buka `http://localhost/Sistem%20Absensi/`.
2. Pilih peran untuk menguji login:
   - Admin: `admin` / `Admin123!`
   - Dosen dan mahasiswa dibuat pada tahap berikutnya atau melalui pendaftaran mahasiswa.
3. Buka `daftar.html` untuk mendaftar mahasiswa dan merekam tiga pose wajah.
4. Gunakan NIM atau username beserta kata sandi untuk login sebagai mahasiswa.

Library wajah disimpan lokal di `assets/vendor/face-api.js`; model wajah disimpan lokal di `assets/models/`.

## Tahap 3: Dashboard dan role

Tersedia dashboard terpisah untuk mahasiswa, dosen, dan admin. Navigasi memakai hash dan profil
dipastikan cocok dengan role melalui sesi PHP. Fitur data operasional belum diaktifkan sampai tahap
khususnya dimulai.

## Tahap 4: QR mahasiswa

- Menu **QR Saya** menampilkan identitas mahasiswa dan QR berisi `ABSENSI:<qr_token>`.
- QR dibuat secara lokal oleh `assets/vendor/qrcode.min.js`.
- Tombol unduh menghasilkan berkas PNG bernama `QR-<NIM>.png`.

## Tahap 5: Jadwal, sesi, scan QR, dan wajah

- Menu dosen **Absensi** memuat jadwal berstatus aktif pada hari server WITA.
- Dosen dapat membuka satu sesi per kelas dengan mode `QR` atau `QR + Wajah`.
- Scanner QR dan kamera wajah berjalan dari library lokal di `assets/vendor/`.
- Respons QR + wajah dilanjutkan ke verifikasi descriptor di server.
- Panel **Sudah Absen** diperbarui otomatis setiap 3 detik.

## Tahap 6: Rekap dan koreksi dosen

- Menu **Rekap Presensi** memuat kelas, pertemuan, dan daftar peserta milik dosen.
- Status presensi dapat dikoreksi menjadi Hadir, Terlambat, Izin, Sakit, atau Alpa.
- Koreksi disimpan dengan metode `Manual` dan identitas dosen pelaksana.
- Tombol **Ekspor CSV** mengunduh rekap per pertemuan dalam format CSV UTF-8.

## Tahap 7: Administrasi sistem

- Admin mengelola mahasiswa, dosen, mata kuliah, kelas, peserta, tahun akademik, dan pengumuman.
- Pendaftaran mandiri dosen menunggu persetujuan admin; dosen yang dibuat admin aktif secara langsung.
- Admin dapat mereset token QR atau data wajah mahasiswa.
- Menu **Rekap Absensi** menampilkan rekap lintas kelas dan mendukung ekspor CSV.

Untuk database `absensi` yang sudah ada, jalankan satu kali di phpMyAdmin:

```sql
ALTER TABLE dosen ADD COLUMN status_akun ENUM('Menunggu','Aktif','Ditolak') NOT NULL DEFAULT 'Aktif';
```

`database/seed.php` juga menambahkan kolom tersebut bila belum tersedia. Akun dosen lama
dan akun baru yang dibuat admin akan berstatus `Aktif` secara default.
- Menu **Pengaturan** mengubah ambang wajah dan batas tepat waktu bawaan.

## Tahap 8: Finalisasi

- Seluruh dashboard mahasiswa, dosen, dan admin memuat profil melalui sesi aktif.
- Menu mahasiswa menampilkan jadwal kuliah per hari, tim pengajar, dan ketidakhadiran dari pertemuan yang sudah ditutup.
- Menu dosen menampilkan jadwal mengajar per hari, jumlah peserta, dan seluruh anggota tim.
- Endpoint final dilindungi `requireRole()` dan seluruh interaksi penulisan menggunakan prepared statement.
- Zona waktu PHP dan MySQL menggunakan WITA, `Asia/Makassar` dan `+08:00`.

## Jadwal 2026/2027 Ganjil dan tim pengajar

Untuk database yang sudah berisi data, jalankan migrasi **satu kali** sebelum seed:

1. Import `database/migrasi_tim_pengajar.sql` melalui phpMyAdmin pada database `absensi`, atau dari PowerShell:

```powershell
cd "C:\xampp\htdocs\Sistem Absensi"
Get-Content -Raw database\migrasi_tim_pengajar.sql | C:\xampp\mysql\bin\mysql.exe -u root absensi
```

2. Jalankan seed melalui CLI; aman dijalankan kembali:

```powershell
C:\xampp\php\php.exe database\seed_jadwal.php
```

Jika database baru dibuat dari `database/schema.sql`, struktur migrasi sudah tersedia, jadi jalankan `seed_jadwal.php` saja.

Seed membuat 11 akun dosen untuk jadwal dengan kata sandi pengujian `Glen181106` (password tersimpan dalam bentuk hash). NIDN berurutan dari `002` sampai `012`; NIDN `001` milik dosen lama tetap dipertahankan.

### Hasil pengujian

- Migrasi berhasil diterapkan pada database lokal. Seed dijalankan dua kali: putaran pertama membuat satu tahun akademik, 11 dosen, 10 mata kuliah, 10 kelas, 20 relasi pengajar, dan mendaftarkan 8 enrolmen semester-3; putaran kedua tidak membuat data duplikat.
- API pendaftaran dan jadwal mengembalikan 8 kelas untuk mahasiswa semester 3 dan 2 kelas untuk semester 5.
- API admin menampilkan 10 kelas dengan dua dosen per kelas. Perubahan tim ke satu dosen dan pemulihan ke dua dosen berhasil.
- Login Ikhsan menampilkan dua jadwal. Dosen anggota tim kedua dapat membaca dan menutup sesi yang dibuka anggota pertama; dosen di luar tim mendapat HTTP 403 untuk scan, rekap kelas, dan menutup sesi.
- Scan QR + wajah berhasil, rekap menampilkan peserta, dan ketidakhadiran berubah dari 0 menjadi 1 setelah sesi berisi status Alpa ditutup. Akun mahasiswa dan sesi sementara yang dipakai dalam tes dibersihkan.
- Seluruh file PHP yang berubah lulus `php -l`. Pemeriksaan visual browser dan pemeriksaan sintaks JavaScript otomatis tidak tersedia pada lingkungan ini karena Node.js dan browser Chromium untuk Playwright tidak terpasang.
