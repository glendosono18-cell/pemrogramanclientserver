<?php
require_once __DIR__ . '/auth.php';

$user = requireLogin();
$role = $user['role'] ?? '';
if (!in_array($role, ['mahasiswa', 'dosen', 'admin'], true)) {
    apiResponse('error', ['status' => 'error', 'message' => 'Akses tidak diizinkan'], 403);
}
requireRole($role);

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    apiResponse('error', ['status' => 'error', 'message' => 'Metode tidak diizinkan'], 405);
}

function chatMahasiswaId($conn, array $user)
{
    $statement = mysqli_prepare($conn, 'SELECT id_mahasiswa FROM mahasiswa WHERE id_login=? LIMIT 1');
    mysqli_stmt_bind_param($statement, 'i', $user['id']);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $studentId);
    $found = mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);
    if (!$found) {
        apiResponse('error', ['status' => 'error', 'message' => 'Data mahasiswa tidak ditemukan'], 404);
    }

    return (int) $studentId;
}

function chatDosenId($conn, array $user)
{
    requireActiveDosen($conn, $user);
    $statement = mysqli_prepare($conn, 'SELECT id_dosen FROM dosen WHERE id_login=? LIMIT 1');
    mysqli_stmt_bind_param($statement, 'i', $user['id']);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $lecturerId);
    $found = mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);
    if (!$found) {
        apiResponse('error', ['status' => 'error', 'message' => 'Data dosen tidak ditemukan'], 404);
    }

    return (int) $lecturerId;
}

function handleChatAttachment()
{
    if (!isset($_FILES['lampiran']) || !is_array($_FILES['lampiran'])) {
        return [null, null, null];
    }
    $file = $_FILES['lampiran'];
    $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return [null, null, null];
    }
    if ($errorCode !== UPLOAD_ERR_OK) {
        apiResponse('error', ['status' => 'error', 'message' => 'Gagal mengunggah file lampiran'], 400);
    }
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        apiResponse('error', ['status' => 'error', 'message' => 'Ukuran lampiran maksimal 5 MB'], 400);
    }

    $allowedExt = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip',
    ];
    $originalName = basename((string) ($file['name'] ?? 'lampiran'));
    $originalName = preg_replace('/[^a-zA-Z0-9._ -]/', '_', $originalName);
    if ($originalName === '' || $originalName === '.' || $originalName === '..') {
        $originalName = 'lampiran';
    }
    if (strlen($originalName) > 200) {
        $originalName = substr($originalName, -200);
    }
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        apiResponse('error', [
            'status' => 'error',
            'message' => 'Format lampiran tidak didukung (gunakan gambar, PDF, Office, TXT, atau ZIP)',
        ], 400);
    }

    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($file['tmp_name']);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }
    $blockedMimes = ['text/x-php', 'application/x-httpd-php', 'text/html', 'application/javascript'];
    if (in_array($mime, $blockedMimes, true)) {
        apiResponse('error', ['status' => 'error', 'message' => 'Jenis file lampiran tidak diizinkan'], 400);
    }

    $uploadDir = dirname(__DIR__) . '/uploads/chat';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        apiResponse('error', ['status' => 'error', 'message' => 'Folder lampiran tidak dapat dibuat'], 500);
    }
    $savedName = 'chat_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath = $uploadDir . '/' . $savedName;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        apiResponse('error', ['status' => 'error', 'message' => 'Lampiran tidak dapat disimpan'], 500);
    }

    return [$originalName, 'uploads/chat/' . $savedName, $mime];
}

try {
    require_once __DIR__ . '/../Koneksi/koneksi.php';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $section = $_GET['bagian'] ?? ($role === 'admin' ? 'daftar' : 'percakapan');
        $targetType = $role;
        $targetId = 0;

        if ($role === 'mahasiswa') {
            $targetId = chatMahasiswaId($conn, $user);
            if ($section === 'badge') {
                $statement = mysqli_prepare(
                    $conn,
                    "SELECT COUNT(*) FROM pesan_bantuan
                     WHERE id_mahasiswa=? AND pengirim='admin' AND dibaca=0"
                );
                mysqli_stmt_bind_param($statement, 'i', $targetId);
                mysqli_stmt_execute($statement);
                mysqli_stmt_bind_result($statement, $unreadCount);
                mysqli_stmt_fetch($statement);
                mysqli_stmt_close($statement);
                apiResponse('success', ['status' => 'success', 'belum_dibaca' => (int) $unreadCount]);
            }
            if ($section !== 'percakapan') {
                apiResponse('error', ['status' => 'error', 'message' => 'Bagian chat tidak valid'], 400);
            }
            $statement = mysqli_prepare(
                $conn,
                "UPDATE pesan_bantuan SET dibaca=1
                 WHERE id_mahasiswa=? AND pengirim='admin' AND dibaca=0"
            );
            mysqli_stmt_bind_param($statement, 'i', $targetId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        } elseif ($role === 'dosen') {
            $targetId = chatDosenId($conn, $user);
            if ($section === 'badge') {
                $statement = mysqli_prepare(
                    $conn,
                    "SELECT COUNT(*) FROM pesan_bantuan
                     WHERE id_dosen=? AND pengirim='admin' AND dibaca=0"
                );
                mysqli_stmt_bind_param($statement, 'i', $targetId);
                mysqli_stmt_execute($statement);
                mysqli_stmt_bind_result($statement, $unreadCount);
                mysqli_stmt_fetch($statement);
                mysqli_stmt_close($statement);
                apiResponse('success', ['status' => 'success', 'belum_dibaca' => (int) $unreadCount]);
            }
            if ($section !== 'percakapan') {
                apiResponse('error', ['status' => 'error', 'message' => 'Bagian chat tidak valid'], 400);
            }
            $statement = mysqli_prepare(
                $conn,
                "UPDATE pesan_bantuan SET dibaca=1
                 WHERE id_dosen=? AND pengirim='admin' AND dibaca=0"
            );
            mysqli_stmt_bind_param($statement, 'i', $targetId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        } elseif ($section === 'daftar') {
            $conversations = [];

            $statement = mysqli_prepare(
                $conn,
                "SELECT m.id_mahasiswa, m.nim, m.nama_lengkap, l.username,
                        (SELECT CASE
                                  WHEN p.isi <> '' THEN p.isi
                                  WHEN p.lampiran_nama IS NOT NULL THEN CONCAT('[Lampiran] ', p.lampiran_nama)
                                  ELSE ''
                                END
                         FROM pesan_bantuan p
                         WHERE p.id_mahasiswa=m.id_mahasiswa
                         ORDER BY p.id_pesan DESC LIMIT 1) AS pesan_terakhir,
                        (SELECT p.dibuat_pada FROM pesan_bantuan p
                         WHERE p.id_mahasiswa=m.id_mahasiswa
                         ORDER BY p.id_pesan DESC LIMIT 1) AS waktu_terakhir,
                        (SELECT COUNT(*) FROM pesan_bantuan p
                         WHERE p.id_mahasiswa=m.id_mahasiswa
                           AND p.pengirim='mahasiswa' AND p.dibaca=0) AS belum_dibaca
                 FROM mahasiswa m
                 INNER JOIN login l ON l.id_login=m.id_login
                 WHERE EXISTS (SELECT 1 FROM pesan_bantuan p WHERE p.id_mahasiswa=m.id_mahasiswa)"
            );
            mysqli_stmt_execute($statement);
            mysqli_stmt_bind_result(
                $statement, $idMahasiswa, $nim, $nama, $username, $lastMessage, $lastAt, $unreadCount
            );
            while (mysqli_stmt_fetch($statement)) {
                $conversations[] = [
                    'tipe' => 'mahasiswa',
                    'id_pengguna' => (int) $idMahasiswa,
                    'id_mahasiswa' => (int) $idMahasiswa,
                    'id_dosen' => null,
                    'nomor_induk' => $nim,
                    'nim' => $nim,
                    'nama_lengkap' => $nama,
                    'username' => $username,
                    'pesan_terakhir' => $lastMessage,
                    'waktu_terakhir' => $lastAt,
                    'belum_dibaca' => (int) $unreadCount,
                ];
            }
            mysqli_stmt_close($statement);

            $statement = mysqli_prepare(
                $conn,
                "SELECT d.id_dosen, d.nidn, d.nama_lengkap, l.username,
                        (SELECT CASE
                                  WHEN p.isi <> '' THEN p.isi
                                  WHEN p.lampiran_nama IS NOT NULL THEN CONCAT('[Lampiran] ', p.lampiran_nama)
                                  ELSE ''
                                END
                         FROM pesan_bantuan p
                         WHERE p.id_dosen=d.id_dosen
                         ORDER BY p.id_pesan DESC LIMIT 1) AS pesan_terakhir,
                        (SELECT p.dibuat_pada FROM pesan_bantuan p
                         WHERE p.id_dosen=d.id_dosen
                         ORDER BY p.id_pesan DESC LIMIT 1) AS waktu_terakhir,
                        (SELECT COUNT(*) FROM pesan_bantuan p
                         WHERE p.id_dosen=d.id_dosen
                           AND p.pengirim='dosen' AND p.dibaca=0) AS belum_dibaca
                 FROM dosen d
                 INNER JOIN login l ON l.id_login=d.id_login
                 WHERE EXISTS (SELECT 1 FROM pesan_bantuan p WHERE p.id_dosen=d.id_dosen)"
            );
            mysqli_stmt_execute($statement);
            mysqli_stmt_bind_result(
                $statement, $idDosen, $nidn, $nama, $username, $lastMessage, $lastAt, $unreadCount
            );
            while (mysqli_stmt_fetch($statement)) {
                $conversations[] = [
                    'tipe' => 'dosen',
                    'id_pengguna' => (int) $idDosen,
                    'id_mahasiswa' => null,
                    'id_dosen' => (int) $idDosen,
                    'nomor_induk' => $nidn,
                    'nidn' => $nidn,
                    'nama_lengkap' => $nama,
                    'username' => $username,
                    'pesan_terakhir' => $lastMessage,
                    'waktu_terakhir' => $lastAt,
                    'belum_dibaca' => (int) $unreadCount,
                ];
            }
            mysqli_stmt_close($statement);

            usort($conversations, static function ($a, $b) {
                $timeCmp = strcmp((string) ($b['waktu_terakhir'] ?? ''), (string) ($a['waktu_terakhir'] ?? ''));
                if ($timeCmp !== 0) {
                    return $timeCmp;
                }
                return strcmp((string) $a['nama_lengkap'], (string) $b['nama_lengkap']);
            });

            apiResponse('success', ['status' => 'success', 'percakapan' => $conversations]);
        } elseif ($section !== 'percakapan') {
            apiResponse('error', ['status' => 'error', 'message' => 'Bagian chat tidak valid'], 400);
        }

        if ($role === 'admin') {
            $reqType = $_GET['tipe'] ?? (isset($_GET['id_dosen']) ? 'dosen' : 'mahasiswa');
            if (!in_array($reqType, ['mahasiswa', 'dosen'], true)) {
                apiResponse('error', ['status' => 'error', 'message' => 'Tipe pengguna tidak valid'], 400);
            }
            $targetType = $reqType;
            $rawId = $_GET['id_pengguna'] ?? ($reqType === 'dosen' ? ($_GET['id_dosen'] ?? null) : ($_GET['id_mahasiswa'] ?? null));
            $targetId = filter_var($rawId, FILTER_VALIDATE_INT);
            if ($targetId === false || $targetId < 1) {
                apiResponse('error', ['status' => 'error', 'message' => 'Pengguna tidak valid'], 400);
            }
            if ($targetType === 'dosen') {
                $statement = mysqli_prepare(
                    $conn,
                    "UPDATE pesan_bantuan SET dibaca=1
                     WHERE id_dosen=? AND pengirim='dosen' AND dibaca=0"
                );
            } else {
                $statement = mysqli_prepare(
                    $conn,
                    "UPDATE pesan_bantuan SET dibaca=1
                     WHERE id_mahasiswa=? AND pengirim='mahasiswa' AND dibaca=0"
                );
            }
            mysqli_stmt_bind_param($statement, 'i', $targetId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        }

        $sinceId = filter_var($_GET['since_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($sinceId === false || $sinceId < 0) {
            apiResponse('error', ['status' => 'error', 'message' => 'Penanda pesan tidak valid'], 400);
        }
        $whereCol = $targetType === 'dosen' ? 'id_dosen' : 'id_mahasiswa';
        $statement = mysqli_prepare(
            $conn,
            "SELECT id_pesan, pengirim, isi, lampiran_nama, lampiran_path, lampiran_tipe, dibuat_pada
             FROM pesan_bantuan WHERE $whereCol=? AND id_pesan>?
             ORDER BY id_pesan ASC"
        );
        mysqli_stmt_bind_param($statement, 'ii', $targetId, $sinceId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result(
            $statement,
            $messageId,
            $sender,
            $message,
            $attachmentName,
            $attachmentPath,
            $attachmentType,
            $createdAt
        );
        $messages = [];
        while (mysqli_stmt_fetch($statement)) {
            $messages[] = [
                'id_pesan' => (int) $messageId,
                'pengirim' => $sender,
                'isi' => $message,
                'lampiran_nama' => $attachmentName,
                'lampiran_path' => $attachmentPath,
                'lampiran_tipe' => $attachmentType,
                'dibuat_pada' => $createdAt,
            ];
        }
        mysqli_stmt_close($statement);
        apiResponse('success', ['status' => 'success', 'pesan' => $messages]);
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $isMultipart = stripos($contentType, 'multipart/form-data') !== false;
    $payload = $isMultipart ? $_POST : json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload) || ($payload['aksi'] ?? '') !== 'kirim') {
        apiResponse('error', ['status' => 'error', 'message' => 'Format pesan tidak valid'], 400);
    }

    $message = is_string($payload['isi'] ?? null) ? trim($payload['isi']) : '';
    $messageLength = function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message);
    if ($messageLength > 1000) {
        apiResponse('error', ['status' => 'error', 'message' => 'Pesan maksimal 1000 karakter'], 400);
    }

    [$attachmentName, $attachmentPath, $attachmentType] = handleChatAttachment();
    if ($message === '' && $attachmentPath === null) {
        apiResponse('error', ['status' => 'error', 'message' => 'Pesan atau file lampiran wajib diisi'], 400);
    }

    $studentId = null;
    $lecturerId = null;
    if ($role === 'mahasiswa') {
        $studentId = chatMahasiswaId($conn, $user);
        $sender = 'mahasiswa';
    } elseif ($role === 'dosen') {
        $lecturerId = chatDosenId($conn, $user);
        $sender = 'dosen';
    } else {
        $reqType = $payload['tipe'] ?? (isset($payload['id_dosen']) ? 'dosen' : 'mahasiswa');
        if (!in_array($reqType, ['mahasiswa', 'dosen'], true)) {
            apiResponse('error', ['status' => 'error', 'message' => 'Tipe pengguna tidak valid'], 400);
        }
        $rawId = $payload['id_pengguna'] ?? ($reqType === 'dosen' ? ($payload['id_dosen'] ?? null) : ($payload['id_mahasiswa'] ?? null));
        $targetId = filter_var($rawId, FILTER_VALIDATE_INT);
        if ($targetId === false || $targetId < 1) {
            apiResponse('error', ['status' => 'error', 'message' => 'Pengguna tidak valid'], 400);
        }
        if ($reqType === 'dosen') {
            $lecturerId = (int) $targetId;
        } else {
            $studentId = (int) $targetId;
        }
        $sender = 'admin';
    }

    $rateCol = $lecturerId !== null ? 'id_dosen' : 'id_mahasiswa';
    $rateId = $lecturerId !== null ? $lecturerId : $studentId;
    $statement = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) FROM pesan_bantuan
         WHERE $rateCol=? AND pengirim=?
           AND dibuat_pada>=DATE_SUB(NOW(), INTERVAL 1 MINUTE)"
    );
    mysqli_stmt_bind_param($statement, 'is', $rateId, $sender);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $recentCount);
    mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);
    if ((int) $recentCount >= 10) {
        if ($attachmentPath !== null) {
            @unlink(dirname(__DIR__) . '/' . $attachmentPath);
        }
        apiResponse('error', ['status' => 'error', 'message' => 'Terlalu banyak pesan. Coba lagi sebentar.'], 429);
    }

    $statement = mysqli_prepare(
        $conn,
        'INSERT INTO pesan_bantuan
         (id_mahasiswa, id_dosen, pengirim, isi, lampiran_nama, lampiran_path, lampiran_tipe, dibaca)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0)'
    );
    mysqli_stmt_bind_param(
        $statement,
        'iisssss',
        $studentId,
        $lecturerId,
        $sender,
        $message,
        $attachmentName,
        $attachmentPath,
        $attachmentType
    );
    mysqli_stmt_execute($statement);
    $messageId = mysqli_insert_id($conn);
    mysqli_stmt_close($statement);
    apiResponse('success', [
        'status' => 'success',
        'message' => 'Pesan berhasil dikirim',
        'id_pesan' => (int) $messageId,
    ], 201);
} catch (Throwable $error) {
    error_log('Chat API error: ' . $error->getMessage());
    apiResponse('error', ['status' => 'error', 'message' => 'Chat tidak dapat diproses'], 500);
}