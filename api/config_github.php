<?php
require_once __DIR__ . '/koneksi.php';

$host = $_SERVER['HTTP_HOST'] ?? '';

if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    // --------------------------------------------------
    // CONFIG LOCALHOST (Pakai Client ID/Secret App Local)
    // --------------------------------------------------
    define('GITHUB_CLIENT_ID', 'Ov23li5xIOarUlSiJ9lp');
    define('GITHUB_CLIENT_SECRET', '37e4eda62876a5d7df58f4bf74c69a1790ff46a9');
    define('GITHUB_REDIRECT_URI', "http://" . $host . "/School_Project/assignment/SIPN/api/github-callback.php");

} else {
    // --------------------------------------------------
    // CONFIG PRODUKSI (Pakai Client ID/Secret App Production)
    // --------------------------------------------------
    define('GITHUB_CLIENT_ID', 'Ov23ligjlLntj5NuZLx1');
    define('GITHUB_CLIENT_SECRET', '5415c08f6072993fb26a8e10d4f16e1b2b212eb7');
    define('GITHUB_REDIRECT_URI', "https://sipnsakuci.my.id/api/github-callback.php");
}
?>