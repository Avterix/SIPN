<?php
$host = isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : getenv('DB_HOST');
$user = isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : getenv('DB_USER');
$pass = isset($_ENV['DB_PASS']) ? $_ENV['DB_PASS'] : getenv('DB_PASS');
$db   = isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : getenv('DB_NAME');
$port = isset($_ENV['DB_PORT']) ? $_ENV['DB_PORT'] : getenv('DB_PORT');

if (!$host) {
    $host = "mysql-sipn-sipn.k.aivencloud.com";
    $user = "avnadmin"; 
    $pass = "AVNS_KAd4LB1vjSZmU_r8gYu"; 
    $db   = "defaultdb"; 
    $port = "19675";
}
$koneksi = mysqli_connect($host, $user, $pass, $db, $port);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

mysqli_select_db($koneksi, $db);

// Deteksi Otomatis Base URL
$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (strpos($http_host, 'localhost') !== false || strpos($http_host, '127.0.0.1') !== false) {
    // Localhost XAMPP
    $base_url = "http://" . $http_host . "/School_Project/assignment/SIPN";
} else {
    // Vercel Production
    $base_url = "";
}
?>