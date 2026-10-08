<?php
session_start();
require_once "config_github.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$_SESSION['oauth2state'] = bin2hex(random_bytes(16));

$params = [
    'client_id'    => GITHUB_CLIENT_ID,
    'redirect_uri' => GITHUB_REDIRECT_URI,
    'scope'        => 'read:user user:email',
    'state'        => $_SESSION['oauth2state'],
    'prompt'       => 'select_account' // <-- INI YANG PAKSA GITHUB PILIH AKUN / LOGIN ULANG
];

$url = 'https://github.com/login/oauth/authorize?' . http_build_query($params);
header("Location: " . $url);
exit;