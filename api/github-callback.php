<?php
session_start();
require_once "koneksi.php";
require_once "config_github.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if (empty($_GET['code']) || empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
    $_SESSION['pesan_error'] = "Gagal memverifikasi request OAuth GitHub.";
    header("Location: profile.php");
    exit;
}

$code = $_GET['code'];

$ch = curl_init('https://github.com/login/oauth/access_token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id'     => GITHUB_CLIENT_ID,
    'client_secret' => GITHUB_CLIENT_SECRET,
    'code'          => $code,
    'redirect_uri'  => GITHUB_REDIRECT_URI
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-SIPNCore-App');

$response = json_decode(curl_exec($ch), true);
curl_close($ch);

$access_token = $response['access_token'] ?? null;

if (!$access_token) {
    $_SESSION['pesan_error'] = "Gagal mendapatkan Access Token dari GitHub.";
    header("Location: profile.php");
    exit;
}

$ch = curl_init('https://api.github.com/user');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . $access_token,
    'User-Agent: PHP-SIPNCore-App'
]);

$github_user = json_decode(curl_exec($ch), true);
curl_close($ch);

if (isset($github_user['id'])) {
    $gh_id       = mysqli_real_escape_string($koneksi, $github_user['id']);
    $gh_username = mysqli_real_escape_string($koneksi, $github_user['login']);
    $gh_avatar   = mysqli_real_escape_string($koneksi, $github_user['avatar_url']);

    // CEK APAKAH AKUN GITHUB INI SUDAH DIPAKAI USER LAIN DI DATABASE
    $q_cek = mysqli_query($koneksi, "SELECT id, username FROM users WHERE github_id = '$gh_id' AND id != '$user_id'");
    if (mysqli_num_rows($q_cek) > 0) {
        $user_lain = mysqli_fetch_assoc($q_cek);
        $_SESSION['pesan_error'] = "Akun GitHub @$gh_username sudah terhubung ke user lain (" . $user_lain['username'] . ")!";
        header("Location: profile.php");
        exit;
    }

    // UPDATE DATA KE AKUN USER YANG SEDANG LOGIN SAAT INI
    $sql = "UPDATE users SET 
            github_id = '$gh_id', 
            github_username = '$gh_username', 
            github_avatar = '$gh_avatar', 
            github_connected = 1 
            WHERE id = '$user_id'";
            
    if (mysqli_query($koneksi, $sql)) {
        $_SESSION['pesan_sukses'] = "Akun GitHub @$gh_username berhasil dihubungkan!";
    } else {
        $_SESSION['pesan_error'] = "Gagal menyimpan data GitHub ke database.";
    }
} else {
    $_SESSION['pesan_error'] = "Gagal mengambil data akun GitHub.";
}

header("Location: profile.php");
exit;