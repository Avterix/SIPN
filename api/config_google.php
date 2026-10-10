<?php
require_once __DIR__ . '/koneksi.php';

define('YOUR_GOOGLE_CLIENT_ID', '925370081719-opeco2afn67hklmtoqqmata15i2plrm2.apps.googleusercontent.com');
define('YOUR_GOOGLE_CLIENT_SECRET', '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');

$host = $_SERVER['HTTP_HOST'];
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {

    $redirect_url = "http://" . $host . "/School_Project/assignment/SIPN/api/google-callback.php";
} else {

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $redirect_url = $protocol . "://" . $host . "/api/google-callback.php"; 
}

define('GOOGLE_REDIRECT_URI', $redirect_url);
?>