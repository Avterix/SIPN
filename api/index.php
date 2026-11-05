<?php
// Router tunggal (Vercel hanya memakai satu function: api/index.php)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Path URL yang diakses (tanpa query string)
$request = $_SERVER['REQUEST_URI'] ?? '/';
$path = rawurldecode((string) parse_url($request, PHP_URL_PATH));

// Khusus XAMPP lokal: buang folder pembungkus dari path
$xampp_folder = '/School_Project/assignment/SIPN';
if (strpos($path, $xampp_folder) === 0) {
    $path = substr($path, strlen($xampp_folder));
}

// Buang awalan '/api' kalau ada
if (strpos($path, '/api') === 0) {
    $path = substr($path, 4);
}

// Halaman utama -> dashboard
if ($path === '/' || $path === '' || $path === '/index.php') {
    require __DIR__ . '/dashboard.php';
    exit;
}

// Hanya nama halaman sederhana (huruf, angka, _ dan -), dengan atau tanpa .php
$name = ltrim($path, '/');
if (substr($name, -4) === '.php') {
    $name = substr($name, 0, -4);
}

// File pendukung yang TIDAK boleh dibuka lewat URL
$blocked = [
    'index', 'env', 'koneksi', 'auth', 'session_handler',
    'config_github', 'config_google', 'loader', 'loader_nilai', 'migrate_passwords',
];

if (
    !preg_match('/^[A-Za-z0-9_-]+$/', $name)
    || in_array($name, $blocked, true)
    || !is_file(__DIR__ . '/' . $name . '.php')
) {
    http_response_code(404);
    echo 'Halaman tidak ditemukan (404).';
    exit;
}

require __DIR__ . '/' . $name . '.php';
