<?php
// Mengambil path URL yang diakses (misal: /login.php atau /login)
$request = $_SERVER['REQUEST_URI'];

// Bersihkan path dari query string (seperti ?id=1 atau ?pesan=gagal)
$path = parse_url($request, PHP_URL_PATH);

// Jika mengakses halaman utama terluar (/), langsung buka dashboard.php
if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/dashboard.php';
    exit;
}

// Hapus awalan '/api' jika Vercel menyertakannya di URL internal
if (strpos($path, '/api') === 0) {
    $path = substr($path, 4);
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
    // Jika benar-benar tidak ada filenya di folder api/
    http_response_code(404);
    echo "Halaman tidak ditemukan (404) - File: " . htmlspecialchars($path);
}
?>
