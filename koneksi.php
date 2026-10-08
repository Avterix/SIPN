<?php
// Sesuaikan dengan settingan server lokal lu (biasanya pakai XAMPP)
$host = "localhost";
$user = "root";      
$pass = "";           
$db   = "db_siacad_smk";

$koneksi = mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>