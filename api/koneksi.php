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

$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_local_req = (strpos($http_host, 'localhost') !== false || strpos($http_host, '127.0.0.1') !== false);
$is_local_db  = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

// Otomatis aktifkan SSL untuk DB remote (seperti Aiven), bisa di-override via DB_SSL=true/false
$use_ssl = filter_var(env('DB_SSL', $is_local_db ? 'false' : 'true'), FILTER_VALIDATE_BOOLEAN);

$koneksi = false;
$db_error = '';

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = mysqli_init();
    mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 8);

    $flags = 0;
    if ($use_ssl) {
        mysqli_ssl_set($conn, null, null, null, null, null);
        $flags = defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT')
            ? (MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT)
            : MYSQLI_CLIENT_SSL;
    }

    if (mysqli_real_connect($conn, $host, $user, $pass, $db, $port, null, $flags)) {
        $koneksi = $conn;
    }
} catch (Throwable $e) {
    $db_error = $e->getMessage();
    error_log('SIPN: koneksi DB gagal: ' . $db_error);
}

if (!$koneksi) {
    http_response_code(500);
    if ($is_local_req && $db_error !== '') {
        exit('Layanan sedang bermasalah (Debug Lokal): ' . htmlspecialchars($db_error));
    }
    exit('Layanan sedang bermasalah. Coba lagi nanti.');
}

mysqli_set_charset($koneksi, 'utf8mb4');

// Deteksi otomatis Base URL
if ($is_local_req) {
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