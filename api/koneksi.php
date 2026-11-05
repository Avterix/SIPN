<?php
// Koneksi database + session. Semua kredensial dibaca dari .env / Environment Variables.
require_once __DIR__ . '/env.php';

$host = env('DB_HOST');
$user = env('DB_USER');
$pass = env('DB_PASS', '');
$db   = env('DB_NAME');
$port = (int) env('DB_PORT', 3306);

if (!$host || !$user || !$db) {
    error_log('SIPN: variabel DB_HOST / DB_USER / DB_NAME belum diisi');
    http_response_code(500);
    exit('Konfigurasi server belum lengkap.');
}

$koneksi = false;
try {
    $koneksi = mysqli_connect($host, $user, $pass, $db, $port);
} catch (Throwable $e) {
    error_log('SIPN: koneksi DB gagal: ' . $e->getMessage());
}
if (!$koneksi) {
    http_response_code(500);
    exit('Layanan sedang bermasalah. Coba lagi nanti.');
}
mysqli_set_charset($koneksi, 'utf8mb4');

// Deteksi otomatis Base URL
$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (strpos($http_host, 'localhost') !== false || strpos($http_host, '127.0.0.1') !== false) {
    $base_url = 'http://' . $http_host . env('LOCAL_BASE_PATH', '/School_Project/assignment/SIPN');
} else {
    $base_url = '';
}

// Session (dimulai di sini, jadi halaman lain TIDAK perlu session_start())
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (env('SESSION_DRIVER', 'file') === 'db') {
        require_once __DIR__ . '/session_handler.php';
        session_set_save_handler(new DbSessionHandler($koneksi), true);
    }

    session_start();
}
