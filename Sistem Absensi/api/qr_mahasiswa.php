<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiResponse('error', ['status' => 'error', 'message' => 'Metode tidak diizinkan'], 405);
}

$user = requireRole('mahasiswa');

try {
    require_once __DIR__ . '/../Koneksi/koneksi.php';
    $statement = mysqli_prepare(
        $conn,
        'SELECT m.nim, m.nama_lengkap, m.prodi, m.semester, m.qr_token
         FROM mahasiswa m
         WHERE m.id_login = ?
         LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 'i', $user['id']);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result(
        $statement,
        $nim,
        $namaLengkap,
        $prodi,
        $semester,
        $qrToken
    );

    if (!mysqli_stmt_fetch($statement)) {
        mysqli_stmt_close($statement);
        apiResponse('error', ['status' => 'error', 'message' => 'Data mahasiswa tidak ditemukan'], 404);
    }
    mysqli_stmt_close($statement);

    apiResponse('success', [
        'status' => 'success',
        'mahasiswa' => [
            'nim' => $nim,
            'nama_lengkap' => $namaLengkap,
            'prodi' => $prodi,
            'semester' => (int) $semester,
        ],
        'qr_content' => 'ABSENSI:' . $qrToken,
    ]);
} catch (Throwable $error) {
    error_log('QR mahasiswa database error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'QR mahasiswa tidak dapat dimuat'], 500);
}
