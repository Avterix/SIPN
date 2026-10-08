<?php
session_start();
require_once "koneksi.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$sql = "UPDATE users SET github_id = NULL, github_username = NULL, github_avatar = NULL, github_connected = 0 WHERE id = '$user_id'";

if (mysqli_query($koneksi, $sql)) {
    $_SESSION['pesan_sukses'] = "Koneksi GitHub berhasil dilepas.";
}

header("Location: profile.php");
exit;