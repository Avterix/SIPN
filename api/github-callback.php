<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

ini_set('session.cookie_path', '/');
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config_github.php';

$redirect_profile = 'profile.php';
$redirect_login   = 'login.php';

// 1. Validasi state OAuth (sekali pakai)
$state_session = (string) ($_SESSION['oauth2state'] ?? '');
unset($_SESSION['oauth2state']);

if (empty($_GET['code']) || !hash_equals($state_session, (string) ($_GET['state'] ?? ''))) {
    flash_redirect($redirect_login, 'error', 'Gagal memverifikasi request OAuth GitHub.');
}

if (!function_exists('curl_init')) {
    error_log('SIPN: ekstensi cURL belum aktif');
    flash_redirect($redirect_login, 'error', 'Server belum mendukung login GitHub.');
}

// Helper cURL kecil
function gh_request($url, $headers, $post = null)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-SIPNCore-App');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    curl_close($ch);
    return $raw === false ? null : json_decode($raw, true);
}

// 2. Tukar code dengan access token
$response = gh_request('https://github.com/login/oauth/access_token', ['Accept: application/json'], [
    'client_id'     => GITHUB_CLIENT_ID,
    'client_secret' => GITHUB_CLIENT_SECRET,
    'code'          => $_GET['code'],
    'redirect_uri'  => GITHUB_REDIRECT_URI,
]);
$access_token = $response['access_token'] ?? null;

if (!$access_token) {
    flash_redirect($redirect_login, 'error', 'Gagal mendapatkan Access Token dari GitHub.');
}

$auth_headers = [
    'Accept: application/json',
    'Authorization: Bearer ' . $access_token,
    'User-Agent: PHP-SIPNCore-App',
];

// 3. Ambil profil GitHub
$github_user = gh_request('https://api.github.com/user', $auth_headers);

if (empty($github_user['id'])) {
    flash_redirect($redirect_login, 'error', 'Gagal mengambil data akun GitHub.');
}

$gh_id       = (string) $github_user['id'];
$gh_username = (string) ($github_user['login'] ?? '');
$gh_avatar   = (string) ($github_user['avatar_url'] ?? '');
$gh_name     = (string) (!empty($github_user['name']) ? $github_user['name'] : $gh_username);

// Email primary yang sudah terverifikasi
$gh_email = '';
$emails = gh_request('https://api.github.com/user/emails', $auth_headers);
if (is_array($emails)) {
    foreach ($emails as $em) {
        if (!empty($em['primary']) && !empty($em['verified']) && !empty($em['email'])) {
            $gh_email = (string) $em['email'];
            break;
        }
    }
}

// =========================================================
// CABANG A: user SUDAH login (hubungkan dari profile.php)
// =========================================================
if (!empty($_SESSION['user_id'])) {
    $user_id = (int) $_SESSION['user_id'];

    $st = $koneksi->prepare('SELECT username FROM users WHERE github_id = ? AND id <> ? LIMIT 1');
    $st->bind_param('si', $gh_id, $user_id);
    $st->execute();
    $st->bind_result($user_lain);
    if ($st->fetch()) {
        $st->close();
        flash_redirect($redirect_profile, 'error', 'Akun GitHub @' . $gh_username . ' sudah terhubung ke user lain (' . $user_lain . ')!');
    }
    $st->close();

    $st = $koneksi->prepare('UPDATE users SET github_id = ?, github_username = ?, github_avatar = ?, github_connected = 1 WHERE id = ?');
    $st->bind_param('sssi', $gh_id, $gh_username, $gh_avatar, $user_id);
    $ok = $st->execute();
    $st->close();

    if ($ok) {
        flash_redirect($redirect_profile, 'success', 'Akun GitHub @' . $gh_username . ' berhasil dihubungkan!');
    }
    flash_redirect($redirect_profile, 'error', 'Gagal menyimpan data GitHub ke database.');
}

// =========================================================
// CABANG B: user BELUM login (login / register via GitHub)
// =========================================================
function cari_user($koneksi, $kolom, $nilai)
{
    // $kolom hanya dari daftar tetap di bawah (bukan input user)
    $sql = 'SELECT id, username, role, foto, github_id FROM users WHERE ' . $kolom . ' = ? LIMIT 1';
    $st = $koneksi->prepare($sql);
    $st->bind_param('s', $nilai);
    $st->execute();
    $st->bind_result($id, $username, $role, $foto, $github_id);
    $row = null;
    if ($st->fetch()) {
        $row = ['id' => $id, 'username' => $username, 'role' => $role, 'foto' => $foto, 'github_id' => $github_id];
    }
    $st->close();
    return $row;
}

$u = cari_user($koneksi, 'github_id', $gh_id);
if (!$u && $gh_email !== '') {
    $u = cari_user($koneksi, 'email', $gh_email);
}

if ($u) {
    // Akun sudah ada -> hubungkan GitHub kalau belum, lalu login
    if (empty($u['github_id'])) {
        $st = $koneksi->prepare('UPDATE users SET github_id = ?, github_username = ?, github_avatar = ?, github_connected = 1 WHERE id = ?');
        $st->bind_param('sssi', $gh_id, $gh_username, $gh_avatar, $u['id']);
        $st->execute();
        $st->close();
    }

    login_user($u['id'], $u['username'], $u['role'], $u['foto'] ?? '');
    flash_redirect('dashboard.php', 'success', 'Selamat datang kembali, @' . $u['username'] . '!');
}

// Akun belum ada -> lengkapi profil siswa dulu
$_SESSION['temp_github'] = [
    'github_id'       => $gh_id,
    'github_username' => $gh_username,
    'github_avatar'   => $gh_avatar,
    'nama_lengkap'    => $gh_name,
    'email'           => $gh_email,
];
flash_redirect('complete-github-reg.php');
