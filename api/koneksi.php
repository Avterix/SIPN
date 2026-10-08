<?php
// 1. Cek apakah ada Environment Variables dari Vercel (untuk Online)
$host = isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : getenv('DB_HOST');
$user = isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : getenv('DB_USER');
$pass = isset($_ENV['DB_PASS']) ? $_ENV['DB_PASS'] : getenv('DB_PASS');
$db   = isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : getenv('DB_NAME');
$port = isset($_ENV['DB_PORT']) ? $_ENV['DB_PORT'] : getenv('DB_PORT');

// 2. JIKA DIJALANKAN DI XAMPP LOKAL (Variabel di atas pasti kosong/false)
if (!$host) {
    $host = "mysql-sipn-sipn.k.aivencloud.com";
    $user = "avnadmin"; 
    $pass = "AVNS_KAd4LB1vjSZmU_r8gYu"; 
    $db   = "defaultdb"; 
    $port = "19675";
}
// 3. Eksekusi koneksi ke Cloud Aiven
$koneksi = mysqli_connect($host, $user, $pass, $db, $port);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Paksa mengunci database agar tidak memicu error 'No database selected'
mysqli_select_db($koneksi, $db);

// --- TAMBAHKAN KODE INI DI PALING BAWAH FILE KONEKSI.PHP ---

// Cek apakah web sedang berjalan di localhost (XAMPP) atau di server online (Vercel)
if ($_SERVER['HTTP_HOST'] == 'localhost') {
    // Jika di XAMPP lokal, sertakan nama subfoldernya lengkap
    $base_url = "http://localhost/School_Project/assignment/SIPN";
} else {
    // Jika di Vercel online, gunakan domain utama terluar saja secara otomatis
    $base_url = "";
}

?>
