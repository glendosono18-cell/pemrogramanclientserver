<?php
date_default_timezone_set('Asia/Makassar');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = 'rnbqd9.h.filess.io';
$dbUser = 'Sistem absensi_beganpanbe';
$dbPassword = '0f805d236287b951a2d345c73c542f38debb533b';
$dbName = 'Sistem absensi_beganpanbe';
$dbPort = 3307;

$conn = mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName, $dbPort);
mysqli_set_charset($conn, 'utf8mb4');
mysqli_query($conn, "SET time_zone = '+08:00'");