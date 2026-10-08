<?php
$host = isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : getenv('DB_HOST');
$user = isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : getenv('DB_USER');
$pass = isset($_ENV['DB_PASS']) ? $_ENV['DB_PASS'] : getenv('DB_PASS');
$db   = isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : getenv('DB_NAME');
$port = isset($_ENV['DB_PORT']) ? $_ENV['DB_PORT'] : getenv('DB_PORT');

$koneksi = mysqli_connect($host, $user, $pass, $db, $port);

if (!$koneksi) {
    die("Koneksi ke Database Cloud Gagal: " . mysqli_connect_error());
}
?>
