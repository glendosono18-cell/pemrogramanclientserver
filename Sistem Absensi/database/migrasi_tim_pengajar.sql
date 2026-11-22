CREATE TABLE kelas_pengajar (
  id_pengajar INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_kelas INT UNSIGNED NOT NULL,
  id_dosen INT UNSIGNED NOT NULL,
  urutan TINYINT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_kelas_dosen (id_kelas, id_dosen),
  KEY idx_pengajar_dosen (id_dosen),
  CONSTRAINT fk_pengajar_kelas FOREIGN KEY (id_kelas) REFERENCES kelas_kuliah (id_kelas) ON DELETE CASCADE,
  CONSTRAINT fk_pengajar_dosen FOREIGN KEY (id_dosen) REFERENCES dosen (id_dosen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO kelas_pengajar (id_kelas, id_dosen, urutan)
SELECT id_kelas, id_dosen, 1 FROM kelas_kuliah;

ALTER TABLE kelas_kuliah
  ADD UNIQUE KEY uq_kelas_mk_tahun (id_mata_kuliah, id_tahun_akademik, nama_kelas);

ALTER TABLE kelas_kuliah DROP FOREIGN KEY fk_kelas_dosen;

ALTER TABLE kelas_kuliah DROP INDEX uq_kelas_jadwal;

ALTER TABLE kelas_kuliah DROP COLUMN id_dosen;

ALTER TABLE mata_kuliah
  ADD COLUMN semester TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER sks;
