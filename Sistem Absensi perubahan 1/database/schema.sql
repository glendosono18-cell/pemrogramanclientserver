CREATE DATABASE IF NOT EXISTS absensi
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE absensi;

CREATE TABLE IF NOT EXISTS login (
  id_login INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(100) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('mahasiswa','dosen','admin') NOT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_login),
  UNIQUE KEY uq_login_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin (
  id_admin INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_login INT UNSIGNED NOT NULL,
  nama_lengkap VARCHAR(150) NOT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_admin),
  UNIQUE KEY uq_admin_login (id_login),
  CONSTRAINT fk_admin_login FOREIGN KEY (id_login)
    REFERENCES login (id_login) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dosen (
  id_dosen INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_login INT UNSIGNED NOT NULL,
  nidn VARCHAR(30) NOT NULL,
  nama_lengkap VARCHAR(150) NOT NULL,
  prodi VARCHAR(100) NOT NULL,
  provinsi VARCHAR(100) DEFAULT NULL,
  kota VARCHAR(100) DEFAULT NULL,
  kecamatan VARCHAR(100) DEFAULT NULL,
  kelurahan VARCHAR(100) DEFAULT NULL,
  status_akun ENUM('Menunggu','Aktif','Ditolak') NOT NULL DEFAULT 'Aktif',
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_dosen),
  UNIQUE KEY uq_dosen_login (id_login),
  UNIQUE KEY uq_dosen_nidn (nidn),
  CONSTRAINT fk_dosen_login FOREIGN KEY (id_login)
    REFERENCES login (id_login) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mahasiswa (
  id_mahasiswa INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_login INT UNSIGNED NOT NULL,
  nim VARCHAR(30) NOT NULL,
  nama_lengkap VARCHAR(150) NOT NULL,
  prodi VARCHAR(100) NOT NULL,
  semester TINYINT UNSIGNED NOT NULL,
  provinsi VARCHAR(100) DEFAULT NULL,
  kota VARCHAR(100) DEFAULT NULL,
  kecamatan VARCHAR(100) DEFAULT NULL,
  kelurahan VARCHAR(100) DEFAULT NULL,
  foto_profil VARCHAR(255) DEFAULT NULL,
  qr_token CHAR(64) NOT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_mahasiswa),
  UNIQUE KEY uq_mahasiswa_login (id_login),
  UNIQUE KEY uq_mahasiswa_nim (nim),
  UNIQUE KEY uq_mahasiswa_qr_token (qr_token),
  CONSTRAINT fk_mahasiswa_login FOREIGN KEY (id_login)
    REFERENCES login (id_login) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pesan_bantuan (
  id_pesan INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_mahasiswa INT UNSIGNED DEFAULT NULL,
  id_dosen INT UNSIGNED DEFAULT NULL,
  pengirim ENUM('mahasiswa','dosen','admin') NOT NULL,
  isi TEXT NOT NULL,
  lampiran_nama VARCHAR(255) DEFAULT NULL,
  lampiran_path VARCHAR(255) DEFAULT NULL,
  lampiran_tipe VARCHAR(100) DEFAULT NULL,
  dibaca TINYINT(1) NOT NULL DEFAULT 0,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pesan),
  KEY idx_pesan_mahasiswa_waktu (id_mahasiswa, dibuat_pada),
  KEY idx_pesan_mahasiswa_baca (id_mahasiswa, pengirim, dibaca),
  KEY idx_pesan_dosen_waktu (id_dosen, dibuat_pada),
  KEY idx_pesan_dosen_baca (id_dosen, pengirim, dibaca),
  CONSTRAINT fk_pesan_bantuan_mahasiswa FOREIGN KEY (id_mahasiswa)
    REFERENCES mahasiswa (id_mahasiswa) ON DELETE CASCADE,
  CONSTRAINT fk_pesan_bantuan_dosen FOREIGN KEY (id_dosen)
    REFERENCES dosen (id_dosen) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mata_kuliah (
  id_mata_kuliah INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode VARCHAR(20) NOT NULL,
  nama VARCHAR(150) NOT NULL,
  sks TINYINT UNSIGNED NOT NULL,
  semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_mata_kuliah),
  UNIQUE KEY uq_mata_kuliah_kode (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pengaturan (
  kunci VARCHAR(100) NOT NULL,
  nilai VARCHAR(255) NOT NULL,
  diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (kunci)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tahun_akademik (
  id_tahun_akademik INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tahun VARCHAR(9) NOT NULL,
  semester ENUM('Ganjil','Genap') NOT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id_tahun_akademik),
  UNIQUE KEY uq_tahun_semester (tahun, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kelas_kuliah (
  id_kelas INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_mata_kuliah INT UNSIGNED NOT NULL,
  id_tahun_akademik INT UNSIGNED NOT NULL,
  nama_kelas VARCHAR(30) NOT NULL,
  hari ENUM('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu') NOT NULL,
  jam_mulai TIME NOT NULL,
  jam_selesai TIME NOT NULL,
  ruangan VARCHAR(50) NOT NULL,
  PRIMARY KEY (id_kelas),
  UNIQUE KEY uq_kelas_mk_tahun (id_mata_kuliah, id_tahun_akademik, nama_kelas),
  KEY fk_kelas_tahun (id_tahun_akademik),
  CONSTRAINT fk_kelas_mata_kuliah FOREIGN KEY (id_mata_kuliah) REFERENCES mata_kuliah (id_mata_kuliah),
  CONSTRAINT fk_kelas_tahun FOREIGN KEY (id_tahun_akademik) REFERENCES tahun_akademik (id_tahun_akademik)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kelas_pengajar (
  id_pengajar INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_kelas INT UNSIGNED NOT NULL,
  id_dosen INT UNSIGNED NOT NULL,
  urutan TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_pengajar),
  UNIQUE KEY uq_kelas_dosen (id_kelas, id_dosen),
  KEY idx_pengajar_dosen (id_dosen),
  CONSTRAINT fk_pengajar_kelas FOREIGN KEY (id_kelas)
    REFERENCES kelas_kuliah (id_kelas) ON DELETE CASCADE,
  CONSTRAINT fk_pengajar_dosen FOREIGN KEY (id_dosen) REFERENCES dosen (id_dosen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS presensi_pertemuan (
  id_pertemuan INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_kelas INT UNSIGNED NOT NULL,
  id_dosen INT UNSIGNED NOT NULL,
  pertemuan_ke TINYINT UNSIGNED NOT NULL,
  waktu_buka DATETIME NOT NULL,
  tanggal_pertemuan DATE NOT NULL,
  waktu_tutup DATETIME DEFAULT NULL,
  status ENUM('Dibuka','Ditutup') NOT NULL DEFAULT 'Dibuka',
  mode_verifikasi ENUM('QR','QR + Wajah') NOT NULL DEFAULT 'QR + Wajah',
  batas_tepat_menit TINYINT UNSIGNED NOT NULL DEFAULT 15,
  ruangan VARCHAR(50) NOT NULL,
  PRIMARY KEY (id_pertemuan),
  KEY idx_sesi_kelas_status (id_kelas, status),
  KEY idx_sesi_kelas_tanggal (id_kelas, tanggal_pertemuan),
  KEY fk_pertemuan_dosen (id_dosen),
  CONSTRAINT fk_pertemuan_dosen FOREIGN KEY (id_dosen) REFERENCES dosen (id_dosen),
  CONSTRAINT fk_pertemuan_kelas FOREIGN KEY (id_kelas) REFERENCES kelas_kuliah (id_kelas)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS peserta_kelas (
  id_peserta INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_kelas INT UNSIGNED NOT NULL,
  id_mahasiswa INT UNSIGNED NOT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_peserta),
  UNIQUE KEY uq_peserta_kelas (id_kelas, id_mahasiswa),
  KEY fk_peserta_mahasiswa (id_mahasiswa),
  CONSTRAINT fk_peserta_kelas FOREIGN KEY (id_kelas)
    REFERENCES kelas_kuliah (id_kelas) ON DELETE CASCADE,
  CONSTRAINT fk_peserta_mahasiswa FOREIGN KEY (id_mahasiswa)
    REFERENCES mahasiswa (id_mahasiswa) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS presensi_mahasiswa (
  id_presensi INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pertemuan INT UNSIGNED NOT NULL,
  id_mahasiswa INT UNSIGNED NOT NULL,
  status ENUM('Hadir','Terlambat','Izin','Sakit','Alpa') NOT NULL DEFAULT 'Alpa',
  metode ENUM('QR','QR + Wajah','Manual') DEFAULT NULL,
  waktu_scan DATETIME DEFAULT NULL,
  skor_wajah DECIMAL(5,4) DEFAULT NULL,
  dikoreksi_oleh INT UNSIGNED DEFAULT NULL,
  diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_presensi),
  UNIQUE KEY uq_presensi_pertemuan_mahasiswa (id_pertemuan, id_mahasiswa),
  KEY fk_presensi_mahasiswa (id_mahasiswa),
  KEY fk_presensi_koreksi (dikoreksi_oleh),
  CONSTRAINT fk_presensi_koreksi FOREIGN KEY (dikoreksi_oleh)
    REFERENCES login (id_login) ON DELETE SET NULL,
  CONSTRAINT fk_presensi_mahasiswa FOREIGN KEY (id_mahasiswa)
    REFERENCES mahasiswa (id_mahasiswa) ON DELETE CASCADE,
  CONSTRAINT fk_presensi_pertemuan FOREIGN KEY (id_pertemuan)
    REFERENCES presensi_pertemuan (id_pertemuan) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS log_scan (
  id_log INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pertemuan INT UNSIGNED DEFAULT NULL,
  id_mahasiswa INT UNSIGNED DEFAULT NULL,
  qr_token VARCHAR(64) DEFAULT NULL,
  hasil ENUM('Berhasil','Gagal') NOT NULL,
  alasan VARCHAR(255) NOT NULL,
  alamat_ip VARCHAR(45) DEFAULT NULL,
  waktu_dibuat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_log),
  KEY idx_log_pertemuan_waktu (id_pertemuan, waktu_dibuat),
  KEY fk_log_mahasiswa (id_mahasiswa),
  CONSTRAINT fk_log_mahasiswa FOREIGN KEY (id_mahasiswa)
    REFERENCES mahasiswa (id_mahasiswa) ON DELETE SET NULL,
  CONSTRAINT fk_log_pertemuan FOREIGN KEY (id_pertemuan)
    REFERENCES presensi_pertemuan (id_pertemuan) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wajah_mahasiswa (
  id_wajah INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_mahasiswa INT UNSIGNED NOT NULL,
  pose ENUM('depan','kiri','kanan') NOT NULL,
  descriptor LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_wajah),
  UNIQUE KEY uq_wajah_mahasiswa_pose (id_mahasiswa, pose),
  CONSTRAINT fk_wajah_mahasiswa FOREIGN KEY (id_mahasiswa)
    REFERENCES mahasiswa (id_mahasiswa) ON DELETE CASCADE,
  CHECK (JSON_VALID(descriptor))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pengumuman (
  id_pengumuman INT UNSIGNED NOT NULL AUTO_INCREMENT,
  judul VARCHAR(150) NOT NULL,
  isi TEXT NOT NULL,
  dibuat_oleh INT UNSIGNED DEFAULT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pengumuman),
  KEY fk_pengumuman_login (dibuat_oleh),
  CONSTRAINT fk_pengumuman_login FOREIGN KEY (dibuat_oleh)
    REFERENCES login (id_login) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
