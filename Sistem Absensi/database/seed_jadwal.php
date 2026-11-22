<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Skrip ini hanya boleh dijalankan melalui CLI.\n");
}

require_once __DIR__ . '/../Koneksi/koneksi.php';

$lecturers = [
    ['002', 'Ikhsan Hidayat, S.Kom, M.T', 'ikhsan.hidayat'],
    ['003', 'Dr. Bambang Panji Asmara, ST, MT', 'bambang.asmara'],
    ['004', 'Andi Sitti Dwi Auliyani, S.Pd., M.Pd', 'andi.auliyani'],
    ['005', 'Yasin Mohamad, ST., MT.', 'yasin.mohamad'],
    ['006', 'Iskandar Zulkarnain Nasibu, S.Pd, M.Eng', 'iskandar.nasibu'],
    ['007', 'Wildan, S.Pd., M.Pd', 'wildan'],
    ['008', 'Salmawaty Tansa, ST, M.Eng', 'salmawaty.tansa'],
    ['009', 'Rahmat Deddy Rianto Dako, ST., M.Eng', 'rahmat.dako'],
    ['010', 'Abdul Gani Fadhlulrahman S. H. Lihawa, S.Kom., M.Kom', 'abdulgani.lihawa'],
    ['011', 'Ifan Wiranto, ST., MT', 'ifan.wiranto'],
    ['012', 'Afifah Farhanah Akadji, S.Si., M.Si.', 'afifah.akadji'],
];

$courses = [
    ['EAL604452', 'SISTEM OPERASI', 2, 5, 'Senin', '13:00', '14:40', 'RK 2.11', [1, 2]],
    ['EAL604353', 'JARINGAN KOMPUTASI', 3, 5, 'Senin', '14:40', '17:10', 'Lab. Komputer 1', [1, 2]],
    ['EAL602933', 'FISIKA 2', 3, 3, 'Selasa', '15:30', '18:00', 'Lab. Kendali', [3, 4]],
    ['EAL602533', 'SIRKUIT & ELEKTRONIKA', 3, 3, 'Rabu', '13:00', '15:30', 'Lab. Unit Produksi', [5, 6]],
    ['EAL602732', 'DESAIN DIGITAL & LOGIKA', 2, 3, 'Rabu', '15:30', '17:10', 'Lab. Kendali', [2, 7]],
    ['EAL603033', 'PEMROGRAMAN CLIENT/SERVER', 3, 3, 'Kamis', '13:00', '15:30', 'Lab. Komputer 1', [8, 9]],
    ['EAL602433', 'PERSAMAAN DIFERENSIAL', 3, 3, 'Kamis', '15:30', '18:00', 'Lab. Unit Produksi', [10, 11]],
    ['EAL602631', 'PRAK. SIRKUIT & ELEKTRONIKA', 1, 3, 'Sabtu', '13:00', '13:50', 'Lab. Elektronika', [5, 6]],
    ['EAL603131', 'PRAK. PEMROGRAMAN CLIENT/SERVER', 1, 3, 'Sabtu', '13:51', '14:40', 'Lab. Komputer 2', [8, 9]],
    ['EAL602831', 'PRAK. DESAIN DIGITAL & LOGIKA', 1, 3, 'Sabtu', '14:41', '15:30', 'Lab. Komputer 1', [2, 7]],
];

$created = ['tahun_akademik' => 0, 'dosen' => 0, 'mata_kuliah' => 0, 'kelas' => 0];
$assignedTeams = 0;
$enrolledStudents = 0;
$prodi = 'Teknik Komputer';
$initialPassword = 'Glen181106';

try {
    mysqli_begin_transaction($conn);

    $year = '2026/2027';
    $academicSemester = 'Ganjil';
    $statement = mysqli_prepare(
        $conn,
        'SELECT id_tahun_akademik FROM tahun_akademik WHERE tahun=? AND semester=? LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 'ss', $year, $academicSemester);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $academicYearId);
    $yearFound = mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);

    if (!$yearFound) {
        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO tahun_akademik (tahun, semester, aktif) VALUES (?, ?, 0)'
        );
        mysqli_stmt_bind_param($statement, 'ss', $year, $academicSemester);
        mysqli_stmt_execute($statement);
        $academicYearId = mysqli_insert_id($conn);
        mysqli_stmt_close($statement);
        $created['tahun_akademik']++;
    }

    mysqli_query($conn, 'UPDATE tahun_akademik SET aktif=0 WHERE aktif<>0');
    $statement = mysqli_prepare(
        $conn,
        'UPDATE tahun_akademik SET aktif=1 WHERE id_tahun_akademik=?'
    );
    mysqli_stmt_bind_param($statement, 'i', $academicYearId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    $lecturerIds = [];
    foreach ($lecturers as [$nidn, $name, $username]) {
        $passwordHash = password_hash($initialPassword, PASSWORD_DEFAULT);
        $statement = mysqli_prepare(
            $conn,
            'SELECT d.id_dosen, d.id_login
             FROM dosen d INNER JOIN login l ON l.id_login=d.id_login
             WHERE d.nidn=? OR l.username=?'
        );
        mysqli_stmt_bind_param($statement, 'ss', $nidn, $username);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $matchedLecturerId, $matchedLoginId);
        $lecturerId = null;
        $loginId = null;
        while (mysqli_stmt_fetch($statement)) {
            if ($lecturerId !== null && $lecturerId !== (int) $matchedLecturerId) {
                mysqli_stmt_close($statement);
                throw new RuntimeException(
                    "NIDN $nidn dan username $username menunjuk ke akun dosen yang berbeda."
                );
            }
            $lecturerId = (int) $matchedLecturerId;
            $loginId = (int) $matchedLoginId;
        }
        mysqli_stmt_close($statement);

        if ($lecturerId !== null) {
            $statement = mysqli_prepare(
                $conn,
                'UPDATE login SET password=?, role=\'dosen\', aktif=1 WHERE id_login=?'
            );
            mysqli_stmt_bind_param($statement, 'si', $passwordHash, $loginId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);

            $status = 'Aktif';
            $statement = mysqli_prepare(
                $conn,
                'UPDATE dosen SET nidn=?, nama_lengkap=?, prodi=?, status_akun=? WHERE id_dosen=?'
            );
            mysqli_stmt_bind_param($statement, 'ssssi', $nidn, $name, $prodi, $status, $lecturerId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        } else {
            $statement = mysqli_prepare($conn, 'SELECT id_login FROM login WHERE username=? LIMIT 1');
            mysqli_stmt_bind_param($statement, 's', $username);
            mysqli_stmt_execute($statement);
            mysqli_stmt_bind_result($statement, $conflictingLoginId);
            $usernameConflict = mysqli_stmt_fetch($statement);
            mysqli_stmt_close($statement);
            if ($usernameConflict) {
                throw new RuntimeException("Username $username sudah dipakai akun selain dosen.");
            }

            $role = 'dosen';
            $statement = mysqli_prepare(
                $conn,
                'INSERT INTO login (username, password, role) VALUES (?, ?, ?)'
            );
            mysqli_stmt_bind_param($statement, 'sss', $username, $passwordHash, $role);
            mysqli_stmt_execute($statement);
            $loginId = mysqli_insert_id($conn);
            mysqli_stmt_close($statement);

            $status = 'Aktif';
            $statement = mysqli_prepare(
                $conn,
                'INSERT INTO dosen (id_login, nidn, nama_lengkap, prodi, status_akun)
                 VALUES (?, ?, ?, ?, ?)'
            );
            mysqli_stmt_bind_param($statement, 'issss', $loginId, $nidn, $name, $prodi, $status);
            mysqli_stmt_execute($statement);
            $lecturerId = mysqli_insert_id($conn);
            mysqli_stmt_close($statement);
            $created['dosen']++;
        }
        $lecturerIds[] = (int) $lecturerId;
    }

    $courseIds = [];
    foreach ($courses as [$code, $name, $credits, $semester, $day, $start, $end, $room, $team]) {
        $statement = mysqli_prepare(
            $conn,
            'SELECT id_mata_kuliah FROM mata_kuliah WHERE kode=? LIMIT 1'
        );
        mysqli_stmt_bind_param($statement, 's', $code);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $courseId);
        $courseFound = mysqli_stmt_fetch($statement);
        mysqli_stmt_close($statement);

        if ($courseFound) {
            $statement = mysqli_prepare(
                $conn,
                'UPDATE mata_kuliah SET nama=?, sks=?, semester=? WHERE id_mata_kuliah=?'
            );
            mysqli_stmt_bind_param($statement, 'siii', $name, $credits, $semester, $courseId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        } else {
            $statement = mysqli_prepare(
                $conn,
                'INSERT INTO mata_kuliah (kode, nama, sks, semester) VALUES (?, ?, ?, ?)'
            );
            mysqli_stmt_bind_param($statement, 'ssii', $code, $name, $credits, $semester);
            mysqli_stmt_execute($statement);
            $courseId = mysqli_insert_id($conn);
            mysqli_stmt_close($statement);
            $created['mata_kuliah']++;
        }
        $courseIds[] = (int) $courseId;
    }

    foreach ($courses as $index => [$code, $name, $credits, $semester, $day, $start, $end, $room, $team]) {
        $courseId = $courseIds[$index];
        $className = 'A';
        $statement = mysqli_prepare(
            $conn,
            'SELECT id_kelas FROM kelas_kuliah
             WHERE id_mata_kuliah=? AND id_tahun_akademik=? AND nama_kelas=? LIMIT 1'
        );
        mysqli_stmt_bind_param($statement, 'iis', $courseId, $academicYearId, $className);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $classId);
        $classFound = mysqli_stmt_fetch($statement);
        mysqli_stmt_close($statement);

        if ($classFound) {
            $statement = mysqli_prepare(
                $conn,
                'UPDATE kelas_kuliah
                 SET hari=?, jam_mulai=?, jam_selesai=?, ruangan=?
                 WHERE id_kelas=?'
            );
            mysqli_stmt_bind_param($statement, 'ssssi', $day, $start, $end, $room, $classId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        } else {
            $statement = mysqli_prepare(
                $conn,
                'INSERT INTO kelas_kuliah
                 (id_mata_kuliah, id_tahun_akademik, nama_kelas, hari, jam_mulai, jam_selesai, ruangan)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            mysqli_stmt_bind_param(
                $statement,
                'iisssss',
                $courseId,
                $academicYearId,
                $className,
                $day,
                $start,
                $end,
                $room
            );
            mysqli_stmt_execute($statement);
            $classId = mysqli_insert_id($conn);
            mysqli_stmt_close($statement);
            $created['kelas']++;
        }

        $statement = mysqli_prepare($conn, 'DELETE FROM kelas_pengajar WHERE id_kelas=?');
        mysqli_stmt_bind_param($statement, 'i', $classId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        foreach ($team as $position => $lecturerIndex) {
            $lecturerId = $lecturerIds[$lecturerIndex - 1];
            $order = $position + 1;
            $statement = mysqli_prepare(
                $conn,
                'INSERT INTO kelas_pengajar (id_kelas, id_dosen, urutan) VALUES (?, ?, ?)'
            );
            mysqli_stmt_bind_param($statement, 'iii', $classId, $lecturerId, $order);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            $assignedTeams++;
        }
    }

    $statement = mysqli_prepare(
        $conn,
        'INSERT IGNORE INTO peserta_kelas (id_kelas, id_mahasiswa)
         SELECT k.id_kelas, m.id_mahasiswa
         FROM kelas_kuliah k
         INNER JOIN mata_kuliah mk ON mk.id_mata_kuliah=k.id_mata_kuliah
         INNER JOIN mahasiswa m ON m.semester=mk.semester
         WHERE k.id_tahun_akademik=?'
    );
    mysqli_stmt_bind_param($statement, 'i', $academicYearId);
    mysqli_stmt_execute($statement);
    $enrolledStudents = mysqli_stmt_affected_rows($statement);
    mysqli_stmt_close($statement);

    mysqli_commit($conn);
    echo "Seed jadwal selesai.\n";
    echo "Tahun akademik dibuat: {$created['tahun_akademik']}\n";
    echo "Akun dosen dibuat: {$created['dosen']}\n";
    echo "Mata kuliah dibuat: {$created['mata_kuliah']}\n";
    echo "Kelas dibuat: {$created['kelas']}\n";
    echo "Tim pengajar disimpan: $assignedTeams\n";
    echo "Enrolmen mahasiswa baru: $enrolledStudents\n";
} catch (Throwable $error) {
    mysqli_rollback($conn);
    fwrite(STDERR, "Seed jadwal gagal: " . $error->getMessage() . "\n");
    exit(1);
}
