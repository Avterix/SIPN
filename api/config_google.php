<?php
require_once __DIR__ . '/koneksi.php';

define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID'));
define('GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET'));

$host = $_SERVER['HTTP_HOST'] ?? '';

if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    // MODE LOCALHOST
    $redirect_url = 'http://' . $host . env('LOCAL_BASE_PATH', '/School_Project/assignment/SIPN') . '/api/google-callback.php';
} else {
    // MODE VERCEL / PRODUKSI
    $is_https = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
             || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    $redirect_url = ($is_https ? 'https' : 'http') . '://' . $host . '/api/google-callback.php';
}

define('GOOGLE_REDIRECT_URI', $redirect_url);
