<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Makassar');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function apiResponse($status, array $payload, $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function requireLogin()
{
    if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
        apiResponse('error', [
            'status' => 'error',
            'message' => 'Silakan masuk terlebih dahulu',
        ], 401);
    }

    return $_SESSION['user'];
}

function requireRole($role)
{
    $user = requireLogin();

    if (($user['role'] ?? '') !== $role) {
        apiResponse('error', [
            'status' => 'error',
            'message' => 'Akses tidak diizinkan',
        ], 403);
    }

    return $user;
}

function requireActiveDosen($conn, array $user)
{
    $statement = mysqli_prepare(
        $conn,
        'SELECT status_akun FROM dosen WHERE id_login = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 'i', $user['id']);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $status);
    $found = mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);

    if (!$found) {
        apiResponse('error', ['status' => 'error', 'message' => 'Data dosen tidak ditemukan'], 404);
    }
    if ($status !== 'Aktif') {
        $message = $status === 'Menunggu'
            ? 'Akun dosen Anda menunggu persetujuan admin'
            : 'Pendaftaran akun dosen Anda ditolak. Hubungi admin.';
        apiResponse('error', ['status' => 'error', 'message' => $message], 403);
    }
}

function academicYearOptions($conn)
{
    $statement = mysqli_prepare(
        $conn,
        'SELECT id_tahun_akademik, tahun, semester, aktif
         FROM tahun_akademik ORDER BY tahun DESC, semester DESC'
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $id, $year, $semester, $active);
    $years = [];
    while (mysqli_stmt_fetch($statement)) {
        $years[] = [
            'id_tahun_akademik' => (int) $id,
            'tahun' => $year,
            'semester' => $semester,
            'aktif' => (int) $active,
        ];
    }
    mysqli_stmt_close($statement);

    return $years;
}

function resolveAcademicYearId($conn, $requestedId)
{
    if ($requestedId === null || $requestedId === '') {
        $statement = mysqli_prepare(
            $conn,
            'SELECT id_tahun_akademik FROM tahun_akademik WHERE aktif = 1
             ORDER BY id_tahun_akademik DESC LIMIT 1'
        );
    } else {
        if (!is_string($requestedId) && !is_int($requestedId)) {
            apiResponse('error', ['status' => 'error', 'message' => 'Tahun akademik tidak valid'], 400);
        }
        $id = filter_var($requestedId, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            apiResponse('error', ['status' => 'error', 'message' => 'Tahun akademik tidak valid'], 400);
        }
        $statement = mysqli_prepare(
            $conn,
            'SELECT id_tahun_akademik FROM tahun_akademik WHERE id_tahun_akademik = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($statement, 'i', $id);
    }

    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $yearId);
    $found = mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);
    if (!$found) {
        apiResponse('error', ['status' => 'error', 'message' => 'Tahun akademik tidak ditemukan'], 404);
    }

    return (int) $yearId;
}
