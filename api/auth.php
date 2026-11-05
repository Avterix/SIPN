<?php
// Helper keamanan: cek login, cek role, CSRF, proses login yang aman.
require_once __DIR__ . '/koneksi.php';

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function require_login()
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function require_role(...$roles)
{
    require_login();
    if (!in_array(strtolower($_SESSION['role'] ?? ''), $roles, true)) {
        header('Location: dashboard.php');
        exit;
    }
}

// ---------- CSRF ----------
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check($token)
{
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

function csrf_require()
{
    if (!csrf_check($_POST['csrf'] ?? null)) {
        http_response_code(419);
        exit('Sesi form kedaluwarsa. Kembali, muat ulang halaman, lalu coba lagi.');
    }
}

// ---------- Login ----------
// Selalu ganti session ID saat login (anti session fixation).
function login_user($id, $username, $role, $foto = '')
{
    session_regenerate_id(true);
    $foto = (string) $foto;
    // Foto base64 bisa sangat besar; jangan ikut disimpan di session.
    if (strpos($foto, 'data:') === 0) {
        $foto = '';
    }
    $_SESSION['user_id']   = (int) $id;
    $_SESSION['username']  = $username;
    $_SESSION['role']      = strtolower((string) $role);
    $_SESSION['foto']      = $foto;
    $_SESSION['login']     = true;
    $_SESSION['logged_in'] = true;
}

// Set pesan flash (opsional), tutup session, lalu redirect.
function flash_redirect($url, $type = '', $text = '')
{
    if ($type !== '') {
        $_SESSION['msg_type'] = $type;
        $_SESSION['msg_text'] = $text;
    }
    session_write_close();
    header('Location: ' . $url);
    exit;
}
