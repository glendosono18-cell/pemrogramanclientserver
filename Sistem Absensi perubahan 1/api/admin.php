<?php
require_once __DIR__ . '/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) {
    apiResponse('error', ['status' => 'error', 'message' => 'Metode tidak diizinkan'], 405);
}

requireRole('admin');

function fetchAdminRows($conn, $query)
{
    $statement = mysqli_prepare($conn, $query);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    mysqli_stmt_close($statement);
    return $result;
}

try {
    require_once __DIR__ . '/../Koneksi/koneksi.php';

    if ($method === 'GET') {
        $bagian = $_GET['bagian'] ?? 'ringkasan';

        if ($bagian === 'ringkasan') {
            $counts = [];
            foreach ([
                'dosen_menunggu' => "SELECT COUNT(*) FROM dosen WHERE status_akun='Menunggu'",
                'kelas' => 'SELECT COUNT(*) FROM kelas_kuliah',
                'pertemuan' => 'SELECT COUNT(*) FROM presensi_pertemuan',
            ] as $key => $query) {
                $result = fetchAdminRows($conn, $query);
                $counts[$key] = (int) mysqli_fetch_row($result)[0];
            }
            $mahasiswa = [];
            $result = fetchAdminRows(
                $conn,
                'SELECT semester, COUNT(*) AS jumlah
                 FROM mahasiswa GROUP BY semester ORDER BY semester'
            );
            while ($row = mysqli_fetch_assoc($result)) {
                $mahasiswa[] = [
                    'kategori' => 'Semester ' . (int) $row['semester'],
                    'jumlah' => (int) $row['jumlah'],
                ];
            }
            $dosen = [];
            $result = fetchAdminRows(
                $conn,
                'SELECT status_akun, COUNT(*) AS jumlah
                 FROM dosen GROUP BY status_akun
                 ORDER BY FIELD(status_akun, \'Aktif\', \'Menunggu\', \'Ditolak\')'
            );
            while ($row = mysqli_fetch_assoc($result)) {
                $dosen[] = [
                    'kategori' => $row['status_akun'],
                    'jumlah' => (int) $row['jumlah'],
                ];
            }
            $counts['mahasiswa'] = array_sum(array_column($mahasiswa, 'jumlah'));
            $counts['dosen'] = array_sum(array_column($dosen, 'jumlah'));
            apiResponse('success', [
                'status' => 'success',
                'statistik' => $counts,
                'distribusi_mahasiswa' => $mahasiswa,
                'distribusi_dosen' => $dosen,
            ]);
        }

        if ($bagian === 'mahasiswa') {
            $search = trim($_GET['q'] ?? '');
            $query = 'SELECT m.id_mahasiswa, m.nim, m.nama_lengkap, m.prodi, m.semester,
                             m.provinsi, m.kota, m.kecamatan, m.kelurahan,
                             l.username, l.aktif,
                             (SELECT COUNT(*) FROM wajah_mahasiswa w WHERE w.id_mahasiswa=m.id_mahasiswa) AS jumlah_wajah
                      FROM mahasiswa m
                      INNER JOIN login l ON l.id_login=m.id_login';
            $params = [];
            $types = '';
            if ($search !== '') {
                $query .= ' WHERE m.nim LIKE ? OR m.nama_lengkap LIKE ? OR l.username LIKE ?';
                $like = '%' . $search . '%';
                $params = [$like, $like, $like];
                $types = 'sss';
            }
            $query .= ' ORDER BY m.nama_lengkap';
            $statement = mysqli_prepare($conn, $query);
            if ($types !== '') {
                mysqli_stmt_bind_param($statement, $types, ...$params);
            }
            mysqli_stmt_execute($statement);
            mysqli_stmt_bind_result(
                $statement,
                $idMahasiswa,
                $nim,
                $nama,
                $prodi,
                $semester,
                $provinsi,
                $kota,
                $kecamatan,
                $kelurahan,
                $username,
                $aktif,
                $jumlahWajah
            );
            $rows = [];
            while (mysqli_stmt_fetch($statement)) {
                $rows[] = [
                    'id_mahasiswa' => (int) $idMahasiswa,
                    'nim' => $nim,
                    'nama_lengkap' => $nama,
                    'prodi' => $prodi,
                    'semester' => (int) $semester,
                    'provinsi' => $provinsi,
                    'kota' => $kota,
                    'kecamatan' => $kecamatan,
                    'kelurahan' => $kelurahan,
                    'username' => $username,
                    'aktif' => (int) $aktif,
                    'jumlah_wajah' => (int) $jumlahWajah,
                ];
            }
            mysqli_stmt_close($statement);
            apiResponse('success', ['status' => 'success', 'mahasiswa' => $rows]);
        }

        if ($bagian === 'dosen') {
            $statusFilter = $_GET['status'] ?? '';
            $search = $_GET['q'] ?? '';
            if (!is_string($statusFilter) || !is_string($search)) {
                apiResponse('error', ['status' => 'error', 'message' => 'Filter status tidak valid'], 400);
            }
            $statusFilter = trim($statusFilter);
            $search = trim($search);
            if (!in_array($statusFilter, ['', 'Menunggu', 'Aktif', 'Ditolak'], true)) {
                apiResponse('error', ['status' => 'error', 'message' => 'Filter status tidak valid'], 400);
            }

            $query = 'SELECT d.id_dosen, d.nidn, d.nama_lengkap, d.prodi,
                             d.provinsi, d.kota, d.kecamatan, d.kelurahan,
                             l.id_login, l.username, l.aktif, d.status_akun
                      FROM dosen d
                      INNER JOIN login l ON l.id_login=d.id_login';
            $conditions = [];
            $parameters = [];
            $types = '';
            if ($statusFilter !== '') {
                $conditions[] = 'd.status_akun = ?';
                $parameters[] = $statusFilter;
                $types .= 's';
            }
            if ($search !== '') {
                $conditions[] = '(d.nidn LIKE ? OR d.nama_lengkap LIKE ? OR l.username LIKE ?)';
                $like = '%' . $search . '%';
                array_push($parameters, $like, $like, $like);
                $types .= 'sss';
            }
            if ($conditions) {
                $query .= ' WHERE ' . implode(' AND ', $conditions);
            }
            $query .= ' ORDER BY d.nama_lengkap';
            $statement = mysqli_prepare($conn, $query);
            if ($types !== '') {
                mysqli_stmt_bind_param($statement, $types, ...$parameters);
            }
            mysqli_stmt_execute($statement);
            mysqli_stmt_bind_result(
                $statement,
                $idDosen,
                $nidn,
                $nama,
                $prodi,
                $provinsi,
                $kota,
                $kecamatan,
                $kelurahan,
                $idLogin,
                $username,
                $aktif,
                $statusAkun
            );
            $rows = [];
            while (mysqli_stmt_fetch($statement)) {
                $rows[] = [
                    'id_dosen' => (int) $idDosen,
                    'nidn' => $nidn,
                    'nama_lengkap' => $nama,
                    'prodi' => $prodi,
                    'provinsi' => $provinsi,
                    'kota' => $kota,
                    'kecamatan' => $kecamatan,
                    'kelurahan' => $kelurahan,
                    'id_login' => (int) $idLogin,
                    'username' => $username,
                    'aktif' => (int) $aktif,
                    'status_akun' => $statusAkun,
                ];
            }
            mysqli_stmt_close($statement);
            apiResponse('success', ['status' => 'success', 'dosen' => $rows]);
        }

        if ($bagian === 'akademik') {
            $data = ['tahun' => [], 'mata_kuliah' => [], 'kelas' => [], 'mahasiswa' => [], 'dosen' => []];

            $result = fetchAdminRows(
                $conn,
                'SELECT id_tahun_akademik, tahun, semester, aktif
                 FROM tahun_akademik ORDER BY tahun DESC, semester'
            );
            while ($row = mysqli_fetch_assoc($result)) {
                $row['id_tahun_akademik'] = (int) $row['id_tahun_akademik'];
                $row['aktif'] = (int) $row['aktif'];
                $data['tahun'][] = $row;
            }

            $result = fetchAdminRows(
                $conn,
                'SELECT id_mata_kuliah, kode, nama, sks, semester
                 FROM mata_kuliah ORDER BY nama'
            );
            while ($row = mysqli_fetch_assoc($result)) {
                $row['id_mata_kuliah'] = (int) $row['id_mata_kuliah'];
                $row['sks'] = (int) $row['sks'];
                $row['semester'] = (int) $row['semester'];
                $data['mata_kuliah'][] = $row;
            }

            $result = fetchAdminRows(
                $conn,
                'SELECT k.id_kelas, k.id_mata_kuliah, k.id_tahun_akademik,
                        k.nama_kelas, k.hari, k.jam_mulai, k.jam_selesai,
                        k.ruangan, mk.kode, mk.nama AS nama_mata_kuliah,
                        mk.semester AS semester_mata_kuliah, ta.tahun,
                        ta.semester AS semester_tahun,
                        (SELECT COUNT(*) FROM peserta_kelas pk WHERE pk.id_kelas=k.id_kelas) AS jumlah_peserta
                 FROM kelas_kuliah k
                 INNER JOIN mata_kuliah mk ON mk.id_mata_kuliah=k.id_mata_kuliah
                 INNER JOIN tahun_akademik ta ON ta.id_tahun_akademik=k.id_tahun_akademik
                 ORDER BY ta.tahun DESC, k.hari, k.jam_mulai'
            );
            while ($row = mysqli_fetch_assoc($result)) {
                $row['id_kelas'] = (int) $row['id_kelas'];
                $row['id_mata_kuliah'] = (int) $row['id_mata_kuliah'];
                $row['id_tahun_akademik'] = (int) $row['id_tahun_akademik'];
                $row['semester_mata_kuliah'] = (int) $row['semester_mata_kuliah'];
                $row['jumlah_peserta'] = (int) $row['jumlah_peserta'];
                $row['tim_pengajar'] = [];
                $row['id_dosen_1'] = null;
                $row['id_dosen_2'] = null;
                $data['kelas'][] = $row;
            }

            $teamResult = fetchAdminRows(
                $conn,
                'SELECT kp.id_kelas, kp.id_dosen, d.nama_lengkap
                 FROM kelas_pengajar kp
                 INNER JOIN dosen d ON d.id_dosen=kp.id_dosen
                 ORDER BY kp.id_kelas, kp.urutan'
            );
            $teams = [];
            while ($team = mysqli_fetch_assoc($teamResult)) {
                $classId = (int) $team['id_kelas'];
                $teams[$classId]['names'][] = $team['nama_lengkap'];
                $teams[$classId]['ids'][] = (int) $team['id_dosen'];
            }
            foreach ($data['kelas'] as &$class) {
                $team = $teams[$class['id_kelas']] ?? ['names' => [], 'ids' => []];
                $class['tim_pengajar'] = $team['names'];
                $class['id_dosen_1'] = $team['ids'][0] ?? null;
                $class['id_dosen_2'] = $team['ids'][1] ?? null;
            }
            unset($class);

            $result = fetchAdminRows(
                $conn,
                'SELECT id_mahasiswa, nim, nama_lengkap, prodi, semester FROM mahasiswa ORDER BY nama_lengkap'
            );
            while ($row = mysqli_fetch_assoc($result)) {
                $row['id_mahasiswa'] = (int) $row['id_mahasiswa'];
                $data['mahasiswa'][] = $row;
            }

            $result = fetchAdminRows(
                $conn,
                'SELECT id_dosen, nidn, nama_lengkap, prodi FROM dosen ORDER BY nama_lengkap'
            );
            while ($row = mysqli_fetch_assoc($result)) {
                $row['id_dosen'] = (int) $row['id_dosen'];
                $data['dosen'][] = $row;
            }

            apiResponse('success', ['status' => 'success'] + $data);
        }

        if ($bagian === 'peserta') {
            $classId = filter_var($_GET['id_kelas'] ?? null, FILTER_VALIDATE_INT);
            if ($classId === false || $classId < 1) {
                apiResponse('error', ['status' => 'error', 'message' => 'Kelas tidak valid'], 400);
            }
            $statement = mysqli_prepare(
                $conn,
                'SELECT m.id_mahasiswa, m.nim, m.nama_lengkap, m.prodi
                 FROM peserta_kelas pk
                 INNER JOIN mahasiswa m ON m.id_mahasiswa=pk.id_mahasiswa
                 WHERE pk.id_kelas=? ORDER BY m.nama_lengkap'
            );
            mysqli_stmt_bind_param($statement, 'i', $classId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_bind_result($statement, $idMahasiswa, $nim, $nama, $prodi);
            $rows = [];
            while (mysqli_stmt_fetch($statement)) {
                $rows[] = [
                    'id_mahasiswa' => (int) $idMahasiswa,
                    'nim' => $nim,
                    'nama_lengkap' => $nama,
                    'prodi' => $prodi,
                ];
            }
            mysqli_stmt_close($statement);
            apiResponse('success', ['status' => 'success', 'peserta' => $rows]);
        }

        if ($bagian === 'pengumuman') {
            $statement = mysqli_prepare(
                $conn,
                'SELECT id_pengumuman, judul, isi, dibuat_pada
                 FROM pengumuman ORDER BY dibuat_pada DESC'
            );
            mysqli_stmt_execute($statement);
            mysqli_stmt_bind_result($statement, $idPengumuman, $judul, $isi, $dibuatPada);
            $rows = [];
            while (mysqli_stmt_fetch($statement)) {
                $rows[] = [
                    'id_pengumuman' => (int) $idPengumuman,
                    'judul' => $judul,
                    'isi' => $isi,
                    'dibuat_pada' => $dibuatPada,
                ];
            }
            mysqli_stmt_close($statement);
            apiResponse('success', ['status' => 'success', 'pengumuman' => $rows]);
        }

        if ($bagian === 'pengaturan') {
            $result = fetchAdminRows(
                $conn,
                'SELECT kunci, nilai FROM pengaturan
                 WHERE kunci IN (\'ambang_wajah\', \'batas_tepat_menit\')'
            );
            $settings = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $settings[$row['kunci']] = $row['nilai'];
            }
            apiResponse('success', [
                'status' => 'success',
                'ambang_wajah' => $settings['ambang_wajah'] ?? '0.5',
                'batas_tepat_menit' => $settings['batas_tepat_menit'] ?? '15',
            ]);
        }

        if ($bagian === 'rekap') {
            $courseValue = $_GET['id_mata_kuliah'] ?? null;
            $classValue = $_GET['id_kelas'] ?? null;
            $meetingValue = $_GET['id_pertemuan'] ?? null;
            $courseId = $courseValue === null || $courseValue === '' ? null
                : ((is_string($courseValue) || is_int($courseValue)) ? filter_var($courseValue, FILTER_VALIDATE_INT) : false);
            $classId = $classValue === null || $classValue === '' ? null
                : ((is_string($classValue) || is_int($classValue)) ? filter_var($classValue, FILTER_VALIDATE_INT) : false);
            $meetingId = $meetingValue === null || $meetingValue === '' ? null
                : ((is_string($meetingValue) || is_int($meetingValue)) ? filter_var($meetingValue, FILTER_VALIDATE_INT) : false);
            $export = ($_GET['export'] ?? '') === 'csv';
            if (($courseId !== null && ($courseId === false || $courseId < 1))
                || ($classId !== null && ($classId === false || $classId < 1))
                || ($meetingId !== null && ($meetingId === false || $meetingId < 1))) {
                apiResponse('error', ['status' => 'error', 'message' => 'Pilihan rekap tidak valid'], 400);
            }

            $coursesResult = fetchAdminRows(
                $conn,
                'SELECT DISTINCT mk.id_mata_kuliah, mk.kode, mk.nama
                 FROM kelas_kuliah k
                 INNER JOIN mata_kuliah mk ON mk.id_mata_kuliah=k.id_mata_kuliah
                 ORDER BY mk.nama'
            );
            $courses = [];
            while ($row = mysqli_fetch_assoc($coursesResult)) {
                $row['id_mata_kuliah'] = (int) $row['id_mata_kuliah'];
                $courses[] = $row;
            }

            $classQuery = 'SELECT k.id_kelas, mk.id_mata_kuliah, mk.kode, mk.nama, k.nama_kelas
                 FROM kelas_kuliah k
                 INNER JOIN mata_kuliah mk ON mk.id_mata_kuliah=k.id_mata_kuliah';
            if ($courseId !== null) {
                $classQuery .= ' WHERE k.id_mata_kuliah=?';
            }
            $classQuery .= ' ORDER BY mk.nama, k.nama_kelas';
            $classesStatement = mysqli_prepare($conn, $classQuery);
            if ($courseId !== null) {
                mysqli_stmt_bind_param($classesStatement, 'i', $courseId);
            }
            mysqli_stmt_execute($classesStatement);
            mysqli_stmt_bind_result($classesStatement, $classRowId, $classCourseId, $classCode, $className, $classLabel);
            $classes = [];
            while (mysqli_stmt_fetch($classesStatement)) {
                $classes[] = [
                    'id_kelas' => (int) $classRowId,
                    'id_mata_kuliah' => (int) $classCourseId,
                    'kode' => $classCode,
                    'nama' => $className,
                    'nama_kelas' => $classLabel,
                ];
            }
            mysqli_stmt_close($classesStatement);

            if ($classId !== null) {
                $classCheckQuery = 'SELECT id_kelas FROM kelas_kuliah WHERE id_kelas=?';
                if ($courseId !== null) {
                    $classCheckQuery .= ' AND id_mata_kuliah=?';
                }
                $classCheckQuery .= ' LIMIT 1';
                $classCheck = mysqli_prepare($conn, $classCheckQuery);
                if ($courseId !== null) {
                    mysqli_stmt_bind_param($classCheck, 'ii', $classId, $courseId);
                } else {
                    mysqli_stmt_bind_param($classCheck, 'i', $classId);
                }
                mysqli_stmt_execute($classCheck);
                mysqli_stmt_bind_result($classCheck, $checkedClassId);
                $classFound = mysqli_stmt_fetch($classCheck);
                mysqli_stmt_close($classCheck);
                if (!$classFound) {
                    apiResponse('error', ['status' => 'error', 'message' => 'Kelas tidak ditemukan untuk mata kuliah ini'], 404);
                }
            }

            $meetings = [];
            if ($classId !== null) {
                $statement = mysqli_prepare(
                    $conn,
                    'SELECT id_pertemuan, pertemuan_ke, waktu_buka, status
                     FROM presensi_pertemuan WHERE id_kelas=? ORDER BY pertemuan_ke, waktu_buka'
                );
                mysqli_stmt_bind_param($statement, 'i', $classId);
                mysqli_stmt_execute($statement);
                mysqli_stmt_bind_result($statement, $idPertemuan, $ke, $buka, $statusPertemuan);
                while (mysqli_stmt_fetch($statement)) {
                    $meetings[] = [
                        'id_pertemuan' => (int) $idPertemuan,
                        'pertemuan_ke' => (int) $ke,
                        'waktu_buka' => $buka,
                        'status' => $statusPertemuan,
                    ];
                }
                mysqli_stmt_close($statement);
            }

            $trend = [];
            if ($classId !== null) {
                $statement = mysqli_prepare(
                    $conn,
                    'SELECT p.id_pertemuan, p.pertemuan_ke, p.waktu_buka, p.status,
                            COALESCE(SUM(CASE WHEN pm.status=\'Hadir\' THEN 1 ELSE 0 END),0),
                            COALESCE(SUM(CASE WHEN pm.status=\'Terlambat\' THEN 1 ELSE 0 END),0),
                            COALESCE(SUM(CASE WHEN pm.status=\'Izin\' THEN 1 ELSE 0 END),0),
                            COALESCE(SUM(CASE WHEN pm.status=\'Sakit\' THEN 1 ELSE 0 END),0),
                            COALESCE(SUM(CASE WHEN pm.status=\'Alpa\' THEN 1 ELSE 0 END),0),
                            (SELECT COUNT(*) FROM peserta_kelas pk WHERE pk.id_kelas=p.id_kelas)
                     FROM presensi_pertemuan p
                     LEFT JOIN presensi_mahasiswa pm ON pm.id_pertemuan=p.id_pertemuan
                     WHERE p.id_kelas=?
                     GROUP BY p.id_pertemuan, p.pertemuan_ke, p.waktu_buka, p.status, p.id_kelas
                     ORDER BY p.pertemuan_ke, p.waktu_buka'
                );
                mysqli_stmt_bind_param($statement, 'i', $classId);
                mysqli_stmt_execute($statement);
                mysqli_stmt_bind_result(
                    $statement, $trendMeetingId, $trendNumber, $trendOpenedAt, $trendStatus,
                    $present, $late, $excused, $sick, $absent, $totalStudents
                );
                while (mysqli_stmt_fetch($statement)) {
                    $present = (int) $present;
                    $late = (int) $late;
                    $totalStudents = (int) $totalStudents;
                    $trend[] = [
                        'id_pertemuan' => (int) $trendMeetingId,
                        'pertemuan_ke' => (int) $trendNumber,
                        'waktu_buka' => $trendOpenedAt,
                        'status' => $trendStatus,
                        'hadir' => $present,
                        'terlambat' => (int) $late,
                        'izin' => (int) $excused,
                        'sakit' => (int) $sick,
                        'alpa' => (int) $absent,
                        'total_peserta' => $totalStudents,
                        'persentase_hadir' => $totalStudents > 0
                            ? round(($present + $late) * 100 / $totalStudents, 1)
                            : 0,
                    ];
                }
                mysqli_stmt_close($statement);
            }

            if ($meetingId !== null) {
                $meetingCheck = mysqli_prepare(
                    $conn,
                    'SELECT p.id_kelas, k.id_mata_kuliah
                     FROM presensi_pertemuan p
                     INNER JOIN kelas_kuliah k ON k.id_kelas=p.id_kelas
                     WHERE p.id_pertemuan=? LIMIT 1'
                );
                mysqli_stmt_bind_param($meetingCheck, 'i', $meetingId);
                mysqli_stmt_execute($meetingCheck);
                mysqli_stmt_bind_result($meetingCheck, $meetingClassId, $meetingCourseId);
                $meetingFound = mysqli_stmt_fetch($meetingCheck);
                mysqli_stmt_close($meetingCheck);
                if (!$meetingFound
                    || ($classId !== null && (int) $meetingClassId !== $classId)
                    || ($courseId !== null && (int) $meetingCourseId !== $courseId)) {
                    apiResponse('error', ['status' => 'error', 'message' => 'Pertemuan tidak ditemukan untuk kelas ini'], 404);
                }
            }

            if ($meetingId === null) {
                apiResponse('success', [
                    'status' => 'success',
                    'mata_kuliah' => $courses,
                    'kelas' => $classes,
                    'pertemuan' => $meetings,
                    'tren' => $trend,
                ]);
            }

            $statement = mysqli_prepare(
                $conn,
                'SELECT m.nim, m.nama_lengkap, pm.status, pm.metode, pm.waktu_scan
                 FROM presensi_mahasiswa pm
                 INNER JOIN mahasiswa m ON m.id_mahasiswa=pm.id_mahasiswa
                 WHERE pm.id_pertemuan=? ORDER BY m.nama_lengkap'
            );
            mysqli_stmt_bind_param($statement, 'i', $meetingId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_bind_result($statement, $nim, $nama, $status, $metode, $waktu);
            $rows = [];
            while (mysqli_stmt_fetch($statement)) {
                $rows[] = [
                    'nim' => $nim,
                    'nama_lengkap' => $nama,
                    'status' => $status,
                    'metode' => $metode,
                    'waktu_scan' => $waktu,
                ];
            }
            mysqli_stmt_close($statement);

            if ($export) {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="rekap-admin-' . $meetingId . '.csv"');
                echo "\xEF\xBB\xBF";
                $output = fopen('php://output', 'w');
                fputcsv($output, ['NIM', 'Nama', 'Status', 'Metode', 'Waktu Scan']);
                foreach ($rows as $row) {
                    fputcsv($output, [$row['nim'], $row['nama_lengkap'], $row['status'], $row['metode'] ?? '', $row['waktu_scan'] ?? '']);
                }
                fclose($output);
                exit;
            }
            apiResponse('success', [
                'status' => 'success',
                'mata_kuliah' => $courses,
                'kelas' => $classes,
                'pertemuan' => $meetings,
                'peserta' => $rows,
                'tren' => $trend,
            ]);
        }

        apiResponse('error', ['status' => 'error', 'message' => 'Bagian data tidak valid'], 400);
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        apiResponse('error', ['status' => 'error', 'message' => 'Format data tidak valid'], 400);
    }
    $aksi = $payload['aksi'] ?? '';

    if ($aksi === 'mahasiswa_simpan') {
        foreach (['nim', 'nama_lengkap', 'prodi', 'provinsi', 'kota', 'kecamatan', 'kelurahan', 'username', 'password'] as $field) {
            if (!isset($payload[$field]) || !is_string($payload[$field])) {
                apiResponse('error', ['status' => 'error', 'message' => 'Data mahasiswa tidak lengkap'], 400);
            }
        }
        $nim = trim($payload['nim']);
        $nama = trim($payload['nama_lengkap']);
        $prodi = trim($payload['prodi']);
        $provinsi = trim($payload['provinsi']);
        $kota = trim($payload['kota']);
        $kecamatan = trim($payload['kecamatan']);
        $kelurahan = trim($payload['kelurahan']);
        $username = trim($payload['username']);
        $password = $payload['password'];
        $semester = filter_var($payload['semester'] ?? null, FILTER_VALIDATE_INT);
        if ($nim === '' || $nama === '' || $prodi === ''
            || $provinsi === '' || $kota === '' || $kecamatan === '' || $kelurahan === ''
            || $username === ''
            || strlen($nim) > 30 || strlen($nama) > 150 || strlen($prodi) > 100
            || strlen($provinsi) > 100 || strlen($kota) > 100 || strlen($kecamatan) > 100 || strlen($kelurahan) > 100
            || strlen($username) > 100
            || strlen($password) < 8 || strlen($password) > 255
            || $semester === false || $semester < 1 || $semester > 14) {
            apiResponse('error', [
                'status' => 'error',
                'message' => 'Periksa NIM, identitas, wilayah asal, username, semester 1–14, dan kata sandi minimal 8 karakter',
            ], 400);
        }

        mysqli_begin_transaction($conn);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'mahasiswa';
        $statement = mysqli_prepare($conn, 'INSERT INTO login(username,password,role) VALUES(?,?,?)');
        mysqli_stmt_bind_param($statement, 'sss', $username, $passwordHash, $role);
        mysqli_stmt_execute($statement);
        $loginId = mysqli_insert_id($conn);
        mysqli_stmt_close($statement);

        $qrToken = bin2hex(random_bytes(32));
        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO mahasiswa(id_login,nim,nama_lengkap,prodi,semester,provinsi,kota,kecamatan,kelurahan,qr_token)
             VALUES(?,?,?,?,?,?,?,?,?,?)'
        );
        mysqli_stmt_bind_param($statement, 'isssisssss', $loginId, $nim, $nama, $prodi, $semester, $provinsi, $kota, $kecamatan, $kelurahan, $qrToken);
        mysqli_stmt_execute($statement);
        $studentId = mysqli_insert_id($conn);
        mysqli_stmt_close($statement);

        $statement = mysqli_prepare(
            $conn,
            'INSERT IGNORE INTO peserta_kelas(id_kelas,id_mahasiswa)
             SELECT k.id_kelas, ?
             FROM kelas_kuliah k
             INNER JOIN mata_kuliah mk ON mk.id_mata_kuliah=k.id_mata_kuliah
             INNER JOIN tahun_akademik ta ON ta.id_tahun_akademik=k.id_tahun_akademik
             WHERE ta.aktif=1 AND k.nama_kelas=\'A\' AND mk.semester=?'
        );
        mysqli_stmt_bind_param($statement, 'ii', $studentId, $semester);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        mysqli_commit($conn);
        apiResponse('success', [
            'status' => 'success',
            'message' => 'Akun mahasiswa berhasil dibuat',
        ], 201);
    }

    if ($aksi === 'mahasiswa_update') {
        $id = filter_var($payload['id_mahasiswa'] ?? null, FILTER_VALIDATE_INT);
        $nim = trim($payload['nim'] ?? '');
        $nama = trim($payload['nama_lengkap'] ?? '');
        $prodi = trim($payload['prodi'] ?? '');
        $semester = filter_var($payload['semester'] ?? null, FILTER_VALIDATE_INT);
        $username = trim($payload['username'] ?? '');
        $aktif = ($payload['aktif'] ?? false) ? 1 : 0;
        if ($id === false || $nim === '' || $nama === '' || $prodi === '' || $username === ''
            || $semester === false || $semester < 1 || $semester > 14) {
            apiResponse('error', ['status' => 'error', 'message' => 'Data mahasiswa tidak valid'], 400);
        }
        mysqli_begin_transaction($conn);
        $statement = mysqli_prepare(
            $conn,
            'UPDATE mahasiswa SET nim=?, nama_lengkap=?, prodi=?, semester=? WHERE id_mahasiswa=?'
        );
        mysqli_stmt_bind_param($statement, 'sssii', $nim, $nama, $prodi, $semester, $id);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        $statement = mysqli_prepare(
            $conn,
            'UPDATE login l INNER JOIN mahasiswa m ON m.id_login=l.id_login
             SET l.username=?, l.aktif=? WHERE m.id_mahasiswa=?'
        );
        mysqli_stmt_bind_param($statement, 'sii', $username, $aktif, $id);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        mysqli_commit($conn);
        apiResponse('success', ['status' => 'success', 'message' => 'Data mahasiswa berhasil diperbarui']);
    }

    if ($aksi === 'mahasiswa_reset_qr') {
        $id = filter_var($payload['id_mahasiswa'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false) apiResponse('error', ['status' => 'error', 'message' => 'Mahasiswa tidak valid'], 400);
        $token = bin2hex(random_bytes(32));
        $statement = mysqli_prepare($conn, 'UPDATE mahasiswa SET qr_token=? WHERE id_mahasiswa=?');
        mysqli_stmt_bind_param($statement, 'si', $token, $id);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        apiResponse('success', ['status' => 'success', 'message' => 'Token QR berhasil direset']);
    }

    if ($aksi === 'mahasiswa_reset_wajah') {
        $id = filter_var($payload['id_mahasiswa'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false) apiResponse('error', ['status' => 'error', 'message' => 'Mahasiswa tidak valid'], 400);
        $statement = mysqli_prepare($conn, 'DELETE FROM wajah_mahasiswa WHERE id_mahasiswa=?');
        mysqli_stmt_bind_param($statement, 'i', $id);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        apiResponse('success', ['status' => 'success', 'message' => 'Data wajah berhasil direset']);
    }

    if ($aksi === 'mahasiswa_hapus') {
        $id = filter_var($payload['id_mahasiswa'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false) apiResponse('error', ['status' => 'error', 'message' => 'Mahasiswa tidak valid'], 400);
        $statement = mysqli_prepare(
            $conn,
            'DELETE l FROM login l INNER JOIN mahasiswa m ON m.id_login=l.id_login WHERE m.id_mahasiswa=?'
        );
        mysqli_stmt_bind_param($statement, 'i', $id);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        apiResponse('success', ['status' => 'success', 'message' => 'Mahasiswa berhasil dihapus']);
    }

    if (in_array($aksi, ['dosen_simpan', 'dosen_hapus'], true)) {
        if ($aksi === 'dosen_hapus') {
            $id = filter_var($payload['id_dosen'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false) apiResponse('error', ['status' => 'error', 'message' => 'Dosen tidak valid'], 400);
            $statement = mysqli_prepare(
                $conn,
                'DELETE l FROM login l INNER JOIN dosen d ON d.id_login=l.id_login WHERE d.id_dosen=?'
            );
            mysqli_stmt_bind_param($statement, 'i', $id);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            apiResponse('success', ['status' => 'success', 'message' => 'Dosen berhasil dihapus']);
        }

        $id = filter_var($payload['id_dosen'] ?? null, FILTER_VALIDATE_INT);
        $nidn = trim($payload['nidn'] ?? '');
        $nama = trim($payload['nama_lengkap'] ?? '');
        $prodi = trim($payload['prodi'] ?? '');
        $provinsi = trim((string) ($payload['provinsi'] ?? ''));
        $kota = trim((string) ($payload['kota'] ?? ''));
        $kecamatan = trim((string) ($payload['kecamatan'] ?? ''));
        $kelurahan = trim((string) ($payload['kelurahan'] ?? ''));
        $username = trim($payload['username'] ?? '');
        $password = $payload['password'] ?? '';
        if ($nidn === '' || $nama === '' || $prodi === '' || $username === ''
            || ($id === false && ($provinsi === '' || $kota === '' || $kecamatan === '' || $kelurahan === '' || strlen($password) < 8))) {
            apiResponse('error', [
                'status' => 'error',
                'message' => 'Data dosen dan wilayah asal tidak lengkap; kata sandi baru minimal 8 karakter',
            ], 400);
        }

        mysqli_begin_transaction($conn);
        if ($id === false) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'dosen';
            $statement = mysqli_prepare($conn, 'INSERT INTO login(username,password,role) VALUES(?,?,?)');
            mysqli_stmt_bind_param($statement, 'sss', $username, $hash, $role);
            mysqli_stmt_execute($statement);
            $loginId = mysqli_insert_id($conn);
            mysqli_stmt_close($statement);
            $statement = mysqli_prepare(
                $conn,
                'INSERT INTO dosen(id_login,nidn,nama_lengkap,prodi,provinsi,kota,kecamatan,kelurahan) VALUES(?,?,?,?,?,?,?,?)'
            );
            mysqli_stmt_bind_param($statement, 'isssssss', $loginId, $nidn, $nama, $prodi, $provinsi, $kota, $kecamatan, $kelurahan);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        } else {
            $provVal = $provinsi !== '' ? $provinsi : null;
            $kotaVal = $kota !== '' ? $kota : null;
            $kecVal = $kecamatan !== '' ? $kecamatan : null;
            $kelVal = $kelurahan !== '' ? $kelurahan : null;
            $statement = mysqli_prepare(
                $conn,
                'UPDATE dosen SET nidn=?, nama_lengkap=?, prodi=?,
                                  provinsi=COALESCE(?, provinsi), kota=COALESCE(?, kota),
                                  kecamatan=COALESCE(?, kecamatan), kelurahan=COALESCE(?, kelurahan)
                 WHERE id_dosen=?'
            );
            mysqli_stmt_bind_param($statement, 'sssssssi', $nidn, $nama, $prodi, $provVal, $kotaVal, $kecVal, $kelVal, $id);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            $statement = mysqli_prepare(
                $conn,
                'UPDATE login l INNER JOIN dosen d ON d.id_login=l.id_login SET l.username=? WHERE d.id_dosen=?'
            );
            mysqli_stmt_bind_param($statement, 'si', $username, $id);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            if (strlen($password) >= 8) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $statement = mysqli_prepare(
                    $conn,
                    'UPDATE login l INNER JOIN dosen d ON d.id_login=l.id_login SET l.password=? WHERE d.id_dosen=?'
                );
                mysqli_stmt_bind_param($statement, 'si', $hash, $id);
                mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);
            }
        }
        mysqli_commit($conn);
        apiResponse('success', ['status' => 'success', 'message' => 'Data dosen berhasil disimpan']);
    }

    if ($aksi === 'tahun_simpan') {
        $idValue = $payload['id_tahun_akademik'] ?? null;
        $id = $idValue === null || $idValue === ''
            ? null
            : filter_var($idValue, FILTER_VALIDATE_INT);
        $tahun = is_string($payload['tahun'] ?? null) ? trim($payload['tahun']) : '';
        $semester = $payload['semester'] ?? '';
        $activeValue = $payload['aktif'] ?? false;
        $activeValid = is_bool($activeValue) || in_array($activeValue, [0, 1, '0', '1'], true);
        if (!preg_match('/^[0-9]{4}\/[0-9]{4}$/', $tahun)
            || !in_array($semester, ['Ganjil', 'Genap'], true)
            || !$activeValid
            || ($id !== null && ($id === false || $id < 1))) {
            apiResponse('error', ['status' => 'error', 'message' => 'Tahun atau semester tidak valid'], 400);
        }
        $aktif = in_array($activeValue, [true, 1, '1'], true) ? 1 : 0;
        mysqli_begin_transaction($conn);
        if ($id !== null) {
            $exists = mysqli_prepare(
                $conn,
                'SELECT id_tahun_akademik FROM tahun_akademik WHERE id_tahun_akademik=? FOR UPDATE'
            );
            mysqli_stmt_bind_param($exists, 'i', $id);
            mysqli_stmt_execute($exists);
            mysqli_stmt_bind_result($exists, $existingId);
            $found = mysqli_stmt_fetch($exists);
            mysqli_stmt_close($exists);
            if (!$found) {
                mysqli_rollback($conn);
                apiResponse('error', ['status' => 'error', 'message' => 'Tahun akademik tidak ditemukan'], 404);
            }
        }
        if ($aktif === 1) {
            $statement = mysqli_prepare($conn, 'UPDATE tahun_akademik SET aktif=0');
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        }
        if ($id === null) {
            $statement = mysqli_prepare(
                $conn,
                'INSERT INTO tahun_akademik(tahun,semester,aktif) VALUES(?,?,?)'
            );
            mysqli_stmt_bind_param($statement, 'ssi', $tahun, $semester, $aktif);
        } elseif ($aktif === 1) {
            $statement = mysqli_prepare(
                $conn,
                'UPDATE tahun_akademik SET tahun=?, semester=?, aktif=1 WHERE id_tahun_akademik=?'
            );
            mysqli_stmt_bind_param($statement, 'ssi', $tahun, $semester, $id);
        } else {
            $statement = mysqli_prepare(
                $conn,
                'UPDATE tahun_akademik SET tahun=?, semester=? WHERE id_tahun_akademik=?'
            );
            mysqli_stmt_bind_param($statement, 'ssi', $tahun, $semester, $id);
        }
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        mysqli_commit($conn);
        apiResponse('success', [
            'status' => 'success',
            'message' => $id === null ? 'Tahun akademik berhasil ditambahkan' : 'Tahun akademik berhasil diperbarui',
        ]);
    }

    if ($aksi === 'tahun_aktifkan') {
        $id = filter_var($payload['id_tahun_akademik'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            apiResponse('error', ['status' => 'error', 'message' => 'Tahun akademik tidak valid'], 400);
        }
        mysqli_begin_transaction($conn);
        $statement = mysqli_prepare(
            $conn,
            'SELECT id_tahun_akademik FROM tahun_akademik WHERE id_tahun_akademik=? FOR UPDATE'
        );
        mysqli_stmt_bind_param($statement, 'i', $id);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $existingId);
        $found = mysqli_stmt_fetch($statement);
        mysqli_stmt_close($statement);
        if (!$found) {
            mysqli_rollback($conn);
            apiResponse('error', ['status' => 'error', 'message' => 'Tahun akademik tidak ditemukan'], 404);
        }
        $statement = mysqli_prepare($conn, 'UPDATE tahun_akademik SET aktif=0');
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        $statement = mysqli_prepare(
            $conn,
            'UPDATE tahun_akademik SET aktif=1 WHERE id_tahun_akademik=?'
        );
        mysqli_stmt_bind_param($statement, 'i', $id);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        mysqli_commit($conn);
        apiResponse('success', ['status' => 'success', 'message' => 'Tahun akademik berhasil diaktifkan']);
    }

    if ($aksi === 'mata_simpan') {
        $id = filter_var($payload['id_mata_kuliah'] ?? null, FILTER_VALIDATE_INT);
        $kode = is_string($payload['kode'] ?? null) ? trim($payload['kode']) : '';
        $nama = is_string($payload['nama'] ?? null) ? trim($payload['nama']) : '';
        $sks = filter_var($payload['sks'] ?? null, FILTER_VALIDATE_INT);
        $semester = filter_var($payload['semester'] ?? null, FILTER_VALIDATE_INT);
        if ($kode === '' || $nama === '' || $sks === false || $sks < 1 || $sks > 12
            || $semester === false || $semester < 1 || $semester > 14) {
            apiResponse('error', ['status' => 'error', 'message' => 'Data mata kuliah tidak valid'], 400);
        }
        if ($id === false) {
            $statement = mysqli_prepare(
                $conn,
                'INSERT INTO mata_kuliah(kode,nama,sks,semester) VALUES(?,?,?,?)'
            );
        } else {
            $statement = mysqli_prepare(
                $conn,
                'UPDATE mata_kuliah SET kode=?, nama=?, sks=?, semester=? WHERE id_mata_kuliah=?'
            );
        }
        if ($id === false) {
            mysqli_stmt_bind_param($statement, 'ssii', $kode, $nama, $sks, $semester);
        } else {
            mysqli_stmt_bind_param($statement, 'ssiii', $kode, $nama, $sks, $semester, $id);
        }
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        apiResponse('success', ['status' => 'success', 'message' => 'Mata kuliah berhasil disimpan']);
    }

    if ($aksi === 'kelas_simpan') {
        $id = filter_var($payload['id_kelas'] ?? null, FILTER_VALIDATE_INT);
        $idCourse = filter_var($payload['id_mata_kuliah'] ?? null, FILTER_VALIDATE_INT);
        $idLecturer1 = filter_var($payload['id_dosen_1'] ?? null, FILTER_VALIDATE_INT);
        $lecturer2Value = $payload['id_dosen_2'] ?? '';
        $idLecturer2 = $lecturer2Value === '' || $lecturer2Value === null
            ? null
            : (is_string($lecturer2Value) || is_int($lecturer2Value)
                ? filter_var($lecturer2Value, FILTER_VALIDATE_INT)
                : false);
        $idYear = filter_var($payload['id_tahun_akademik'] ?? null, FILTER_VALIDATE_INT);
        $nama = is_string($payload['nama_kelas'] ?? null) ? trim($payload['nama_kelas']) : '';
        $hari = $payload['hari'] ?? '';
        $mulai = is_string($payload['jam_mulai'] ?? null) ? trim($payload['jam_mulai']) : '';
        $selesai = is_string($payload['jam_selesai'] ?? null) ? trim($payload['jam_selesai']) : '';
        $ruangan = is_string($payload['ruangan'] ?? null) ? trim($payload['ruangan']) : '';
        if ($idCourse === false || $idCourse < 1 || $idLecturer1 === false || $idLecturer1 < 1
            || $idLecturer2 === false || ($idLecturer2 !== null && $idLecturer2 < 1)
            || $idYear === false || $idYear < 1 || ($id !== false && $id < 1)
            || ($idLecturer2 !== null && $idLecturer1 === $idLecturer2)
            || $nama === '' || $ruangan === ''
            || !in_array($hari, ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'], true)
            || !preg_match('/^[0-9]{2}:[0-9]{2}$/', $mulai)
            || !preg_match('/^[0-9]{2}:[0-9]{2}$/', $selesai)
            || $selesai <= $mulai) {
            apiResponse('error', ['status' => 'error', 'message' => 'Data kelas atau jadwal tidak valid'], 400);
        }

        foreach (array_filter([$idLecturer1, $idLecturer2], static function ($value) {
            return $value !== null;
        }) as $lecturerId) {
            $lecturerStatement = mysqli_prepare(
                $conn,
                'SELECT COUNT(*) FROM dosen WHERE id_dosen = ? AND status_akun = \'Aktif\''
            );
            mysqli_stmt_bind_param($lecturerStatement, 'i', $lecturerId);
            mysqli_stmt_execute($lecturerStatement);
            mysqli_stmt_bind_result($lecturerStatement, $activeLecturer);
            mysqli_stmt_fetch($lecturerStatement);
            mysqli_stmt_close($lecturerStatement);
            if ((int) $activeLecturer !== 1) {
                apiResponse('error', ['status' => 'error', 'message' => 'Pilih dosen aktif yang terdaftar'], 400);
            }
        }

        mysqli_begin_transaction($conn);
        if ($id === false) {
            $statement = mysqli_prepare(
                $conn,
                'INSERT INTO kelas_kuliah
                 (id_mata_kuliah,id_tahun_akademik,nama_kelas,hari,jam_mulai,jam_selesai,ruangan)
                 VALUES(?,?,?,?,?,?,?)'
            );
            mysqli_stmt_bind_param(
                $statement,
                'iisssss',
                $idCourse,
                $idYear,
                $nama,
                $hari,
                $mulai,
                $selesai,
                $ruangan
            );
            mysqli_stmt_execute($statement);
            $classId = mysqli_insert_id($conn);
            mysqli_stmt_close($statement);
        } else {
            $existsStatement = mysqli_prepare(
                $conn,
                'SELECT id_kelas FROM kelas_kuliah WHERE id_kelas=? FOR UPDATE'
            );
            mysqli_stmt_bind_param($existsStatement, 'i', $id);
            mysqli_stmt_execute($existsStatement);
            mysqli_stmt_bind_result($existsStatement, $existingClassId);
            $classExists = mysqli_stmt_fetch($existsStatement);
            mysqli_stmt_close($existsStatement);
            if (!$classExists) {
                apiResponse('error', ['status' => 'error', 'message' => 'Kelas tidak ditemukan'], 404);
            }

            $statement = mysqli_prepare(
                $conn,
                'UPDATE kelas_kuliah
                 SET id_mata_kuliah=?, id_tahun_akademik=?, nama_kelas=?,
                     hari=?, jam_mulai=?, jam_selesai=?, ruangan=? WHERE id_kelas=?'
            );
            mysqli_stmt_bind_param(
                $statement,
                'iisssssi',
                $idCourse,
                $idYear,
                $nama,
                $hari,
                $mulai,
                $selesai,
                $ruangan,
                $id
            );
            mysqli_stmt_execute($statement);
            $classId = $id;
            mysqli_stmt_close($statement);
        }

        $statement = mysqli_prepare($conn, 'DELETE FROM kelas_pengajar WHERE id_kelas=?');
        mysqli_stmt_bind_param($statement, 'i', $classId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO kelas_pengajar (id_kelas, id_dosen, urutan) VALUES (?, ?, ?)'
        );
        $order = 1;
        mysqli_stmt_bind_param($statement, 'iii', $classId, $idLecturer1, $order);
        mysqli_stmt_execute($statement);
        if ($idLecturer2 !== null) {
            $order = 2;
            mysqli_stmt_bind_param($statement, 'iii', $classId, $idLecturer2, $order);
            mysqli_stmt_execute($statement);
        }
        mysqli_stmt_close($statement);
        mysqli_commit($conn);
        apiResponse('success', ['status' => 'success', 'message' => 'Kelas dan jadwal berhasil disimpan']);
    }

    if ($aksi === 'peserta_simpan') {
        $classId = filter_var($payload['id_kelas'] ?? null, FILTER_VALIDATE_INT);
        $studentIds = $payload['id_mahasiswa'] ?? null;
        if ($classId === false || !is_array($studentIds)) {
            apiResponse('error', ['status' => 'error', 'message' => 'Peserta kelas tidak valid'], 400);
        }
        mysqli_begin_transaction($conn);
        $statement = mysqli_prepare($conn, 'DELETE FROM peserta_kelas WHERE id_kelas=?');
        mysqli_stmt_bind_param($statement, 'i', $classId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        $insert = mysqli_prepare($conn, 'INSERT IGNORE INTO peserta_kelas(id_kelas,id_mahasiswa) VALUES(?,?)');
        foreach ($studentIds as $studentId) {
            $studentId = filter_var($studentId, FILTER_VALIDATE_INT);
            if ($studentId === false) continue;
            mysqli_stmt_bind_param($insert, 'ii', $classId, $studentId);
            mysqli_stmt_execute($insert);
        }
        mysqli_stmt_close($insert);
        mysqli_commit($conn);
        apiResponse('success', ['status' => 'success', 'message' => 'Peserta kelas berhasil diperbarui']);
    }

    if ($aksi === 'pengumuman_simpan') {
        $judul = trim($payload['judul'] ?? '');
        $isi = trim($payload['isi'] ?? '');
        $id = filter_var($payload['id_pengumuman'] ?? null, FILTER_VALIDATE_INT);
        if ($judul === '' || $isi === '') {
            apiResponse('error', ['status' => 'error', 'message' => 'Judul dan isi pengumuman wajib diisi'], 400);
        }
        $adminUser = $_SESSION['user']['id'];
        if ($id === false) {
            $statement = mysqli_prepare(
                $conn,
                'INSERT INTO pengumuman(judul,isi,dibuat_oleh) VALUES(?,?,?)'
            );
            mysqli_stmt_bind_param($statement, 'ssi', $judul, $isi, $adminUser);
        } else {
            $statement = mysqli_prepare(
                $conn,
                'UPDATE pengumuman SET judul=?, isi=? WHERE id_pengumuman=?'
            );
            mysqli_stmt_bind_param($statement, 'ssi', $judul, $isi, $id);
        }
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        apiResponse('success', ['status' => 'success', 'message' => 'Pengumuman berhasil disimpan']);
    }

    if ($aksi === 'pengumuman_hapus') {
        $id = filter_var($payload['id_pengumuman'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false) apiResponse('error', ['status' => 'error', 'message' => 'Pengumuman tidak valid'], 400);
        $statement = mysqli_prepare($conn, 'DELETE FROM pengumuman WHERE id_pengumuman=?');
        mysqli_stmt_bind_param($statement, 'i', $id);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        apiResponse('success', ['status' => 'success', 'message' => 'Pengumuman berhasil dihapus']);
    }

    if ($aksi === 'pengaturan_simpan') {
        $threshold = filter_var($payload['ambang_wajah'] ?? null, FILTER_VALIDATE_FLOAT);
        $limit = filter_var($payload['batas_tepat_menit'] ?? null, FILTER_VALIDATE_INT);
        if ($threshold === false || $threshold < 0.1 || $threshold > 2.0
            || $limit === false || $limit < 1 || $limit > 180) {
            apiResponse('error', ['status' => 'error', 'message' => 'Pengaturan tidak valid'], 400);
        }
        $thresholdText = number_format((float) $threshold, 2, '.', '');
        $limitText = (string) $limit;
        foreach (['ambang_wajah' => $thresholdText, 'batas_tepat_menit' => $limitText] as $key => $value) {
            $statement = mysqli_prepare(
                $conn,
                'INSERT INTO pengaturan(kunci,nilai) VALUES(?,?)
                 ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)'
            );
            mysqli_stmt_bind_param($statement, 'ss', $key, $value);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        }
        apiResponse('success', ['status' => 'success', 'message' => 'Pengaturan berhasil disimpan']);
    }

    apiResponse('error', ['status' => 'error', 'message' => 'Aksi tidak valid'], 400);
} catch (mysqli_sql_exception $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        mysqli_rollback($conn);
    }
    if ((int) $error->getCode() === 1062) {
        $message = $error->getMessage();
        if (strpos($message, 'uq_mahasiswa_nim') !== false) {
            $message = 'NIM tersebut sudah terdaftar.';
        } elseif (strpos($message, 'uq_login_username') !== false) {
            $message = 'Username tersebut sudah digunakan.';
        } elseif (strpos($message, 'uq_tahun_semester') !== false) {
            $message = 'Tahun akademik dan semester tersebut sudah terdaftar.';
        } else {
            $message = 'Data NIM atau username sudah terdaftar.';
        }
        apiResponse('error', ['status' => 'error', 'message' => $message], 409);
    }
    error_log('Admin API database error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'Data admin tidak dapat diproses'], 500);
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        mysqli_rollback($conn);
    }
    error_log('Admin API error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'Data admin tidak dapat diproses'], 500);
}
