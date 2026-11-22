<?php
require_once __DIR__ . '/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) {
    apiResponse('error', ['status' => 'error', 'message' => 'Metode tidak diizinkan'], 405);
}

$user = requireRole('dosen');

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
        $export = ($_GET['export'] ?? '') === 'csv';
        $meetingId = filter_var($_GET['id_pertemuan'] ?? null, FILTER_VALIDATE_INT);

        $classesStatement = mysqli_prepare(
            $conn,
            'SELECT k.id_kelas, mk.kode, mk.nama, k.nama_kelas
             FROM kelas_kuliah k
             INNER JOIN mata_kuliah mk ON mk.id_mata_kuliah = k.id_mata_kuliah
             WHERE EXISTS (
               SELECT 1 FROM kelas_pengajar kp
               WHERE kp.id_kelas = k.id_kelas AND kp.id_dosen = ?
             )
             ORDER BY mk.nama, k.nama_kelas'
        );
        mysqli_stmt_bind_param($classesStatement, 'i', $lecturerId);
        mysqli_stmt_execute($classesStatement);
        mysqli_stmt_bind_result($classesStatement, $classId, $courseCode, $courseName, $className);
        $classes = [];
        while (mysqli_stmt_fetch($classesStatement)) {
            $classes[] = [
                'id_kelas' => (int) $classId,
                'kode' => $courseCode,
                'nama_mata_kuliah' => $courseName,
                'nama_kelas' => $className,
            ];
        }
        mysqli_stmt_close($classesStatement);

        $selectedClassId = filter_var($_GET['id_kelas'] ?? null, FILTER_VALIDATE_INT);
        $meetings = [];
        if ($selectedClassId !== false && $selectedClassId > 0) {
            $accessStatement = mysqli_prepare(
                $conn,
                'SELECT EXISTS (SELECT 1 FROM kelas_kuliah WHERE id_kelas=?),
                        EXISTS (
                          SELECT 1 FROM kelas_pengajar
                          WHERE id_kelas=? AND id_dosen=?
                        )'
            );
            mysqli_stmt_bind_param($accessStatement, 'iii', $selectedClassId, $selectedClassId, $lecturerId);
            mysqli_stmt_execute($accessStatement);
            mysqli_stmt_bind_result($accessStatement, $classExists, $isTeamMember);
            mysqli_stmt_fetch($accessStatement);
            mysqli_stmt_close($accessStatement);
            if (!(int) $classExists) {
                apiResponse('error', ['status' => 'error', 'message' => 'Kelas tidak ditemukan'], 404);
            }
            if (!(int) $isTeamMember) {
                apiResponse('error', ['status' => 'error', 'message' => 'Dosen bukan anggota tim pengajar kelas ini'], 403);
            }

            $meetingStatement = mysqli_prepare(
                $conn,
                'SELECT p.id_pertemuan, p.pertemuan_ke, p.tanggal_pertemuan,
                        p.waktu_buka, p.status, p.ruangan
                 FROM presensi_pertemuan p
                 WHERE p.id_kelas = ?
                   AND EXISTS (
                     SELECT 1 FROM kelas_pengajar kp
                     WHERE kp.id_kelas = p.id_kelas AND kp.id_dosen = ?
                   )
                 ORDER BY p.waktu_buka DESC'
            );
            mysqli_stmt_bind_param($meetingStatement, 'ii', $selectedClassId, $lecturerId);
            mysqli_stmt_execute($meetingStatement);
            mysqli_stmt_bind_result(
                $meetingStatement,
                $meetingIdRow,
                $meetingNumber,
                $meetingDate,
                $openedAt,
                $meetingStatus,
                $room
            );
            while (mysqli_stmt_fetch($meetingStatement)) {
                $meetings[] = [
                    'id_pertemuan' => (int) $meetingIdRow,
                    'pertemuan_ke' => (int) $meetingNumber,
                    'tanggal_pertemuan' => $meetingDate,
                    'waktu_buka' => $openedAt,
                    'jam_buka' => date('H:i', strtotime($openedAt)),
                    'status' => $meetingStatus,
                    'ruangan' => $room,
                ];
            }
            mysqli_stmt_close($meetingStatement);
        }

        if ($meetingId === false || $meetingId < 1) {
            apiResponse('success', [
                'status' => 'success',
                'kelas' => $classes,
                'pertemuan' => $meetings,
            ]);
        }

        $meetingDateLimit = (new DateTimeImmutable(
            'today',
            new DateTimeZone('Asia/Makassar')
        ))->modify('-4 days')->format('Y-m-d');
        $todayDate = (new DateTimeImmutable(
            'today',
            new DateTimeZone('Asia/Makassar')
        ))->format('Y-m-d');
        $meetingStatement = mysqli_prepare(
            $conn,
            'SELECT p.id_kelas, p.pertemuan_ke, p.status, p.tanggal_pertemuan, p.waktu_buka,
                    mk.kode, mk.nama, k.nama_kelas, p.ruangan,
                    EXISTS (
                      SELECT 1 FROM kelas_pengajar kp
                      WHERE kp.id_kelas=p.id_kelas AND kp.id_dosen=?
                    )
             FROM presensi_pertemuan p
             INNER JOIN kelas_kuliah k ON k.id_kelas = p.id_kelas
             INNER JOIN mata_kuliah mk ON mk.id_mata_kuliah = k.id_mata_kuliah
             WHERE p.id_pertemuan = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($meetingStatement, 'ii', $lecturerId, $meetingId);
        mysqli_stmt_execute($meetingStatement);
        mysqli_stmt_bind_result(
            $meetingStatement,
            $meetingClassId,
            $meetingNumber,
            $meetingStatus,
            $meetingDate,
            $openedAt,
            $courseCode,
            $courseName,
            $className,
            $room,
            $isTeamMember
        );
        if (!mysqli_stmt_fetch($meetingStatement)) {
            mysqli_stmt_close($meetingStatement);
            apiResponse('error', ['status' => 'error', 'message' => 'Pertemuan tidak ditemukan'], 404);
        }
        mysqli_stmt_close($meetingStatement);
        if (!(int) $isTeamMember) {
            apiResponse('error', ['status' => 'error', 'message' => 'Dosen bukan anggota tim pengajar kelas ini'], 403);
        }
        $canEdit = $meetingDate >= $meetingDateLimit && $meetingDate <= $todayDate;

        $rowStatement = mysqli_prepare(
            $conn,
            'SELECT pm.id_mahasiswa, m.nim, m.nama_lengkap, pm.status,
                    pm.metode, pm.waktu_scan, pm.skor_wajah
             FROM presensi_mahasiswa pm
             INNER JOIN mahasiswa m ON m.id_mahasiswa = pm.id_mahasiswa
             WHERE pm.id_pertemuan = ?
             ORDER BY m.nama_lengkap'
        );
        mysqli_stmt_bind_param($rowStatement, 'i', $meetingId);
        mysqli_stmt_execute($rowStatement);
        mysqli_stmt_bind_result(
            $rowStatement,
            $studentId,
            $studentNim,
            $studentName,
            $attendanceStatus,
            $attendanceMethod,
            $scanTime,
            $faceScore
        );
        $rows = [];
        while (mysqli_stmt_fetch($rowStatement)) {
            $rows[] = [
                'id_mahasiswa' => (int) $studentId,
                'nim' => $studentNim,
                'nama_lengkap' => $studentName,
                'status' => $attendanceStatus,
                'metode' => $attendanceMethod,
                'waktu_scan' => $scanTime,
                'skor_wajah' => $faceScore !== null ? (float) $faceScore : null,
            ];
        }
        mysqli_stmt_close($rowStatement);

        if ($export) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="rekap-presensi-' . $meetingId . '.csv"');
            echo "\xEF\xBB\xBF";
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Kode MK', 'Nama MK', 'Kelas', 'Pertemuan', 'Ruangan', 'NIM', 'Nama', 'Status', 'Metode', 'Waktu Scan', 'Skor Wajah']);
            foreach ($rows as $row) {
                fputcsv($output, [
                    $courseCode,
                    $courseName,
                    $className,
                    $meetingNumber,
                    $room,
                    $row['nim'],
                    $row['nama_lengkap'],
                    $row['status'],
                    $row['metode'] ?? '',
                    $row['waktu_scan'] ?? '',
                    $row['skor_wajah'] ?? '',
                ]);
            }
            fclose($output);
            exit;
        }

        apiResponse('success', [
            'status' => 'success',
            'kelas' => $classes,
            'pertemuan' => $meetings,
            'detail' => [
                'id_pertemuan' => (int) $meetingId,
                'pertemuan_ke' => (int) $meetingNumber,
                'status' => $meetingStatus,
                'tanggal_pertemuan' => $meetingDate,
                'waktu_buka' => $openedAt,
                'kode' => $courseCode,
                'nama_mata_kuliah' => $courseName,
                'nama_kelas' => $className,
                'ruangan' => $room,
            ],
            'dapat_dikoreksi' => $canEdit,
            'peserta' => $rows,
        ]);
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        apiResponse('error', ['status' => 'error', 'message' => 'Format data tidak valid'], 400);
    }

    $meetingId = filter_var($payload['id_pertemuan'] ?? null, FILTER_VALIDATE_INT);
    $studentId = filter_var($payload['id_mahasiswa'] ?? null, FILTER_VALIDATE_INT);
    $status = $payload['status'] ?? '';

    if ($meetingId === false || $meetingId < 1 || $studentId === false || $studentId < 1) {
        apiResponse('error', ['status' => 'error', 'message' => 'Pertemuan atau mahasiswa tidak valid'], 400);
    }
    if (!in_array($status, ['Hadir', 'Izin', 'Sakit', 'Alpa', 'Terlambat'], true)) {
        apiResponse('error', ['status' => 'error', 'message' => 'Status kehadiran tidak valid'], 400);
    }

    $meetingAccessStatement = mysqli_prepare(
        $conn,
        'SELECT p.tanggal_pertemuan,
                EXISTS (
             SELECT 1 FROM kelas_pengajar kp
             WHERE kp.id_kelas = p.id_kelas AND kp.id_dosen = ?
           )
         FROM presensi_pertemuan p WHERE p.id_pertemuan = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($meetingAccessStatement, 'ii', $lecturerId, $meetingId);
    mysqli_stmt_execute($meetingAccessStatement);
    mysqli_stmt_bind_result($meetingAccessStatement, $meetingDate, $isTeamMember);
    $meetingFound = mysqli_stmt_fetch($meetingAccessStatement);
    mysqli_stmt_close($meetingAccessStatement);
    if (!$meetingFound) {
        apiResponse('error', ['status' => 'error', 'message' => 'Pertemuan tidak ditemukan'], 404);
    }
    if (!(int) $isTeamMember) {
        apiResponse('error', ['status' => 'error', 'message' => 'Dosen bukan anggota tim pengajar kelas ini'], 403);
    }
    $meetingDateLimit = (new DateTimeImmutable(
        'today',
        new DateTimeZone('Asia/Makassar')
    ))->modify('-4 days')->format('Y-m-d');
    $todayDate = (new DateTimeImmutable(
        'today',
        new DateTimeZone('Asia/Makassar')
    ))->format('Y-m-d');
    if ($meetingDate < $meetingDateLimit || $meetingDate > $todayDate) {
        apiResponse('error', [
            'status' => 'error',
            'message' => 'Koreksi hanya dapat dilakukan sampai 4 hari setelah tanggal pertemuan',
        ], 403);
    }

    $ownershipStatement = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) FROM presensi_mahasiswa
         WHERE id_pertemuan = ? AND id_mahasiswa = ?'
    );
    mysqli_stmt_bind_param($ownershipStatement, 'ii', $meetingId, $studentId);
    mysqli_stmt_execute($ownershipStatement);
    mysqli_stmt_bind_result($ownershipStatement, $ownedRows);
    mysqli_stmt_fetch($ownershipStatement);
    mysqli_stmt_close($ownershipStatement);
    if ((int) $ownedRows < 1) {
        apiResponse('error', ['status' => 'error', 'message' => 'Data presensi tidak dapat dikoreksi'], 403);
    }

    $manualMethod = 'Manual';
    $scanTime = null;
    if ($status === 'Hadir' || $status === 'Terlambat') {
        $scanTime = date('Y-m-d H:i:s');
    }

    $updateStatement = mysqli_prepare(
        $conn,
        'UPDATE presensi_mahasiswa
         SET status = ?, metode = ?, waktu_scan = ?, skor_wajah = NULL,
             dikoreksi_oleh = ?
         WHERE id_pertemuan = ? AND id_mahasiswa = ?'
    );
    mysqli_stmt_bind_param(
        $updateStatement,
        'sssiii',
        $status,
        $manualMethod,
        $scanTime,
        $user['id'],
        $meetingId,
        $studentId
    );
    mysqli_stmt_execute($updateStatement);
    mysqli_stmt_close($updateStatement);

    apiResponse('success', [
        'status' => 'success',
        'message' => 'Status kehadiran berhasil dikoreksi',
    ]);
} catch (Throwable $error) {
    error_log('Rekap dosen error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'Rekap presensi tidak dapat diproses'], 500);
}
