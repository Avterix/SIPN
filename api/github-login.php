<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('session.cookie_path', '/');
session_start();

require_once __DIR__ . "/config_github.php";

// Set state OAuth untuk keamanan
$_SESSION['oauth2state'] = bin2hex(random_bytes(16));

// Simpan session ke RAM/Disk sebelum redirect keluar aplikasi
session_write_close();

$params = [
    'client_id'    => GITHUB_CLIENT_ID,
    'redirect_uri' => GITHUB_REDIRECT_URI,
    'scope'        => 'read:user user:email',
    'state'        => $_SESSION['oauth2state'],
    'prompt'       => 'select_account'
];

$url = 'https://github.com/login/oauth/authorize?' . http_build_query($params);
header("Location: " . $url);
exit;
?>