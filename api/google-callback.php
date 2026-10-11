<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. RECOVER SESSION ID DARI PARAMETER STATE (Memaksa session terhubung kembali)
$state_param = $_GET['state'] ?? '';
if (strpos($state_param, '---') !== false) {
    $parts = explode('---', $state_param);
    $orig_sess_id = $parts[1] ?? '';
    if (!empty($orig_sess_id)) {
        session_id($orig_sess_id);
    }
}

ini_set('session.cookie_path', '/');
session_start();

require_once __DIR__ . "/config_google.php";

if (empty($_GET['code']) || empty($_GET['state']) || ($_GET['state'] !== ($_SESSION['google_oauth_state'] ?? ''))) {
    $_SESSION['msg_type'] = "error";
    $_SESSION['msg_text'] = "Gagal memverifikasi request Login Google.";
    session_write_close();
    header("Location: login.php");
    exit;
}

$code = $_GET['code'];

// 2. Tukar Kode Auth dengan Access Token
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'code'          => $code,
    'grant_type'    => 'authorization_code',
    'redirect_uri'  => GOOGLE_REDIRECT_URI
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$response = json_decode(curl_exec($ch), true);
curl_close($ch);

$access_token = $response['access_token'] ?? null;

if (!$access_token) {
    $_SESSION['msg_type'] = "error";
    $_SESSION['msg_text'] = "Gagal mendapatkan Access Token Google.";
    session_write_close();
    header("Location: login.php");
    exit;
}

// 3. Ambil Profil User dari API Google
$ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);

$google_user = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!empty($google_user['id'])) {
    $g_id     = mysqli_real_escape_string($koneksi, $google_user['id']);
    $g_email  = mysqli_real_escape_string($koneksi, $google_user['email']);
    $g_name   = mysqli_real_escape_string($koneksi, $google_user['name']);
    $g_avatar = mysqli_real_escape_string($koneksi, $google_user['picture'] ?? '');

    // Cek apakah akun Google ini sudah ada di DB
    $q_cek = mysqli_query($koneksi, "SELECT * FROM users WHERE google_id = '$g_id' OR google_email = '$g_email'");

    if ($q_cek && mysqli_num_rows($q_cek) > 0) {
        // AKUN SUDAH ADA -> LANGSUNG LOGIN
        $user = mysqli_fetch_assoc($q_cek);
        
        mysqli_query($koneksi, "UPDATE users SET google_id = '$g_id', google_email = '$g_email' WHERE id = '{$user['id']}'");

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = strtolower($user['role']);
        $_SESSION['foto']      = !empty($user['foto']) ? $user['foto'] : $g_avatar;
        $_SESSION['login']     = true;
        $_SESSION['logged_in'] = true;

        session_write_close();
        header("Location: dashboard.php");
        exit;
    } else {
        // AKUN BELUM ADA -> SIMPAN DI SESSION TEMPORARY
        $base_username  = strtolower(explode('@', $g_email)[0]);
        $clean_username = preg_replace('/[^a-z0-9_]/', '', $base_username);

        $_SESSION['temp_google'] = [
            'google_id'    => $g_id,
            'google_email' => $g_email,
            'nama_lengkap' => $g_name,
            'username'     => $clean_username,
            'avatar'       => $g_avatar
        ];

        session_write_close();
        header("Location: complete-google-reg.php");
        exit;
    }
} else {
    $_SESSION['msg_type'] = "error";
    $_SESSION['msg_text'] = "Gagal mengambil data profil dari Google.";
    session_write_close();
    header("Location: login.php");
    exit;
}
?>