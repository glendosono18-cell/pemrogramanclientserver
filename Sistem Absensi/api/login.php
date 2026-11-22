<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiResponse('error', ['status' => 'error', 'message' => 'Metode tidak diizinkan'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true);
$username = is_array($payload) && isset($payload['username']) && is_string($payload['username'])
    ? trim($payload['username'])
    : '';
$password = is_array($payload) && isset($payload['password']) && is_string($payload['password'])
    ? $payload['password']
    : '';
$selectedRole = is_array($payload) && isset($payload['role']) && is_string($payload['role'])
    ? trim($payload['role'])
    : '';
$allowedRoles = ['mahasiswa', 'dosen', 'admin'];
$failure = ['status' => 'error', 'message' => 'Username, kata sandi, atau peran salah'];

if ($username === '' || $password === '' || !in_array($selectedRole, $allowedRoles, true)) {
    apiResponse('error', $failure, 401);
}

try {
    require_once __DIR__ . '/../Koneksi/koneksi.php';

    $statement = mysqli_prepare(
        $conn,
        'SELECT id_login, username, password, role, aktif FROM login WHERE username = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 's', $username);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $userId, $dbUsername, $storedPassword, $dbRole, $active);
    $userFound = mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);

    if (!$userFound && $selectedRole === 'mahasiswa') {
        $statement = mysqli_prepare(
            $conn,
            'SELECT l.id_login, l.username, l.password, l.role, l.aktif
             FROM login l
             INNER JOIN mahasiswa m ON m.id_login = l.id_login
             WHERE m.nim = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($statement, 's', $username);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $userId, $dbUsername, $storedPassword, $dbRole, $active);
        $userFound = mysqli_stmt_fetch($statement);
        mysqli_stmt_close($statement);
    } elseif (!$userFound && $selectedRole === 'dosen') {
        $statement = mysqli_prepare(
            $conn,
            'SELECT l.id_login, l.username, l.password, l.role, l.aktif
             FROM login l
             INNER JOIN dosen d ON d.id_login = l.id_login
             WHERE d.nidn = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($statement, 's', $username);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $userId, $dbUsername, $storedPassword, $dbRole, $active);
        $userFound = mysqli_stmt_fetch($statement);
        mysqli_stmt_close($statement);
    }

    if (!$userFound || (int) $active !== 1 || $dbRole !== $selectedRole
        || !password_verify($password, (string) $storedPassword)) {
        apiResponse('error', $failure, 401);
    }

    if ($dbRole === 'dosen') {
        $statement = mysqli_prepare(
            $conn,
            'SELECT status_akun FROM dosen WHERE id_login = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($statement, 'i', $userId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $lecturerStatus);
        $lecturerFound = mysqli_stmt_fetch($statement);
        mysqli_stmt_close($statement);

        if (!$lecturerFound) {
            apiResponse('error', $failure, 401);
        }
        if ($lecturerStatus !== 'Aktif') {
            $message = $lecturerStatus === 'Menunggu'
                ? 'Akun dosen Anda menunggu persetujuan admin'
                : 'Pendaftaran akun dosen Anda ditolak. Hubungi admin.';
            apiResponse('error', ['status' => 'error', 'message' => $message], 403);
        }
    }

    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $userId,
        'username' => $dbUsername,
        'role' => $dbRole,
    ];

    $redirects = [
        'mahasiswa' => 'Beranda/dashboard_mahasiswa.html',
        'dosen' => 'Beranda/dashboard_dosen.html',
        'admin' => 'Beranda/dashboard_admin.html',
    ];

    apiResponse('success', [
        'status' => 'success',
        'user' => ['username' => $dbUsername, 'role' => $dbRole],
        'redirect' => $redirects[$dbRole],
    ]);
} catch (Throwable $error) {
    error_log('Login database error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'Layanan login tidak tersedia'], 500);
}
