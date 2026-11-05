<?php
require_once __DIR__ . '/koneksi.php';

define('GITHUB_CLIENT_ID', env('GITHUB_CLIENT_ID'));
define('GITHUB_CLIENT_SECRET', env('GITHUB_CLIENT_SECRET'));

$host = $_SERVER['HTTP_HOST'] ?? '';

if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    // LOCALHOST
    define('GITHUB_REDIRECT_URI', 'http://' . $host . env('LOCAL_BASE_PATH', '/School_Project/assignment/SIPN') . '/api/github-callback.php');
} else {
    // PRODUKSI
    define('GITHUB_REDIRECT_URI', 'https://sipnsakuci.my.id/api/github-callback.php');
}
