<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

ini_set('session.cookie_path', '/');
require_once __DIR__ . '/auth.php';

$redirect_profile = 'profile.php';

require_login();

// Token CSRF wajib (link di profile.php membawa ?t=...)
if (!csrf_check($_GET['t'] ?? null)) {
    flash_redirect($redirect_profile, 'error', 'Permintaan tidak valid. Coba lagi dari halaman profil.');
}

$user_id = (int) $_SESSION['user_id'];
$st = $koneksi->prepare('UPDATE users SET github_id = NULL, github_username = NULL, github_avatar = NULL, github_connected = 0 WHERE id = ?');
$st->bind_param('i', $user_id);
$ok = $st->execute();
$st->close();

if ($ok) {
    flash_redirect($redirect_profile, 'success', 'Koneksi GitHub berhasil dilepas.');
}
flash_redirect($redirect_profile, 'error', 'Gagal melepas koneksi GitHub.');
