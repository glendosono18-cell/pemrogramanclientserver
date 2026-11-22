<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiResponse('error', ['status' => 'error', 'message' => 'Metode tidak diizinkan'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    apiResponse('error', ['status' => 'error', 'message' => 'Format data tidak valid'], 400);
}

$fields = ['nidn', 'nama_lengkap', 'prodi', 'provinsi', 'kota', 'kecamatan', 'kelurahan', 'username', 'password'];
foreach ($fields as $field) {
    if (!isset($payload[$field]) || !is_string($payload[$field])) {
        apiResponse('error', ['status' => 'error', 'message' => 'Data pendaftaran tidak lengkap'], 400);
    }
}

$nidn = trim($payload['nidn']);
$namaLengkap = trim($payload['nama_lengkap']);
$prodi = trim($payload['prodi']);
$provinsi = trim($payload['provinsi']);
$kota = trim($payload['kota']);
$kecamatan = trim($payload['kecamatan']);
$kelurahan = trim($payload['kelurahan']);
$username = trim($payload['username']);
$password = $payload['password'];

if ($nidn === '' || $namaLengkap === '' || $prodi === '' || $provinsi === '' || $kota === '' || $kecamatan === '' || $kelurahan === '' || $username === '') {
    apiResponse('error', ['status' => 'error', 'message' => 'Semua data identitas wajib diisi'], 400);
}
if (strlen($nidn) > 30 || strlen($namaLengkap) > 150
    || strlen($prodi) > 100 || strlen($provinsi) > 100 || strlen($kota) > 100 || strlen($kecamatan) > 100 || strlen($kelurahan) > 100 || strlen($username) > 100) {
    apiResponse('error', ['status' => 'error', 'message' => 'Salah satu data identitas terlalu panjang'], 400);
}
if (strlen($password) < 8) {
    apiResponse('error', ['status' => 'error', 'message' => 'Kata sandi minimal 8 karakter'], 400);
}
if (strlen($password) > 255) {
    apiResponse('error', ['status' => 'error', 'message' => 'Kata sandi maksimal 255 karakter'], 400);
}

try {
    require_once __DIR__ . '/../Koneksi/koneksi.php';
    mysqli_begin_transaction($conn);

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'dosen';
    $statement = mysqli_prepare(
        $conn,
        'INSERT INTO login (username, password, role) VALUES (?, ?, ?)'
    );
    mysqli_stmt_bind_param($statement, 'sss', $username, $passwordHash, $role);
    mysqli_stmt_execute($statement);
    $loginId = mysqli_insert_id($conn);
    mysqli_stmt_close($statement);

    $status = 'Menunggu';
    $statement = mysqli_prepare(
        $conn,
        'INSERT INTO dosen (id_login, nidn, nama_lengkap, prodi, provinsi, kota, kecamatan, kelurahan, status_akun)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    mysqli_stmt_bind_param($statement, 'issssssss', $loginId, $nidn, $namaLengkap, $prodi, $provinsi, $kota, $kecamatan, $kelurahan, $status);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    mysqli_commit($conn);
    apiResponse('success', [
        'status' => 'success',
        'message' => 'Pendaftaran dosen berhasil dan menunggu persetujuan admin',
    ], 201);
} catch (mysqli_sql_exception $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        mysqli_rollback($conn);
    }
    if ((int) $error->getCode() === 1062) {
        apiResponse('error', [
            'status' => 'error',
            'message' => 'NIDN atau username sudah terdaftar',
        ], 409);
    }
    error_log('Pendaftaran dosen database error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'Pendaftaran dosen tidak dapat diproses'], 500);
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        mysqli_rollback($conn);
    }
    error_log('Pendaftaran dosen error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'Pendaftaran dosen tidak dapat diproses'], 500);
}
