<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiResponse('error', ['status' => 'error', 'message' => 'Metode tidak diizinkan'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    apiResponse('error', ['status' => 'error', 'message' => 'Format data tidak valid'], 400);
}

$stringFields = ['nim', 'nama_lengkap', 'prodi', 'provinsi', 'kota', 'kecamatan', 'kelurahan', 'username', 'password'];
foreach ($stringFields as $field) {
    if (!isset($payload[$field]) || !is_string($payload[$field])) {
        apiResponse('error', ['status' => 'error', 'message' => 'Data pendaftaran tidak lengkap'], 400);
    }
}

$nim = trim($payload['nim']);
$namaLengkap = trim($payload['nama_lengkap']);
$prodi = trim($payload['prodi']);
$provinsi = trim($payload['provinsi']);
$kota = trim($payload['kota']);
$kecamatan = trim($payload['kecamatan']);
$kelurahan = trim($payload['kelurahan']);
$username = trim($payload['username']);
$password = $payload['password'];
$semester = filter_var($payload['semester'] ?? null, FILTER_VALIDATE_INT);
$consent = $payload['consent'] ?? false;
$descriptors = $payload['descriptors'] ?? null;

if ($nim === '' || $namaLengkap === '' || $prodi === '' || $provinsi === '' || $kota === '' || $kecamatan === '' || $kelurahan === '' || $username === '') {
    apiResponse('error', ['status' => 'error', 'message' => 'Semua data identitas wajib diisi'], 400);
}
if (strlen($nim) > 30 || strlen($namaLengkap) > 150 || strlen($prodi) > 100 || strlen($provinsi) > 100 || strlen($kota) > 100 || strlen($kecamatan) > 100 || strlen($kelurahan) > 100 || strlen($username) > 100) {
    apiResponse('error', ['status' => 'error', 'message' => 'Salah satu data identitas terlalu panjang'], 400);
}
if (strlen($password) < 8) {
    apiResponse('error', ['status' => 'error', 'message' => 'Kata sandi minimal 8 karakter'], 400);
}
if (strlen($password) > 255) {
    apiResponse('error', ['status' => 'error', 'message' => 'Kata sandi maksimal 255 karakter'], 400);
}
if ($semester === false || $semester < 1 || $semester > 14) {
    apiResponse('error', ['status' => 'error', 'message' => 'Semester harus berupa angka dari 1 sampai 14'], 400);
}
if ($consent !== true) {
    apiResponse('error', ['status' => 'error', 'message' => 'Persetujuan penggunaan data wajah wajib diberikan'], 400);
}

$validDescriptorPoses = ['depan', 'kiri', 'kanan'];
if (!is_array($descriptors)) {
    apiResponse('error', ['status' => 'error', 'message' => 'Data wajah wajib direkam'], 400);
}
foreach ($validDescriptorPoses as $pose) {
    if (!isset($descriptors[$pose]) || !is_array($descriptors[$pose]) || count($descriptors[$pose]) !== 128) {
        apiResponse('error', ['status' => 'error', 'message' => 'Setiap pose wajah harus berisi 128 angka'], 400);
    }
    foreach ($descriptors[$pose] as $value) {
        if (!is_numeric($value) || !is_finite((float) $value)) {
            apiResponse('error', ['status' => 'error', 'message' => 'Descriptor wajah tidak valid'], 400);
        }
    }
}

try {
    require_once __DIR__ . '/../Koneksi/koneksi.php';
    mysqli_begin_transaction($conn);

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'mahasiswa';
    $statement = mysqli_prepare(
        $conn,
        'INSERT INTO login (username, password, role) VALUES (?, ?, ?)'
    );
    mysqli_stmt_bind_param($statement, 'sss', $username, $passwordHash, $role);
    mysqli_stmt_execute($statement);
    $loginId = mysqli_insert_id($conn);
    mysqli_stmt_close($statement);

    $qrToken = bin2hex(random_bytes(32));
    $statement = mysqli_prepare(
        $conn,
        'INSERT INTO mahasiswa (id_login, nim, nama_lengkap, prodi, semester, provinsi, kota, kecamatan, kelurahan, qr_token)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    mysqli_stmt_bind_param(
        $statement,
        'isssisssss',
        $loginId,
        $nim,
        $namaLengkap,
        $prodi,
        $semester,
        $provinsi,
        $kota,
        $kecamatan,
        $kelurahan,
        $qrToken
    );
    mysqli_stmt_execute($statement);
    $mahasiswaId = mysqli_insert_id($conn);
    mysqli_stmt_close($statement);

    $statement = mysqli_prepare(
        $conn,
        'INSERT IGNORE INTO peserta_kelas (id_kelas, id_mahasiswa)
         SELECT k.id_kelas, ?
         FROM kelas_kuliah k
         INNER JOIN mata_kuliah mk ON mk.id_mata_kuliah = k.id_mata_kuliah
         INNER JOIN tahun_akademik ta ON ta.id_tahun_akademik = k.id_tahun_akademik
         WHERE ta.aktif = 1 AND mk.semester = ?'
    );
    mysqli_stmt_bind_param($statement, 'ii', $mahasiswaId, $semester);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    foreach ($validDescriptorPoses as $pose) {
        $descriptorJson = json_encode(
            array_map('floatval', $descriptors[$pose]),
            JSON_PRESERVE_ZERO_FRACTION
        );
        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO wajah_mahasiswa (id_mahasiswa, pose, descriptor)
             VALUES (?, ?, ?)'
        );
        mysqli_stmt_bind_param($statement, 'iss', $mahasiswaId, $pose, $descriptorJson);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
    }

    mysqli_commit($conn);
    apiResponse('success', [
        'status' => 'success',
        'message' => 'Pendaftaran mahasiswa berhasil',
    ], 201);
} catch (mysqli_sql_exception $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        mysqli_rollback($conn);
    }
    if ((int) $error->getCode() === 1062) {
        apiResponse('error', [
            'status' => 'error',
            'message' => 'NIM atau username sudah terdaftar',
        ], 409);
    }
    error_log('Pendaftaran database error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'Pendaftaran tidak dapat diproses'], 500);
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        mysqli_rollback($conn);
    }
    error_log('Pendaftaran error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'Pendaftaran tidak dapat diproses'], 500);
}
