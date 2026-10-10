<?php
require_once __DIR__ . '/koneksi.php';

define('GOOGLE_CLIENT_ID', '925370081719-opeco2afn67hklmtoqqmata15i2plrm2.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');

$host = $_SERVER['HTTP_HOST'];

if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    // --- MODE LOCALHOST ---
    $redirect_url = "http://" . $host . "/School_Project/assignment/SIPN/google-callback.php";
} else {
    // --- MODE VERCEL / PRODUCTION ---
    // Cek HTTPS via proxy header Vercel
    $is_https = (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') 
             || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
             
    $protocol = $is_https ? "https" : "http";
    
    // Sesuaikan path jika file google-callback.php ada di dalam folder /api/
    $redirect_url = $protocol . "://" . $host . "/google-callback.php"; 
}

define('GOOGLE_REDIRECT_URI', $redirect_url);
?>