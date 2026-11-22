ALTER TABLE presensi_pertemuan
  ADD COLUMN tanggal_pertemuan DATE NULL AFTER waktu_buka;

UPDATE presensi_pertemuan
SET tanggal_pertemuan = DATE(waktu_buka)
WHERE tanggal_pertemuan IS NULL;

ALTER TABLE presensi_pertemuan
  MODIFY COLUMN tanggal_pertemuan DATE NOT NULL,
  ADD KEY idx_sesi_kelas_tanggal (id_kelas, tanggal_pertemuan);
