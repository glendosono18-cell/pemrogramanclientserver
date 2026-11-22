<?php
require_once __DIR__ . '/auth.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    apiResponse('error', ['status' => 'error', 'message' => 'Metode tidak diizinkan'], 405);
}

$user = requireRole('mahasiswa');

try {
    require_once __DIR__ . '/../Koneksi/koneksi.php';

    $studentStatement = mysqli_prepare(
        $conn,
        'SELECT id_mahasiswa, nim, nama_lengkap, prodi, semester,
                provinsi, kota, kecamatan, kelurahan, foto_profil
         FROM mahasiswa WHERE id_login=? LIMIT 1'
    );
    mysqli_stmt_bind_param($studentStatement, 'i', $user['id']);
    mysqli_stmt_execute($studentStatement);
    mysqli_stmt_bind_result(
        $studentStatement,
        $studentId,
        $nim,
        $nama,
        $prodi,
        $semester,
        $provinsi,
        $kota,
        $kecamatan,
        $kelurahan,
        $fotoProfil
    );
    if (!mysqli_stmt_fetch($studentStatement)) {
        mysqli_stmt_close($studentStatement);
        apiResponse('error', ['status' => 'error', 'message' => 'Data mahasiswa tidak ditemukan'], 404);
    }
    mysqli_stmt_close($studentStatement);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_FILES['foto_profil']) || !is_array($_FILES['foto_profil'])) {
            apiResponse('error', ['status' => 'error', 'message' => 'File foto profil wajib dipilih'], 400);
        }
        $file = $_FILES['foto_profil'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            apiResponse('error', ['status' => 'error', 'message' => 'Gagal mengunggah foto profil'], 400);
        }
        if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > 3 * 1024 * 1024) {
            apiResponse('error', ['status' => 'error', 'message' => 'Ukuran foto profil maksimal 3 MB'], 400);
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($allowedMimes[$mime])) {
            apiResponse('error', ['status' => 'error', 'message' => 'Format foto harus JPG, PNG, atau WEBP'], 400);
        }
        $uploadDir = dirname(__DIR__) . '/uploads/profil';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            apiResponse('error', ['status' => 'error', 'message' => 'Folder unggahan tidak dapat dibuat'], 500);
        }
        $fileName = 'mhs_' . $studentId . '_' . bin2hex(random_bytes(8)) . '.' . $allowedMimes[$mime];
        $destPath = $uploadDir . '/' . $fileName;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            apiResponse('error', ['status' => 'error', 'message' => 'Foto profil tidak dapat disimpan'], 500);
        }
        $relativePath = 'uploads/profil/' . $fileName;
        $updateStmt = mysqli_prepare($conn, 'UPDATE mahasiswa SET foto_profil=? WHERE id_mahasiswa=?');
        mysqli_stmt_bind_param($updateStmt, 'si', $relativePath, $studentId);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);

        if (is_string($fotoProfil) && strpos($fotoProfil, 'uploads/profil/') === 0) {
            $oldFile = dirname(__DIR__) . '/' . $fotoProfil;
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }
        apiResponse('success', [
            'status' => 'success',
            'message' => 'Foto profil berhasil diperbarui',
            'foto_profil' => $relativePath,
        ]);
    }

    $bagian = $_GET['bagian'] ?? 'ringkasan';

    if ($bagian === 'kelas') {
        $years = academicYearOptions($conn);
        $yearId = resolveAcademicYearId($conn, $_GET['id_tahun_akademik'] ?? null);
        $yearIsActive = false;
        foreach ($years as $year) {
            if ($year['id_tahun_akademik'] === $yearId) {
                $yearIsActive = $year['aktif'] === 1;
                break;
            }
        }

        $statement = mysqli_prepare(
            $conn,
            'SELECT k.id_kelas, mk.kode, mk.nama, mk.sks, mk.semester,
                    k.nama_kelas, k.hari, k.jam_mulai, k.jam_selesai, k.ruangan,
                    (SELECT COUNT(*)
                     FROM presensi_mahasiswa pm
                     INNER JOIN presensi_pertemuan p ON p.id_pertemuan=pm.id_pertemuan
                     WHERE p.id_kelas=k.id_kelas AND p.status=\'Ditutup\'
                       AND pm.id_mahasiswa=?
                       AND pm.status IN (\'Izin\',\'Sakit\',\'Alpa\'))
             FROM peserta_kelas pk
             INNER JOIN kelas_kuliah k ON k.id_kelas=pk.id_kelas
             INNER JOIN mata_kuliah mk ON mk.id_mata_kuliah=k.id_mata_kuliah
             WHERE pk.id_mahasiswa=? AND k.id_tahun_akademik=?
               AND k.nama_kelas=\'A\' AND mk.semester=?
             ORDER BY FIELD(k.hari,\'Senin\',\'Selasa\',\'Rabu\',\'Kamis\',\'Jumat\',\'Sabtu\',\'Minggu\'),
                      k.jam_mulai'
        );
        mysqli_stmt_bind_param($statement, 'iiii', $studentId, $studentId, $yearId, $semester);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result(
            $statement,
            $classId,
            $kode,
            $namaMataKuliah,
            $sks,
            $courseSemester,
            $namaKelas,
            $hari,
            $jamMulai,
            $jamSelesai,
            $ruangan,
            $absenceCount
        );
        $rows = [];
        while (mysqli_stmt_fetch($statement)) {
            $rows[] = [
                'id_kelas' => (int) $classId,
                'kode' => $kode,
                'nama_mata_kuliah' => $namaMataKuliah,
                'sks' => (int) $sks,
                'semester' => (int) $courseSemester,
                'nama_kelas' => $namaKelas,
                'hari' => $hari,
                'jam_mulai' => substr($jamMulai, 0, 5),
                'jam_selesai' => substr($jamSelesai, 0, 5),
                'ruangan' => $ruangan,
                'ketidakhadiran' => (int) $absenceCount,
                'tim_pengajar' => [],
                'pertemuan' => [],
                'sedang_berlangsung' => false,
            ];
        }
        mysqli_stmt_close($statement);

        $classIds = array_column($rows, 'id_kelas');
        if ($classIds) {
            $teamStatement = mysqli_prepare(
                $conn,
                'SELECT kp.id_kelas, d.nama_lengkap
                 FROM kelas_pengajar kp
                 INNER JOIN dosen d ON d.id_dosen=kp.id_dosen
                 INNER JOIN kelas_kuliah k ON k.id_kelas=kp.id_kelas
                 INNER JOIN peserta_kelas pk ON pk.id_kelas=k.id_kelas
                 WHERE pk.id_mahasiswa=? AND k.id_tahun_akademik=?
                 ORDER BY kp.id_kelas, kp.urutan'
            );
            mysqli_stmt_bind_param($teamStatement, 'ii', $studentId, $yearId);
            mysqli_stmt_execute($teamStatement);
            mysqli_stmt_bind_result($teamStatement, $teamClassId, $lecturerName);
            $teams = [];
            while (mysqli_stmt_fetch($teamStatement)) {
                $teams[(int) $teamClassId][] = $lecturerName;
            }
            mysqli_stmt_close($teamStatement);

            $placeholders = implode(',', array_fill(0, count($classIds), '?'));
            $meetingStatement = mysqli_prepare(
                $conn,
                "SELECT id_kelas, waktu_buka, status, mode_verifikasi
                 FROM presensi_pertemuan WHERE id_kelas IN ($placeholders)
                 ORDER BY waktu_buka DESC"
            );
            $meetingTypes = str_repeat('i', count($classIds));
            mysqli_stmt_bind_param($meetingStatement, $meetingTypes, ...$classIds);
            mysqli_stmt_execute($meetingStatement);
            mysqli_stmt_bind_result($meetingStatement, $meetingClassId, $openedAt, $meetingStatus, $mode);
            $meetings = [];
            while (mysqli_stmt_fetch($meetingStatement)) {
                $meetings[(int) $meetingClassId][] = [
                    'waktu_buka' => date('d-m-Y H:i', strtotime($openedAt)),
                    'status' => $meetingStatus,
                    'metode' => $mode,
                ];
            }
            mysqli_stmt_close($meetingStatement);

            $dayByNumber = [
                1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
                5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
            ];
            $today = $dayByNumber[(int) date('N')];
            $now = date('H:i');
            foreach ($rows as &$row) {
                $row['tim_pengajar'] = $teams[$row['id_kelas']] ?? [];
                $row['pertemuan'] = $meetings[$row['id_kelas']] ?? [];
                $row['sedang_berlangsung'] = $yearIsActive && $row['hari'] === $today
                    && $now >= $row['jam_mulai'] && $now <= $row['jam_selesai'];
            }
            unset($row);
        }

        apiResponse('success', [
            'status' => 'success',
            'kelas' => $rows,
            'tahun_akademik' => $years,
            'id_tahun_akademik' => $yearId,
        ]);
    }

    if ($bagian === 'riwayat') {
        $years = academicYearOptions($conn);
        $yearId = resolveAcademicYearId($conn, $_GET['id_tahun_akademik'] ?? null);
        $statement = mysqli_prepare(
            $conn,
            'SELECT k.id_kelas, mk.kode, mk.nama, k.nama_kelas
             FROM peserta_kelas pk
             INNER JOIN kelas_kuliah k ON k.id_kelas=pk.id_kelas
             INNER JOIN mata_kuliah mk ON mk.id_mata_kuliah=k.id_mata_kuliah
             WHERE pk.id_mahasiswa=? AND k.id_tahun_akademik=?
               AND k.nama_kelas=\'A\' AND mk.semester=?
             ORDER BY mk.nama'
        );
        mysqli_stmt_bind_param($statement, 'iii', $studentId, $yearId, $semester);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $classIdRow, $courseCode, $courseName, $className);
        $classes = [];
        while (mysqli_stmt_fetch($statement)) {
            $classes[] = [
                'id_kelas' => (int) $classIdRow,
                'kode' => $courseCode,
                'nama' => $courseName,
                'nama_kelas' => $className,
            ];
        }
        mysqli_stmt_close($statement);

        $classId = filter_var($_GET['id_kelas'] ?? null, FILTER_VALIDATE_INT);
        if ($classId === false || $classId < 1) {
            apiResponse('success', [
                'status' => 'success',
                'tahun_akademik' => $years,
                'id_tahun_akademik' => $yearId,
                'kelas' => $classes,
                'riwayat' => [],
                'ringkasan' => null,
            ]);
        }

        $selectedClass = null;
        foreach ($classes as $class) {
            if ($class['id_kelas'] === (int) $classId) {
                $selectedClass = $class;
                break;
            }
        }
        if ($selectedClass === null) {
            apiResponse('error', ['status' => 'error', 'message' => 'Kelas tidak terdaftar pada akun Anda'], 403);
        }

        $statement = mysqli_prepare(
            $conn,
            'SELECT p.pertemuan_ke, p.waktu_buka, pm.status, pm.metode, pm.waktu_scan
             FROM presensi_pertemuan p
             LEFT JOIN presensi_mahasiswa pm
               ON pm.id_pertemuan=p.id_pertemuan AND pm.id_mahasiswa=?
             WHERE p.id_kelas=? AND p.status=\'Ditutup\'
             ORDER BY p.waktu_buka DESC'
        );
        mysqli_stmt_bind_param($statement, 'ii', $studentId, $classId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $meetingNumber, $openedAt, $attendanceStatus, $method, $scanAt);
        $rows = [];
        $summary = [
            'Hadir' => 0, 'Terlambat' => 0, 'Izin' => 0, 'Sakit' => 0, 'Alpa' => 0,
        ];
        while (mysqli_stmt_fetch($statement)) {
            $attendanceStatus = $attendanceStatus ?? 'Alpa';
            if (isset($summary[$attendanceStatus])) {
                $summary[$attendanceStatus]++;
            }
            $rows[] = [
                'pertemuan_ke' => (int) $meetingNumber,
                'tanggal_jam' => date('d-m-Y H:i', strtotime($openedAt)),
                'status' => $attendanceStatus,
                'metode' => $method,
                'waktu_scan' => $scanAt ? date('d-m-Y H:i', strtotime($scanAt)) : null,
            ];
        }
        mysqli_stmt_close($statement);
        $totalClosed = array_sum($summary);
        apiResponse('success', [
            'status' => 'success',
            'tahun_akademik' => $years,
            'id_tahun_akademik' => $yearId,
            'kelas' => $classes,
            'riwayat' => $rows,
            'ringkasan' => [
                'hadir' => $summary['Hadir'],
                'terlambat' => $summary['Terlambat'],
                'izin' => $summary['Izin'],
                'sakit' => $summary['Sakit'],
                'alpa' => $summary['Alpa'],
                'total_pertemuan' => $totalClosed,
                'persen' => $totalClosed > 0
                    ? round((($summary['Hadir'] + $summary['Terlambat']) / $totalClosed) * 100, 1)
                    : 0,
            ],
        ]);
    }

    if ($bagian === 'wajah') {
        $statement = mysqli_prepare(
            $conn,
            'SELECT pose, dibuat_pada, descriptor FROM wajah_mahasiswa
             WHERE id_mahasiswa=? ORDER BY FIELD(pose,\'depan\',\'kiri\',\'kanan\')'
        );
        mysqli_stmt_bind_param($statement, 'i', $studentId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $pose, $dibuatPada, $descriptorJson);
        $poses = [];
        while (mysqli_stmt_fetch($statement)) {
            $values = json_decode($descriptorJson, true);
            $bars = [];
            if (is_array($values) && count($values) === 128) {
                foreach (array_chunk($values, 8) as $chunk) {
                    $average = array_sum(array_map(static function ($value) {
                        return abs((float) $value);
                    }, $chunk)) / count($chunk);
                    $bars[] = round(min(1, $average), 3);
                }
            }
            $poses[] = [
                'pose' => $pose,
                'status' => 'Terekam',
                'dibuat_pada' => date('d-m-Y H:i', strtotime($dibuatPada)),
                'visualisasi' => $bars,
            ];
        }
        mysqli_stmt_close($statement);
        apiResponse('success', [
            'status' => 'success',
            'poses' => $poses,
            'jumlah_pose' => count($poses),
        ]);
    }

    if ($bagian === 'statistik') {
        $years = academicYearOptions($conn);
        $yearId = resolveAcademicYearId($conn, $_GET['id_tahun_akademik'] ?? null);
        $statement = mysqli_prepare(
            $conn,
            'SELECT k.id_kelas, mk.kode, mk.nama,
                    COUNT(p.id_pertemuan),
                    SUM(CASE WHEN pm.status=\'Hadir\' THEN 1 ELSE 0 END),
                    SUM(CASE WHEN pm.status=\'Terlambat\' THEN 1 ELSE 0 END),
                    SUM(CASE WHEN pm.status=\'Izin\' THEN 1 ELSE 0 END),
                    SUM(CASE WHEN pm.status=\'Sakit\' THEN 1 ELSE 0 END),
                    SUM(CASE WHEN pm.status=\'Alpa\' THEN 1 ELSE 0 END)
             FROM peserta_kelas pk
             INNER JOIN kelas_kuliah k ON k.id_kelas=pk.id_kelas
             INNER JOIN mata_kuliah mk ON mk.id_mata_kuliah=k.id_mata_kuliah
             LEFT JOIN presensi_pertemuan p
               ON p.id_kelas=k.id_kelas AND p.status=\'Ditutup\'
             LEFT JOIN presensi_mahasiswa pm
               ON pm.id_pertemuan=p.id_pertemuan AND pm.id_mahasiswa=pk.id_mahasiswa
             WHERE pk.id_mahasiswa=? AND k.id_tahun_akademik=?
               AND k.nama_kelas=\'A\' AND mk.semester=?
             GROUP BY k.id_kelas, mk.kode, mk.nama
             ORDER BY mk.nama'
        );
        mysqli_stmt_bind_param($statement, 'iii', $studentId, $yearId, $semester);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result(
            $statement, $classIdRow, $courseCode, $courseName, $totalMeetings,
            $attended, $late, $excused, $sick, $absent
        );
        $courses = [];
        $totals = [
            'hadir' => 0, 'terlambat' => 0, 'izin' => 0, 'sakit' => 0,
            'alpa' => 0, 'total_pertemuan' => 0,
        ];
        while (mysqli_stmt_fetch($statement)) {
            $totalMeetings = (int) $totalMeetings;
            $attended = (int) $attended;
            $late = (int) $late;
            $excused = (int) $excused;
            $sick = (int) $sick;
            $absent = (int) $absent;
            $courses[] = [
                'id_kelas' => (int) $classIdRow,
                'kode' => $courseCode,
                'nama' => $courseName,
                'hadir' => $attended,
                'terlambat' => $late,
                'izin' => $excused,
                'sakit' => $sick,
                'alpa' => $absent,
                'total_pertemuan' => $totalMeetings,
                'persen' => $totalMeetings > 0
                    ? round((($attended + $late) / $totalMeetings) * 100, 1)
                    : 0,
            ];
            $totals['hadir'] += $attended;
            $totals['terlambat'] += $late;
            $totals['izin'] += $excused;
            $totals['sakit'] += $sick;
            $totals['alpa'] += $absent;
            $totals['total_pertemuan'] += $totalMeetings;
        }
        mysqli_stmt_close($statement);
        $totals['persen'] = $totals['total_pertemuan'] > 0
            ? round((($totals['hadir'] + $totals['terlambat']) / $totals['total_pertemuan']) * 100, 1)
            : 0;
        apiResponse('success', [
            'status' => 'success',
            'tahun_akademik' => $years,
            'id_tahun_akademik' => $yearId,
            'statistik' => $totals,
            'mata_kuliah' => $courses,
        ]);
    }

    $statement = mysqli_prepare(
        $conn,
        'SELECT COUNT(*),
                SUM(CASE WHEN pm.status IN (\'Hadir\',\'Terlambat\') THEN 1 ELSE 0 END)
         FROM presensi_mahasiswa pm
         WHERE pm.id_mahasiswa=?'
    );
    mysqli_stmt_bind_param($statement, 'i', $studentId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $totalPresensi, $jumlahHadie);
    mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);

    $statement = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) FROM peserta_kelas WHERE id_mahasiswa=?'
    );
    mysqli_stmt_bind_param($statement, 'i', $studentId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $jumlahKelas);
    mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);

    $statement = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) FROM wajah_mahasiswa WHERE id_mahasiswa=?'
    );
    mysqli_stmt_bind_param($statement, 'i', $studentId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $jumlahWajah);
    mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);

    $totalPresensi = (int) $totalPresensi;
    $jumlahHadie = (int) ($jumlahHadie ?? 0);
    apiResponse('success', [
        'status' => 'success',
        'profil' => [
            'nim' => $nim,
            'nama_lengkap' => $nama,
            'prodi' => $prodi,
            'semester' => (int) $semester,
            'provinsi' => $provinsi,
            'kota' => $kota,
            'kecamatan' => $kecamatan,
            'kelurahan' => $kelurahan,
            'foto_profil' => $fotoProfil,
        ],
        'ringkasan' => [
            'jumlah_kelas' => (int) $jumlahKelas,
            'total_presensi' => $totalPresensi,
            'jumlah_hadir' => $jumlahHadie,
            'persentase_hadir' => $totalPresensi > 0
                ? round(($jumlahHadie / $totalPresensi) * 100, 1)
                : 0,
            'jumlah_wajah' => (int) $jumlahWajah,
        ],
    ]);
} catch (Throwable $error) {
    error_log('Mahasiswa API error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'Data mahasiswa tidak dapat dimuat'], 500);
}
