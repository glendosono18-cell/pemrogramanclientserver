<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiResponse('error', ['status' => 'error', 'message' => 'Metode tidak diizinkan'], 405);
}

$user = requireLogin();
if (($user['role'] ?? '') === 'dosen') {
    try {
        require_once __DIR__ . '/../Koneksi/koneksi.php';
        requireActiveDosen($conn, $user);
    } catch (Throwable $error) {
        error_log('Validasi sesi dosen error: ' . $error->getMessage());
        apiResponse('error', ['status' => 'error', 'message' => 'Sesi dosen tidak dapat divalidasi'], 500);
    }
}
apiResponse('success', [
    'status' => 'success',
    'username' => $user['username'],
    'role' => $user['role'],
]);
