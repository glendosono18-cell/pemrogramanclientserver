<?php
require_once __DIR__ . '/auth.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    apiResponse('error', ['status' => 'error', 'message' => 'Metode tidak diizinkan'], 405);
}

$user = requireRole('dosen');

try {
    require_once __DIR__ . '/../Koneksi/koneksi.php';
    requireActiveDosen($conn, $user);
    $statement = mysqli_prepare(
        $conn,
        'SELECT id_dosen FROM dosen WHERE id_login = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 'i', $user['id']);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $lecturerId);
    if (!mysqli_stmt_fetch($statement)) {
        mysqli_stmt_close($statement);
        apiResponse('error', ['status' => 'error', 'message' => 'Data dosen tidak ditemukan'], 404);
    }
    mysqli_stmt_close($statement);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $section = $_GET['bagian'] ?? 'hari_ini';
        if ($section === 'sesi_terbaru') {
            $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Makassar'));
            $cutoff = $today->modify('-4 days')->format('Y-m-d');
            $todayValue = $today->format('Y-m-d');
            $statement = mysqli_prepare(
                $conn,
                'SELECT p.id_pertemuan, p.id_kelas, p.pertemuan_ke,
                        p.tanggal_pertemuan, p.waktu_buka, p.status,
                        k.id_tahun_akademik, k.id_mata_kuliah,
                        mk.kode, mk.nama, k.nama_kelas,
                        SUM(CASE WHEN pm.status IN (\'Hadir\',\'Terlambat\') THEN 1 ELSE 0 END),
                        COUNT(pm.id_presensi)
                 FROM presensi_pertemuan p
                 INNER JOIN kelas_kuliah k ON k.id_kelas=p.id_kelas
                 INNER JOIN mata_kuliah mk ON mk.id_mata_kuliah=k.id_mata_kuliah
                 INNER JOIN kelas_pengajar kp ON kp.id_kelas=k.id_kelas
                 LEFT JOIN presensi_mahasiswa pm ON pm.id_pertemuan=p.id_pertemuan
                 WHERE kp.id_dosen=?
                   AND p.tanggal_pertemuan BETWEEN ? AND ?
                 GROUP BY p.id_pertemuan, p.id_kelas, p.pertemuan_ke,
                          p.tanggal_pertemuan, p.waktu_buka, p.status,
                          k.id_tahun_akademik, k.id_mata_kuliah,
                          mk.kode, mk.nama, k.nama_kelas
                 ORDER BY p.tanggal_pertemuan DESC, p.waktu_buka DESC'
            );
            mysqli_stmt_bind_param($statement, 'iss', $lecturerId, $cutoff, $todayValue);
            mysqli_stmt_execute($statement);
            mysqli_stmt_bind_result(
                $statement, $meetingId, $classId, $meetingNumber, $meetingDate,
                $openedAt, $meetingStatus, $classYearId, $courseId,
                $courseCode, $courseName, $className,
                $presentCount, $participantCount
            );
            $meetings = [];
            while (mysqli_stmt_fetch($statement)) {
                $meetings[] = [
                    'id_pertemuan' => (int) $meetingId,
                    'id_kelas' => (int) $classId,
                    'id_tahun_akademik' => (int) $classYearId,
                    'id_mata_kuliah' => (int) $courseId,
                    'pertemuan_ke' => (int) $meetingNumber,
                    'tanggal_pertemuan' => $meetingDate,
                    'tanggal_jam' => date('d-m-Y', strtotime($meetingDate))
                        . ' ' . date('H:i', strtotime($openedAt)),
                    'status' => $meetingStatus,
                    'kode' => $courseCode,
                    'nama_mata_kuliah' => $courseName,
                    'nama_kelas' => $className,
                    'jumlah_hadir' => (int) $presentCount,
                    'total_peserta' => (int) $participantCount,
                ];
            }
            mysqli_stmt_close($statement);
            apiResponse('success', [
                'status' => 'success',
                'sesi' => $meetings,
            ]);
        }

        if ($section === 'tanggal') {
            $classId = filter_var($_GET['id_kelas'] ?? null, FILTER_VALIDATE_INT);
            if ($classId === false || $classId < 1) {
                apiResponse('error', ['status' => 'error', 'message' => 'Kelas tidak valid'], 400);
            }

            $statement = mysqli_prepare(
                $conn,
                'SELECT k.hari, k.nama_kelas, mk.kode, mk.nama,
                        EXISTS (
                          SELECT 1 FROM kelas_pengajar kp
                          WHERE kp.id_kelas=k.id_kelas AND kp.id_dosen=?
                        )
                 FROM kelas_kuliah k
                 INNER JOIN mata_kuliah mk ON mk.id_mata_kuliah=k.id_mata_kuliah
                 WHERE k.id_kelas=? LIMIT 1'
            );
            mysqli_stmt_bind_param($statement, 'ii', $lecturerId, $classId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_bind_result($statement, $classDay, $className, $courseCode, $courseName, $isTeamMember);
            $classFound = mysqli_stmt_fetch($statement);
            mysqli_stmt_close($statement);
            if (!$classFound) {
                apiResponse('error', ['status' => 'error', 'message' => 'Kelas tidak ditemukan'], 404);
            }
            if (!(int) $isTeamMember) {
                apiResponse('error', [
                    'status' => 'error',
                    'message' => 'Dosen bukan anggota tim pengajar kelas ini',
                ], 403);
            }

            $timezone = new DateTimeZone('Asia/Makassar');
            $today = new DateTimeImmutable('today', $timezone);
            $cutoff = $today->modify('-4 days');
            $cutoffValue = $cutoff->format('Y-m-d');
            $todayValue = $today->format('Y-m-d');
            $statement = mysqli_prepare(
                $conn,
                'SELECT p.id_pertemuan, p.tanggal_pertemuan, p.pertemuan_ke,
                        p.waktu_buka, p.status,
                        SUM(CASE WHEN pm.status IN (\'Hadir\',\'Terlambat\') THEN 1 ELSE 0 END),
                        COUNT(pm.id_presensi)
                 FROM presensi_pertemuan p
                 LEFT JOIN presensi_mahasiswa pm ON pm.id_pertemuan=p.id_pertemuan
                 WHERE p.id_kelas=? AND p.tanggal_pertemuan BETWEEN ? AND ?
                 GROUP BY p.id_pertemuan, p.tanggal_pertemuan, p.pertemuan_ke,
                          p.waktu_buka, p.status'
            );
            mysqli_stmt_bind_param($statement, 'iss', $classId, $cutoffValue, $todayValue);
            mysqli_stmt_execute($statement);
            mysqli_stmt_bind_result(
                $statement, $meetingId, $meetingDate, $meetingNumber, $openedAt,
                $meetingStatus, $presentCount, $participantCount
            );
            $existingMeetings = [];
            while (mysqli_stmt_fetch($statement)) {
                $existingMeetings[$meetingDate] = [
                    'id_pertemuan' => (int) $meetingId,
                    'pertemuan_ke' => (int) $meetingNumber,
                    'waktu_buka' => $openedAt,
                    'status' => $meetingStatus,
                    'jumlah_hadir' => (int) $presentCount,
                    'total_peserta' => (int) $participantCount,
                ];
            }
            mysqli_stmt_close($statement);

            $dayByNumber = [
                1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
                5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
            ];
            $dates = [];
            for ($offset = 0; $offset <= 4; $offset++) {
                $date = $today->modify('-' . $offset . ' days');
                $dateValue = $date->format('Y-m-d');
                $day = $dayByNumber[(int) $date->format('N')];
                if ($day !== $classDay) {
                    continue;
                }
                $existing = $existingMeetings[$dateValue] ?? null;
                $dates[] = [
                    'tanggal' => $dateValue,
                    'hari' => $day,
                    'label' => $day . ', ' . $date->format('d-m-Y'),
                    'pertemuan' => $existing,
                ];
            }

            apiResponse('success', [
                'status' => 'success',
                'kelas' => [
                    'id_kelas' => (int) $classId,
                    'kode' => $courseCode,
                    'nama_mata_kuliah' => $courseName,
                    'nama_kelas' => $className,
                    'hari' => $classDay,
                ],
                'tanggal_pertemuan' => $dates,
            ]);
        }

        if ($section !== 'hari_ini') {
            apiResponse('error', ['status' => 'error', 'message' => 'Bagian jadwal tidak valid'], 400);
        }

        $hari = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ][(int) date('N')];

        $statement = mysqli_prepare(
            $conn,
            'SELECT k.id_kelas, mk.kode, mk.nama, k.nama_kelas, k.hari,
                    k.jam_mulai, k.jam_selesai, k.ruangan,
                    ta.tahun, ta.semester,
                    (SELECT COUNT(*) FROM peserta_kelas pk WHERE pk.id_kelas = k.id_kelas) AS jumlah_peserta,
                    (SELECT p.status FROM presensi_pertemuan p
                     WHERE p.id_kelas = k.id_kelas AND p.status = \'Dibuka\'
                     ORDER BY p.waktu_buka DESC LIMIT 1) AS status_sesi
             FROM kelas_kuliah k
             INNER JOIN mata_kuliah mk ON mk.id_mata_kuliah = k.id_mata_kuliah
             INNER JOIN tahun_akademik ta ON ta.id_tahun_akademik = k.id_tahun_akademik
             WHERE k.hari = ? AND ta.aktif = 1
               AND EXISTS (
                 SELECT 1 FROM kelas_pengajar kp
                 WHERE kp.id_kelas = k.id_kelas AND kp.id_dosen = ?
               )
             ORDER BY k.jam_mulai'
        );
        mysqli_stmt_bind_result(
            $statement,
            $idKelas,
            $kodeMk,
            $namaMk,
            $namaKelas,
            $hariDb,
            $jamMulai,
            $jamSelesai,
            $ruangan,
            $tahun,
            $semester,
            $jumlahPeserta,
            $statusSesi
        );
        mysqli_stmt_bind_param($statement, 'si', $hari, $lecturerId);
        mysqli_stmt_execute($statement);

        $schedule = [];
        while (mysqli_stmt_fetch($statement)) {
            $schedule[] = [
                'id_kelas' => (int) $idKelas,
                'kode' => $kodeMk,
                'nama_mata_kuliah' => $namaMk,
                'nama_kelas' => $namaKelas,
                'hari' => $hariDb,
                'jam_mulai' => $jamMulai,
                'jam_selesai' => $jamSelesai,
                'ruangan' => $ruangan,
                'tahun' => $tahun,
                'semester' => $semester,
                'jumlah_peserta' => (int) $jumlahPeserta,
                'status_sesi' => $statusSesi,
            ];
        }
        mysqli_stmt_close($statement);

        apiResponse('success', [
            'status' => 'success',
            'hari' => $hari,
            'kelas' => $schedule,
        ]);
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        apiResponse('error', ['status' => 'error', 'message' => 'Format data tidak valid'], 400);
    }

    $action = $payload['aksi'] ?? '';

    if ($action === 'aktifkan') {
        $idKelas = filter_var($payload['id_kelas'] ?? null, FILTER_VALIDATE_INT);
        $meetingDateValue = $payload['tanggal_pertemuan'] ?? null;
        $mode = $payload['mode_verifikasi'] ?? 'QR + Wajah';
        $batasMenit = filter_var(
            $payload['batas_tepat_menit'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($idKelas === false || $idKelas < 1) {
            apiResponse('error', ['status' => 'error', 'message' => 'Kelas tidak valid'], 400);
        }
        if (!is_string($meetingDateValue)) {
            apiResponse('error', ['status' => 'error', 'message' => 'Tanggal pertemuan tidak valid'], 400);
        }
        $timezone = new DateTimeZone('Asia/Makassar');
        $meetingDate = DateTimeImmutable::createFromFormat('!Y-m-d', $meetingDateValue, $timezone);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (!$meetingDate || ($dateErrors !== false
                && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $meetingDate->format('Y-m-d') !== $meetingDateValue) {
            apiResponse('error', ['status' => 'error', 'message' => 'Tanggal pertemuan tidak valid'], 400);
        }
        $today = new DateTimeImmutable('today', $timezone);
        if ($meetingDate > $today || $meetingDate < $today->modify('-4 days')) {
            apiResponse('error', [
                'status' => 'error',
                'message' => 'Tanggal pertemuan harus hari ini atau maksimal 4 hari sebelumnya',
            ], 400);
        }
        if (!in_array($mode, ['QR', 'QR + Wajah'], true)) {
            apiResponse('error', ['status' => 'error', 'message' => 'Mode verifikasi tidak valid'], 400);
        }
        if ($batasMenit === false || $batasMenit < 1 || $batasMenit > 180) {
            apiResponse('error', ['status' => 'error', 'message' => 'Batas tepat waktu harus 1 sampai 180 menit'], 400);
        }

        mysqli_begin_transaction($conn);

        $statement = mysqli_prepare(
            $conn,
            'SELECT k.hari, k.ruangan
             FROM kelas_kuliah k
             WHERE k.id_kelas = ?
               AND EXISTS (
                 SELECT 1 FROM kelas_pengajar kp
                 WHERE kp.id_kelas = k.id_kelas AND kp.id_dosen = ?
               )
             LIMIT 1 FOR UPDATE'
        );
        mysqli_stmt_bind_param($statement, 'ii', $idKelas, $lecturerId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $classDay, $room);
        if (!mysqli_stmt_fetch($statement)) {
            mysqli_stmt_close($statement);
            mysqli_rollback($conn);
            apiResponse('error', [
                'status' => 'error',
                'message' => 'Kelas bukan bagian dari tim pengajar dosen ini',
            ], 403);
        }
        mysqli_stmt_close($statement);
        $dayByNumber = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
            5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
        ];
        if ($dayByNumber[(int) $meetingDate->format('N')] !== $classDay) {
            mysqli_rollback($conn);
            apiResponse('error', [
                'status' => 'error',
                'message' => 'Tanggal tidak sesuai dengan hari jadwal kelas',
            ], 400);
        }

        $statement = mysqli_prepare(
            $conn,
            'SELECT id_pertemuan FROM presensi_pertemuan
             WHERE id_kelas = ? AND status = \'Dibuka\' LIMIT 1 FOR UPDATE'
        );
        mysqli_stmt_bind_param($statement, 'i', $idKelas);
        mysqli_stmt_execute($statement);
        mysqli_stmt_store_result($statement);
        $hasOpenMeeting = mysqli_stmt_num_rows($statement) > 0;
        mysqli_stmt_close($statement);
        if ($hasOpenMeeting) {
            mysqli_rollback($conn);
            apiResponse('error', ['status' => 'error', 'message' => 'Sesi kelas ini masih dibuka'], 409);
        }

        $meetingDateSql = $meetingDate->format('Y-m-d');
        $statement = mysqli_prepare(
            $conn,
            'SELECT id_pertemuan FROM presensi_pertemuan
             WHERE id_kelas = ? AND tanggal_pertemuan = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($statement, 'is', $idKelas, $meetingDateSql);
        mysqli_stmt_execute($statement);
        mysqli_stmt_store_result($statement);
        $hasMeetingOnDate = mysqli_stmt_num_rows($statement) > 0;
        mysqli_stmt_close($statement);
        if ($hasMeetingOnDate) {
            mysqli_rollback($conn);
            apiResponse('error', [
                'status' => 'error',
                'message' => 'Pertemuan untuk tanggal tersebut sudah ada',
            ], 409);
        }

        $statement = mysqli_prepare(
            $conn,
            'SELECT COALESCE(MAX(pertemuan_ke), 0) + 1
             FROM presensi_pertemuan WHERE id_kelas = ?'
        );
        mysqli_stmt_bind_param($statement, 'i', $idKelas);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $meetingNumber);
        mysqli_stmt_fetch($statement);
        mysqli_stmt_close($statement);

        $meetingStatus = 'Dibuka';
        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO presensi_pertemuan
             (id_kelas, id_dosen, pertemuan_ke, tanggal_pertemuan, waktu_buka, status,
              mode_verifikasi, batas_tepat_menit, ruangan)
             VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)'
        );
        mysqli_stmt_bind_param(
            $statement,
            'iiisssis',
            $idKelas,
            $lecturerId,
            $meetingNumber,
            $meetingDateSql,
            $meetingStatus,
            $mode,
            $batasMenit,
            $room
        );
        mysqli_stmt_execute($statement);
        $meetingId = mysqli_insert_id($conn);
        mysqli_stmt_close($statement);

        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO presensi_mahasiswa (id_pertemuan, id_mahasiswa, status)
             SELECT ?, pk.id_mahasiswa, \'Alpa\'
             FROM peserta_kelas pk
             WHERE pk.id_kelas = ?'
        );
        mysqli_stmt_bind_param($statement, 'ii', $meetingId, $idKelas);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        mysqli_commit($conn);
        apiResponse('success', [
            'status' => 'success',
            'message' => 'Absensi berhasil dibuka',
            'id_pertemuan' => $meetingId,
            'pertemuan_ke' => (int) $meetingNumber,
            'tanggal_pertemuan' => $meetingDateSql,
        ], 201);
    }

    if ($action === 'tutup') {
        $meetingId = filter_var($payload['id_pertemuan'] ?? null, FILTER_VALIDATE_INT);
        if ($meetingId === false || $meetingId < 1) {
            apiResponse('error', ['status' => 'error', 'message' => 'Pertemuan tidak valid'], 400);
        }

        $statement = mysqli_prepare(
            $conn,
            'SELECT p.status,
                    EXISTS (
                      SELECT 1 FROM kelas_pengajar kp
                      WHERE kp.id_kelas = p.id_kelas AND kp.id_dosen = ?
                    )
             FROM presensi_pertemuan p WHERE p.id_pertemuan = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($statement, 'ii', $lecturerId, $meetingId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $currentStatus, $isTeamMember);
        $meetingFound = mysqli_stmt_fetch($statement);
        mysqli_stmt_close($statement);
        if (!$meetingFound) {
            apiResponse('error', ['status' => 'error', 'message' => 'Pertemuan tidak ditemukan'], 404);
        }
        if (!(int) $isTeamMember) {
            apiResponse('error', ['status' => 'error', 'message' => 'Dosen bukan anggota tim pengajar kelas ini'], 403);
        }
        if ($currentStatus !== 'Dibuka') {
            apiResponse('error', ['status' => 'error', 'message' => 'Sesi absensi sudah ditutup'], 409);
        }

        $statement = mysqli_prepare(
            $conn,
            'UPDATE presensi_pertemuan
             SET status = \'Ditutup\', waktu_tutup = NOW()
             WHERE id_pertemuan = ? AND status = \'Dibuka\'
               AND EXISTS (
                 SELECT 1 FROM kelas_pengajar kp
                 WHERE kp.id_kelas = presensi_pertemuan.id_kelas AND kp.id_dosen = ?
               )'
        );
        mysqli_stmt_bind_param($statement, 'ii', $meetingId, $lecturerId);
        mysqli_stmt_execute($statement);
        $affected = mysqli_stmt_affected_rows($statement);
        mysqli_stmt_close($statement);

        if ($affected < 1) {
            apiResponse('error', ['status' => 'error', 'message' => 'Sesi absensi sudah ditutup'], 409);
        }

        apiResponse('success', ['status' => 'success', 'message' => 'Absensi berhasil ditutup']);
    }

    apiResponse('error', ['status' => 'error', 'message' => 'Aksi tidak valid'], 400);
} catch (mysqli_sql_exception $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        mysqli_rollback($conn);
    }
    error_log('Absensi dosen database error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'Absensi dosen tidak dapat diproses'], 500);
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        mysqli_rollback($conn);
    }
    error_log('Absensi dosen error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'Absensi dosen tidak dapat diproses'], 500);
}
