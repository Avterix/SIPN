<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('session.cookie_path', '/');
session_start();

require_once __DIR__ . "/config_github.php";

$redirect_profile = "profile.php";
$redirect_login   = "login.php";

if (!isset($_SESSION['user_id'])) {
    session_write_close();
    header("Location: " . $redirect_login);
    exit;
}

$user_id = $_SESSION['user_id'];
$sql = "UPDATE users SET github_id = NULL, github_username = NULL, github_avatar = NULL, github_connected = 0 WHERE id = '$user_id'";

if (mysqli_query($koneksi, $sql)) {
    $_SESSION['msg_type'] = "success";
    $_SESSION['msg_text'] = "Koneksi GitHub berhasil dilepas.";
} else {
    $_SESSION['msg_type'] = "error";
    $_SESSION['msg_text'] = "Gagal melepas koneksi GitHub.";
}

session_write_close();
header("Location: " . $redirect_profile);
exit;
?>