<?php
require_once __DIR__ . '/auth.php';

$user = requireRole('dosen');

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) {
    apiResponse('error', ['status' => 'error', 'message' => 'Metode tidak diizinkan'], 405);
}

try {
    require_once __DIR__ . '/../Koneksi/koneksi.php';

    requireActiveDosen($conn, $user);
    $statement = mysqli_prepare($conn, 'SELECT id_dosen FROM dosen WHERE id_login = ? LIMIT 1');
    mysqli_stmt_bind_param($statement, 'i', $user['id']);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $lecturerId);
    if (!mysqli_stmt_fetch($statement)) {
        mysqli_stmt_close($statement);
        apiResponse('error', ['status' => 'error', 'message' => 'Data dosen tidak ditemukan'], 404);
    }
    mysqli_stmt_close($statement);

    if ($method === 'GET') {
        $meetingId = filter_var($_GET['id_pertemuan'] ?? null, FILTER_VALIDATE_INT);
        if ($meetingId === false || $meetingId < 1) {
            apiResponse('error', ['status' => 'error', 'message' => 'Pertemuan tidak valid'], 400);
        }

        $statement = mysqli_prepare(
            $conn,
            'SELECT p.status, p.pertemuan_ke, p.mode_verifikasi,
                    EXISTS (
                      SELECT 1 FROM kelas_pengajar kp
                      WHERE kp.id_kelas = p.id_kelas AND kp.id_dosen = ?
                    )
             FROM presensi_pertemuan p
             WHERE p.id_pertemuan = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($statement, 'ii', $lecturerId, $meetingId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $sessionStatus, $meetingNumber, $mode, $isTeamMember);
        if (!mysqli_stmt_fetch($statement)) {
            mysqli_stmt_close($statement);
            apiResponse('error', ['status' => 'error', 'message' => 'Pertemuan tidak ditemukan'], 404);
        }
        mysqli_stmt_close($statement);
        if (!(int) $isTeamMember) {
            apiResponse('error', ['status' => 'error', 'message' => 'Dosen bukan anggota tim pengajar kelas ini'], 403);
        }

        $statement = mysqli_prepare(
            $conn,
            'SELECT m.nim, m.nama_lengkap, pm.status, pm.metode, pm.waktu_scan, pm.skor_wajah
             FROM presensi_mahasiswa pm
             INNER JOIN mahasiswa m ON m.id_mahasiswa = pm.id_mahasiswa
             WHERE pm.id_pertemuan = ?
             ORDER BY m.nama_lengkap'
        );
        mysqli_stmt_bind_param($statement, 'i', $meetingId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result(
            $statement,
            $nim,
            $studentName,
            $attendanceStatus,
            $methodName,
            $scanTime,
            $faceScore
        );

        $students = [];
        $hadir = 0;
        while (mysqli_stmt_fetch($statement)) {
            if (in_array($attendanceStatus, ['Hadir', 'Terlambat'], true)) {
                $hadir++;
            }
            $students[] = [
                'nim' => $nim,
                'nama_lengkap' => $studentName,
                'status' => $attendanceStatus,
                'metode' => $methodName,
                'waktu_scan' => $scanTime,
                'skor_wajah' => $faceScore !== null ? (float) $faceScore : null,
            ];
        }
        mysqli_stmt_close($statement);

        apiResponse('success', [
            'status' => 'success',
            'sesi' => [
                'status' => $sessionStatus,
                'pertemuan_ke' => (int) $meetingNumber,
                'mode_verifikasi' => $mode,
            ],
            'jumlah_hadir' => $hadir,
            'total_peserta' => count($students),
            'peserta' => $students,
        ]);
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        apiResponse('error', ['status' => 'error', 'message' => 'Format data tidak valid'], 400);
    }

    $meetingId = filter_var($payload['id_pertemuan'] ?? null, FILTER_VALIDATE_INT);
    $qrToken = $payload['qr_token'] ?? null;
    $descriptor = $payload['descriptor'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    if ($meetingId === false || $meetingId < 1) {
        apiResponse('error', ['status' => 'error', 'message' => 'Pertemuan tidak valid'], 400);
    }

    $statement = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) FROM log_scan
         WHERE alamat_ip = ? AND waktu_dibuat >= DATE_SUB(NOW(), INTERVAL 60 SECOND)'
    );
    mysqli_stmt_bind_param($statement, 's', $ipAddress);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $attemptCount);
    mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);
    if ((int) $attemptCount >= 30) {
        apiResponse('error', ['status' => 'error', 'message' => 'Percobaan terlalu cepat, coba lagi nanti'], 429);
    }

    $statement = mysqli_prepare(
        $conn,
        'SELECT p.status, p.mode_verifikasi, p.waktu_buka, p.batas_tepat_menit,
                p.id_kelas,
                EXISTS (
                  SELECT 1 FROM kelas_pengajar kp
                  WHERE kp.id_kelas = p.id_kelas AND kp.id_dosen = ?
                )
         FROM presensi_pertemuan p
         WHERE p.id_pertemuan = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 'ii', $lecturerId, $meetingId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result(
        $statement,
        $sessionStatus,
        $mode,
        $openedAt,
        $onTimeMinutes,
        $classId,
        $isTeamMember
    );
    if (!mysqli_stmt_fetch($statement)) {
        mysqli_stmt_close($statement);
        apiResponse('error', ['status' => 'error', 'message' => 'Sesi absensi tidak ditemukan'], 404);
    }
    mysqli_stmt_close($statement);
    if (!(int) $isTeamMember) {
        apiResponse('error', ['status' => 'error', 'message' => 'Dosen bukan anggota tim pengajar kelas ini'], 403);
    }

    if ($sessionStatus !== 'Dibuka') {
        apiResponse('error', ['status' => 'error', 'message' => 'Sesi absensi sudah ditutup'], 409);
    }

    $qrToken = is_string($qrToken) ? trim($qrToken) : '';
    if (!preg_match('/^[a-f0-9]{64}$/D', $qrToken)) {
        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO log_scan
             (id_pertemuan, id_mahasiswa, qr_token, hasil, alasan, alamat_ip)
             VALUES (?, NULL, NULL, \'Gagal\', \'Format QR tidak valid\', ?)'
        );
        mysqli_stmt_bind_param($statement, 'is', $meetingId, $ipAddress);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        apiResponse('error', ['status' => 'error', 'message' => 'QR tidak dikenali'], 400);
    }

    $statement = mysqli_prepare(
        $conn,
        'SELECT m.id_mahasiswa, m.nim, m.nama_lengkap
         FROM mahasiswa m
         WHERE m.qr_token = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 's', $qrToken);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $studentId, $studentNim, $studentName);
    $studentFound = mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);

    if (!$studentFound) {
        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO log_scan
             (id_pertemuan, id_mahasiswa, qr_token, hasil, alasan, alamat_ip)
             VALUES (?, NULL, ?, \'Gagal\', \'QR tidak dikenal\', ?)'
        );
        mysqli_stmt_bind_param($statement, 'iss', $meetingId, $qrToken, $ipAddress);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        apiResponse('error', ['status' => 'error', 'message' => 'QR tidak dikenali'], 404);
    }

    $statement = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) FROM peserta_kelas
         WHERE id_kelas = ? AND id_mahasiswa = ?'
    );
    mysqli_stmt_bind_param($statement, 'ii', $classId, $studentId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $participantCount);
    mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);

    if ((int) $participantCount < 1) {
        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO log_scan
             (id_pertemuan, id_mahasiswa, qr_token, hasil, alasan, alamat_ip)
             VALUES (?, ?, ?, \'Gagal\', \'Mahasiswa bukan peserta kelas\', ?)'
        );
        mysqli_stmt_bind_param($statement, 'iiss', $meetingId, $studentId, $qrToken, $ipAddress);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        apiResponse('error', ['status' => 'error', 'message' => 'Mahasiswa bukan peserta kelas'], 403);
    }

    $statement = mysqli_prepare(
        $conn,
        'SELECT pm.status FROM presensi_mahasiswa pm
         WHERE pm.id_pertemuan = ? AND pm.id_mahasiswa = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 'ii', $meetingId, $studentId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $attendanceStatus);
    $attendanceRowFound = mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);

    if (!$attendanceRowFound) {
        apiResponse('error', ['status' => 'error', 'message' => 'Mahasiswa belum terdaftar pada sesi ini'], 404);
    }
    if (in_array($attendanceStatus, ['Hadir', 'Terlambat'], true)) {
        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO log_scan
             (id_pertemuan, id_mahasiswa, qr_token, hasil, alasan, alamat_ip)
             VALUES (?, ?, ?, \'Gagal\', \'Absensi ganda dicegah\', ?)'
        );
        mysqli_stmt_bind_param($statement, 'iiss', $meetingId, $studentId, $qrToken, $ipAddress);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        apiResponse('error', ['status' => 'error', 'message' => 'Mahasiswa sudah tercatat hadir'], 409);
    }

    if ($mode === 'QR + Wajah') {
        if (!is_array($descriptor) || count($descriptor) !== 128) {
            apiResponse('success', [
                'status' => 'success',
                'memerlukan_wajah' => true,
                'id_pertemuan' => $meetingId,
                'mahasiswa' => [
                    'id_mahasiswa' => (int) $studentId,
                    'nim' => $studentNim,
                    'nama_lengkap' => $studentName,
                ],
            ]);
        }

        foreach ($descriptor as $value) {
            if (!is_numeric($value) || !is_finite((float) $value)) {
                apiResponse('error', ['status' => 'error', 'message' => 'Descriptor wajah tidak valid'], 400);
            }
        }

        $statement = mysqli_prepare(
            $conn,
            'SELECT descriptor FROM wajah_mahasiswa WHERE id_mahasiswa = ?'
        );
        mysqli_stmt_bind_param($statement, 'i', $studentId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $descriptorJson);

        $minimumDistance = null;
        while (mysqli_stmt_fetch($statement)) {
            $stored = json_decode($descriptorJson, true);
            if (!is_array($stored) || count($stored) !== 128) continue;
            $distance = 0.0;
            for ($index = 0; $index < 128; $index++) {
                $difference = (float) $descriptor[$index] - (float) $stored[$index];
                $distance += $difference * $difference;
            }
            $distance = sqrt($distance);
            if ($minimumDistance === null || $distance < $minimumDistance) {
                $minimumDistance = $distance;
            }
        }
        mysqli_stmt_close($statement);

        if ($minimumDistance === null) {
            $statement = mysqli_prepare(
                $conn,
                'INSERT INTO log_scan
                 (id_pertemuan, id_mahasiswa, qr_token, hasil, alasan, alamat_ip)
                 VALUES (?, ?, ?, \'Gagal\', \'Mahasiswa belum merekam wajah\', ?)'
            );
            mysqli_stmt_bind_param($statement, 'iiss', $meetingId, $studentId, $qrToken, $ipAddress);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            apiResponse('error', [
                'status' => 'error',
                'message' => 'Mahasiswa belum merekam wajah. Minta mahasiswa merekam wajah terlebih dahulu.',
            ], 422);
        }

        $settingValue = '0.5';
        $statement = mysqli_prepare(
            $conn,
            'SELECT nilai FROM pengaturan WHERE kunci = \'ambang_wajah\' LIMIT 1'
        );
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $settingValueFromDb);
        if (mysqli_stmt_fetch($statement)) {
            $settingValue = $settingValueFromDb;
        }
        mysqli_stmt_close($statement);

        $threshold = (float) $settingValue;
        if ($minimumDistance === null || $minimumDistance >= $threshold) {
            $statement = mysqli_prepare(
                $conn,
                'INSERT INTO log_scan
                 (id_pertemuan, id_mahasiswa, qr_token, hasil, alasan, alamat_ip)
                 VALUES (?, ?, ?, \'Gagal\', \'Wajah tidak cocok\', ?)'
            );
            mysqli_stmt_bind_param($statement, 'iiss', $meetingId, $studentId, $qrToken, $ipAddress);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            apiResponse('error', [
                'status' => 'error',
                'message' => 'Wajah tidak cocok',
                'skor_wajah' => $minimumDistance !== null ? round($minimumDistance, 4) : null,
            ], 403);
        }
        $faceScore = $minimumDistance;
    } else {
        $faceScore = null;
    }

    $statusToSave = 'Hadir';
    $minutesFromOpen = (time() - strtotime($openedAt)) / 60;
    if ($minutesFromOpen > (int) $onTimeMinutes) {
        $statusToSave = 'Terlambat';
    }
    $storedMethod = $mode;

    mysqli_begin_transaction($conn);
    $statement = mysqli_prepare(
        $conn,
        'UPDATE presensi_mahasiswa
         SET status = ?, metode = ?, waktu_scan = NOW(), skor_wajah = ?
         WHERE id_pertemuan = ? AND id_mahasiswa = ?'
    );
    mysqli_stmt_bind_param(
        $statement,
        'sssii',
        $statusToSave,
        $storedMethod,
        $faceScore,
        $meetingId,
        $studentId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    $statement = mysqli_prepare(
        $conn,
        'INSERT INTO log_scan
         (id_pertemuan, id_mahasiswa, qr_token, hasil, alasan, alamat_ip)
         VALUES (?, ?, ?, \'Berhasil\', \'Absensi tercatat\', ?)'
    );
    mysqli_stmt_bind_param($statement, 'iiss', $meetingId, $studentId, $qrToken, $ipAddress);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    mysqli_commit($conn);

    apiResponse('success', [
        'status' => 'success',
        'message' => 'Absensi berhasil dicatat',
        'mahasiswa' => [
            'nim' => $studentNim,
            'nama_lengkap' => $studentName,
        ],
        'status_kehadiran' => $statusToSave,
        'skor_wajah' => $faceScore !== null ? round($faceScore, 4) : null,
    ]);
} catch (mysqli_sql_exception $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        mysqli_rollback($conn);
    }
    error_log('Scan absensi database error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'Proses scan tidak dapat diselesaikan'], 500);
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        mysqli_rollback($conn);
    }
    error_log('Scan absensi error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'Proses scan tidak dapat diselesaikan'], 500);
}
