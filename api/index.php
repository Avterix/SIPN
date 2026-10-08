<?php
// Mengambil path URL yang diakses (misal: /login atau /daftar_siswa)
$request = $_SERVER['REQUEST_URI'];
$base_path = '/api';

// Bersihkan path dari query string (seperti ?id=1)
$path = parse_url($request, PHP_URL_PATH);

// Jika mengakses halaman utama, arahkan ke dashboard
if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/dashboard.php';
    exit;
}

// Cari file PHP yang sesuai di dalam folder api
$file = __DIR__ . $path . '.php';

if (file_exists($file)) {
    require $file;
} else {
    // Jika file tidak ada, tampilkan error 404 buatan sendiri
    http_response_code(404);
    echo "Halaman tidak ditemukan (404)";
}
?>
