<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Mengambil path URL yang diakses
$request = $_SERVER['REQUEST_URI'];

// Bersihkan path dari query string (seperti ?id=1)
$path = parse_url($request, PHP_URL_PATH);

// --- PERBAIKAN UNTUK XAMPP LOKAL ---
// Jika dijalankan di XAMPP, hapus folder pembungkusnya dari URL path
$xampp_folder = '/School_Project/assignment/SIPN';
if (strpos($path, $xampp_folder) === 0) {
    $path = substr($path, strlen($xampp_folder));
}

// Hapus awalan '/api' jika ada di URL internal
if (strpos($path, '/api') === 0) {
    $path = substr($path, 4);
}
// -----------------------------------

// Jika mengakses halaman utama terluar, langsung buka dashboard.php
if ($path === '/' || $path === '' || $path === '/index.php') {
    require __DIR__ . '/dashboard.php';
    exit;
}

// Cek 1: Jika user mengetik lengkap dengan .php (misal: /login.php)
$file_langsung = __DIR__ . $path;

// Cek 2: Jika user mengetik tanpa .php (misal: /login)
$file_tanpa_ekstensi = __DIR__ . $path . '.php';

if (file_exists($file_langsung) && is_file($file_langsung)) {
    require $file_langsung;
} elseif (file_exists($file_tanpa_ekstensi) && is_file($file_tanpa_ekstensi)) {
    require $file_tanpa_ekstensi;
} else {
    http_response_code(404);
    echo "Halaman tidak ditemukan (404) - File: " . htmlspecialchars($path);
}
?>
