<?php
require_once __DIR__ . '/koneksi.php';

define('GITHUB_CLIENT_ID', 'Ov23li5xIOarUlSiJ9lp');
define('GITHUB_CLIENT_SECRET', '37e4eda62876a5d7df58f4bf74c69a1790ff46a9');

$host = $_SERVER['HTTP_HOST'];
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    // URL Callback Localhost
    $redirect_url = "http://" . $host . "/School_Project/assignment/SIPN/api/github-callback.php";
} else {
    // URL Callback Vercel
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $redirect_url = $protocol . "://" . $host . "/api/github-callback.php"; 
}
define('GITHUB_REDIRECT_URI', $redirect_url);
?>