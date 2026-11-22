<?php
require_once __DIR__ . '/auth.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    apiResponse('error', ['status' => 'error', 'message' => 'Metode tidak diizinkan'], 405);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole('admin');
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        apiResponse('error', ['status' => 'error', 'message' => 'Format data tidak valid'], 400);
    }

    $lecturerId = filter_var($payload['id_dosen'] ?? null, FILTER_VALIDATE_INT);
    $decision = $payload['keputusan'] ?? '';
    $statuses = ['setujui' => 'Aktif', 'tolak' => 'Ditolak'];
    if (($payload['aksi'] ?? '') !== 'persetujuan'
        || $lecturerId === false || $lecturerId < 1
        || !is_string($decision) || !isset($statuses[$decision])) {
        apiResponse('error', ['status' => 'error', 'message' => 'Keputusan persetujuan tidak valid'], 400);
    }

    try {
        require_once __DIR__ . '/../Koneksi/koneksi.php';
        $status = $statuses[$decision];
        $statement = mysqli_prepare(
            $conn,
            'UPDATE dosen SET status_akun = ?
             WHERE id_dosen = ? AND status_akun = \'Menunggu\''
        );
        mysqli_stmt_bind_param($statement, 'si', $status, $lecturerId);
        mysqli_stmt_execute($statement);
        $updated = mysqli_stmt_affected_rows($statement);
        mysqli_stmt_close($statement);

        if ($updated !== 1) {
            apiResponse('error', [
                'status' => 'error',
                'message' => 'Pendaftaran dosen tidak ditemukan atau sudah diproses',
            ], 409);
        }

        $message = $decision === 'setujui'
            ? 'Akun dosen berhasil disetujui'
            : 'Pendaftaran akun dosen berhasil ditolak';
        apiResponse('success', ['status' => 'success', 'message' => $message]);
    } catch (Throwable $error) {
        error_log('Persetujuan dosen error: ' . $error->getMessage());
        apiResponse('error', ['status' => 'error', 'message' => 'Persetujuan dosen tidak dapat diproses'], 500);
    }
}

$user = requireRole('dosen');

try {
    require_once __DIR__ . '/../Koneksi/koneksi.php';
    requireActiveDosen($conn, $user);
    $statement = mysqli_prepare($conn, 'SELECT id_dosen FROM dosen WHERE id_login=? LIMIT 1');
    mysqli_stmt_bind_param($statement, 'i', $user['id']);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $lecturerId);
    if (!mysqli_stmt_fetch($statement)) {
        mysqli_stmt_close($statement);
        apiResponse('error', ['status' => 'error', 'message' => 'Data dosen tidak ditemukan'], 404);
    }
    mysqli_stmt_close($statement);

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
        'SELECT k.id_kelas, k.id_mata_kuliah, k.id_tahun_akademik,
                mk.kode, mk.nama, mk.sks, mk.semester,
                k.nama_kelas, k.hari, k.jam_mulai, k.jam_selesai, k.ruangan,
                (SELECT COUNT(*) FROM peserta_kelas pk WHERE pk.id_kelas=k.id_kelas),
                (SELECT COUNT(*) FROM presensi_pertemuan p
                 WHERE p.id_kelas=k.id_kelas AND p.status=\'Dibuka\')
         FROM kelas_kuliah k
         INNER JOIN mata_kuliah mk ON mk.id_mata_kuliah=k.id_mata_kuliah
         WHERE k.id_tahun_akademik = ?
           AND EXISTS (
             SELECT 1 FROM kelas_pengajar kp
             WHERE kp.id_kelas = k.id_kelas AND kp.id_dosen = ?
           )
         ORDER BY FIELD(k.hari,\'Senin\',\'Selasa\',\'Rabu\',\'Kamis\',\'Jumat\',\'Sabtu\',\'Minggu\'),
                  k.jam_mulai'
    );
    mysqli_stmt_bind_param($statement, 'ii', $yearId, $lecturerId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result(
        $statement,
        $classId,
        $courseId,
        $classYearId,
        $kode,
        $namaMataKuliah,
        $sks,
        $courseSemester,
        $namaKelas,
        $hari,
        $jamMulai,
        $jamSelesai,
        $ruangan,
        $jumlahPeserta,
        $sesiAktif
    );
    $rows = [];
    $classIds = [];
    $dayByNumber = [
        1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
        5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
    ];
    $timezone = new DateTimeZone('Asia/Makassar');
    $todayDate = new DateTimeImmutable('today', $timezone);
    $weekStart = new DateTimeImmutable('monday this week', $timezone);
    $weekDays = [];
    $weekDateByDay = [];
    for ($offset = 0; $offset < 7; $offset++) {
        $date = $weekStart->modify('+' . $offset . ' days');
        $dayName = $dayByNumber[(int) $date->format('N')];
        $weekDateByDay[$dayName] = $date->format('Y-m-d');
        $weekDays[] = [
            'hari' => $dayName,
            'tanggal' => $date->format('Y-m-d'),
            'label_tanggal' => $date->format('d-m-Y'),
            'hari_ini' => $date->format('Y-m-d') === $todayDate->format('Y-m-d'),
        ];
    }
    $today = $dayByNumber[(int) $todayDate->format('N')];
    $now = date('H:i:s');
    while (mysqli_stmt_fetch($statement)) {
        $classIds[] = (int) $classId;
        $rows[] = [
            'id_kelas' => (int) $classId,
            'id_mata_kuliah' => (int) $courseId,
            'id_tahun_akademik' => (int) $classYearId,
            'kode' => $kode,
            'nama_mata_kuliah' => $namaMataKuliah,
            'sks' => (int) $sks,
            'semester' => (int) $courseSemester,
            'nama_kelas' => $namaKelas,
            'hari' => $hari,
            'tanggal' => $weekDateByDay[$hari] ?? null,
            'jam_mulai' => substr($jamMulai, 0, 5),
            'jam_selesai' => substr($jamSelesai, 0, 5),
            'ruangan' => $ruangan,
            'jumlah_peserta' => (int) $jumlahPeserta,
            'sesi_aktif' => (int) $sesiAktif,
            'sedang_berlangsung' => $yearIsActive && $hari === $today
                && $now >= $jamMulai && $now <= $jamSelesai,
            'tim_pengajar' => [],
        ];
    }
    mysqli_stmt_close($statement);

    if ($classIds) {
        $placeholders = implode(',', array_fill(0, count($classIds), '?'));
        $teamStatement = mysqli_prepare(
            $conn,
            "SELECT kp.id_kelas, d.nama_lengkap
             FROM kelas_pengajar kp
             INNER JOIN dosen d ON d.id_dosen = kp.id_dosen
             WHERE kp.id_kelas IN ($placeholders)
             ORDER BY kp.id_kelas, kp.urutan"
        );
        $teamTypes = str_repeat('i', count($classIds));
        mysqli_stmt_bind_param($teamStatement, $teamTypes, ...$classIds);
        mysqli_stmt_execute($teamStatement);
        mysqli_stmt_bind_result($teamStatement, $teamClassId, $lecturerName);
        $teams = [];
        while (mysqli_stmt_fetch($teamStatement)) {
            $teams[(int) $teamClassId][] = $lecturerName;
        }
        mysqli_stmt_close($teamStatement);

        foreach ($rows as &$row) {
            $row['tim_pengajar'] = $teams[$row['id_kelas']] ?? [];
        }
        unset($row);
    }

    apiResponse('success', [
        'status' => 'success',
        'jadwal' => $rows,
        'pekan' => $weekDays,
        'tahun_akademik' => $years,
        'id_tahun_akademik' => $yearId,
    ]);
} catch (Throwable $error) {
    error_log('Dosen API error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'Jadwal dosen tidak dapat dimuat'], 500);
}
