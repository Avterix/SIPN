<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

ini_set('session.cookie_path', '/');
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config_google.php';

// 1. Validasi state OAuth (sekali pakai)
$state_session = (string) ($_SESSION['google_oauth_state'] ?? '');
unset($_SESSION['google_oauth_state']);

if (empty($_GET['code']) || !hash_equals($state_session, (string) ($_GET['state'] ?? ''))) {
    flash_redirect('login.php', 'error', 'Gagal memverifikasi request Login Google.');
}

function google_request($url, $headers, $post = null)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    curl_close($ch);
    return $raw === false ? null : json_decode($raw, true);
}

// 2. Tukar kode dengan access token
$response = google_request('https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'], [
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'code'          => $_GET['code'],
    'grant_type'    => 'authorization_code',
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
]);
$access_token = $response['access_token'] ?? null;

if (!$access_token) {
    flash_redirect('login.php', 'error', 'Gagal mendapatkan Access Token Google.');
}

// 3. Ambil profil dari Google
$google_user = google_request('https://www.googleapis.com/oauth2/v2/userinfo', ['Authorization: Bearer ' . $access_token]);

// Wajib: email Google sudah terverifikasi
if (empty($google_user['id']) || empty($google_user['email']) || empty($google_user['verified_email'])) {
    flash_redirect('login.php', 'error', 'Gagal mengambil data profil Google (email belum terverifikasi).');
}

$g_id     = (string) $google_user['id'];
$g_email  = (string) $google_user['email'];
$g_name   = (string) ($google_user['name'] ?? $g_email);
$g_avatar = (string) ($google_user['picture'] ?? '');

// Cari akun berdasarkan google_id dulu, lalu google_email
function cari_user_google($koneksi, $kolom, $nilai)
{
    // $kolom hanya dari daftar tetap di bawah (bukan input user)
    $st = $koneksi->prepare('SELECT id, username, role, foto FROM users WHERE ' . $kolom . ' = ? LIMIT 1');
    $st->bind_param('s', $nilai);
    $st->execute();
    $st->bind_result($id, $username, $role, $foto);
    $row = null;
    if ($st->fetch()) {
        $row = ['id' => $id, 'username' => $username, 'role' => $role, 'foto' => $foto];
    }
    $st->close();
    return $row;
}

$user = cari_user_google($koneksi, 'google_id', $g_id);
if (!$user) {
    $user = cari_user_google($koneksi, 'google_email', $g_email);
}

if ($user) {
    // Akun sudah ada -> sinkronkan data Google lalu login
    $st = $koneksi->prepare('UPDATE users SET google_id = ?, google_email = ? WHERE id = ?');
    $st->bind_param('ssi', $g_id, $g_email, $user['id']);
    $st->execute();
    $st->close();

    login_user($user['id'], $user['username'], $user['role'], !empty($user['foto']) ? $user['foto'] : $g_avatar);
    flash_redirect('dashboard.php');
}

// Akun belum ada -> simpan sementara di session, lanjut lengkapi profil
$base_username  = strtolower(explode('@', $g_email)[0]);
$clean_username = preg_replace('/[^a-z0-9_]/', '', $base_username);

$_SESSION['temp_google'] = [
    'google_id'    => $g_id,
    'google_email' => $g_email,
    'nama_lengkap' => $g_name,
    'username'     => $clean_username,
    'avatar'       => $g_avatar,
];
flash_redirect('complete-google-reg.php');
