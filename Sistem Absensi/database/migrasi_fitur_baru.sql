CREATE TABLE IF NOT EXISTS pesan_bantuan (
  id_pesan INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_mahasiswa INT UNSIGNED NOT NULL,
  pengirim ENUM('mahasiswa','admin') NOT NULL,
  isi TEXT NOT NULL,
  dibaca TINYINT(1) NOT NULL DEFAULT 0,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pesan),
  KEY idx_pesan_mahasiswa_waktu (id_mahasiswa, dibuat_pada),
  KEY idx_pesan_mahasiswa_baca (id_mahasiswa, pengirim, dibaca),
  CONSTRAINT fk_pesan_bantuan_mahasiswa FOREIGN KEY (id_mahasiswa)
    REFERENCES mahasiswa (id_mahasiswa) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO peserta_kelas (id_kelas, id_mahasiswa)
SELECT k.id_kelas, m.id_mahasiswa
FROM mahasiswa m
INNER JOIN mata_kuliah mk ON mk.semester = m.semester
INNER JOIN kelas_kuliah k
  ON k.id_mata_kuliah = mk.id_mata_kuliah AND k.nama_kelas = 'A'
INNER JOIN tahun_akademik ta
  ON ta.id_tahun_akademik = k.id_tahun_akademik AND ta.aktif = 1;
