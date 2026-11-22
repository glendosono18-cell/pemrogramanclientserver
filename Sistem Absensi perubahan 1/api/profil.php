<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiResponse('error', ['status' => 'error', 'message' => 'Metode tidak diizinkan'], 405);
}

$user = requireLogin();
$role = $user['role'] ?? '';
$profile = null;

try {
    require_once __DIR__ . '/../Koneksi/koneksi.php';

    if ($role === 'mahasiswa') {
        $statement = mysqli_prepare(
            $conn,
            'SELECT nim, nama_lengkap, prodi, semester, provinsi, kota, kecamatan, kelurahan, foto_profil
             FROM mahasiswa WHERE id_login = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($statement, 'i', $user['id']);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result(
            $statement,
            $nim,
            $namaLengkap,
            $prodi,
            $semester,
            $provinsi,
            $kota,
            $kecamatan,
            $kelurahan,
            $fotoProfil
        );
        if (mysqli_stmt_fetch($statement)) {
            $profile = [
                'nim' => $nim,
                'nama_lengkap' => $namaLengkap,
                'prodi' => $prodi,
                'semester' => (int) $semester,
                'provinsi' => $provinsi,
                'kota' => $kota,
                'kecamatan' => $kecamatan,
                'kelurahan' => $kelurahan,
                'foto_profil' => $fotoProfil,
            ];
        }
        mysqli_stmt_close($statement);
    } elseif ($role === 'dosen') {
        requireActiveDosen($conn, $user);
        $statement = mysqli_prepare(
            $conn,
            'SELECT nidn, nama_lengkap, prodi, provinsi, kota, kecamatan, kelurahan, status_akun, dibuat_pada
             FROM dosen WHERE id_login = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($statement, 'i', $user['id']);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result(
            $statement,
            $nidn,
            $namaLengkap,
            $prodi,
            $provinsi,
            $kota,
            $kecamatan,
            $kelurahan,
            $statusAkun,
            $dibuatPada
        );
        if (mysqli_stmt_fetch($statement)) {
            $profile = [
                'nidn' => $nidn,
                'nama_lengkap' => $namaLengkap,
                'prodi' => $prodi,
                'username' => $user['username'] ?? '',
                'status_akun' => $statusAkun,
                'provinsi' => $provinsi,
                'kota' => $kota,
                'kecamatan' => $kecamatan,
                'kelurahan' => $kelurahan,
                'dibuat_pada' => $dibuatPada,
            ];
        }
        mysqli_stmt_close($statement);
    } elseif ($role === 'admin') {
        $statement = mysqli_prepare(
            $conn,
            'SELECT nama_lengkap FROM admin WHERE id_login = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($statement, 'i', $user['id']);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $namaLengkap);
        if (mysqli_stmt_fetch($statement)) {
            $profile = ['nama' => $namaLengkap];
        }
        mysqli_stmt_close($statement);
    } else {
        apiResponse('error', ['status' => 'error', 'message' => 'Akses tidak diizinkan'], 403);
    }
} catch (Throwable $error) {
    error_log('Profil database error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'Profil tidak dapat dimuat'], 500);
}

if ($profile === null) {
    apiResponse('error', ['status' => 'error', 'message' => 'Profil tidak ditemukan'], 404);
}

apiResponse('success', ['status' => 'success', 'role' => $role, 'profile' => $profile]);
