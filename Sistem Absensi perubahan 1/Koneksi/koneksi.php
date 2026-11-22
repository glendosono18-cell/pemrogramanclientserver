<?php
date_default_timezone_set('Asia/Makassar');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = 'localhost';
$dbUser = 'root';
$dbPassword = '';
$dbName = 'absensi';

$conn = mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName);
mysqli_set_charset($conn, 'utf8mb4');
mysqli_query($conn, "SET time_zone = '+08:00'");
