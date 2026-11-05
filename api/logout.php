<?php
ini_set('session.cookie_path', '/');
require_once __DIR__ . '/koneksi.php';

// Hapus semua variabel sesi
$_SESSION = [];

// Hapus cookie sesi
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// Hancurkan sesi di server
session_destroy();

header('Location: login.php');
exit;
