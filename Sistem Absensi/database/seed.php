<?php
date_default_timezone_set('Asia/Makassar');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'absensi';

try {
    $conn = mysqli_connect($host, $user, $password, $database);
    mysqli_set_charset($conn, 'utf8mb4');
    mysqli_query($conn, "SET time_zone = '+08:00'");

    $statusColumn = mysqli_query($conn, "SHOW COLUMNS FROM dosen LIKE 'status_akun'");
    if (mysqli_num_rows($statusColumn) === 0) {
        mysqli_query(
            $conn,
            "ALTER TABLE dosen ADD COLUMN status_akun
             ENUM('Menunggu','Aktif','Ditolak') NOT NULL DEFAULT 'Aktif'"
        );
    }
    mysqli_free_result($statusColumn);

    mysqli_begin_transaction($conn);

    $adminUsername = 'admin';
    $adminPassword = 'Admin123!';
    $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);

    $statement = mysqli_prepare(
        $conn,
        'SELECT id_login FROM login WHERE username = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 's', $adminUsername);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $adminLoginId);
    $adminExists = mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);

    if (!$adminExists) {
        $role = 'admin';
        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO login (username, password, role) VALUES (?, ?, ?)'
        );
        mysqli_stmt_bind_param($statement, 'sss', $adminUsername, $passwordHash, $role);
        mysqli_stmt_execute($statement);
        $adminLoginId = mysqli_insert_id($conn);
        mysqli_stmt_close($statement);

        $adminName = 'Administrator Sistem';
        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO admin (id_login, nama_lengkap) VALUES (?, ?)'
        );
        mysqli_stmt_bind_param($statement, 'is', $adminLoginId, $adminName);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
    }

    $settings = [
        'ambang_wajah' => '0.5',
        'batas_tepat_menit' => '15',
    ];

    foreach ($settings as $key => $value) {
        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)'
        );
        mysqli_stmt_bind_param($statement, 'ss', $key, $value);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
    }

    mysqli_commit($conn);
    echo "Data awal berhasil dibuat.\n";
    echo "Admin: admin\n";
    echo "Kata sandi: Admin123!\n";
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        mysqli_rollback($conn);
    }
    fwrite(STDERR, "Seed gagal: " . $error->getMessage() . "\n");
    exit(1);
}
