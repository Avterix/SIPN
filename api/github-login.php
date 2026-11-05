<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

ini_set('session.cookie_path', '/');
require_once __DIR__ . '/config_github.php';

// State acak untuk melindungi dari CSRF di alur OAuth
$state = bin2hex(random_bytes(16));
$_SESSION['oauth2state'] = $state;

// Simpan session sebelum redirect keluar aplikasi
session_write_close();

$params = [
    'client_id'    => GITHUB_CLIENT_ID,
    'redirect_uri' => GITHUB_REDIRECT_URI,
    'scope'        => 'read:user user:email',
    'state'        => $state,
    'prompt'       => 'select_account',
];

header('Location: https://github.com/login/oauth/authorize?' . http_build_query($params));
exit;
