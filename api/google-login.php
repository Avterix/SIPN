<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

ini_set('session.cookie_path', '/');
require_once __DIR__ . '/config_google.php';

// State acak untuk melindungi dari CSRF di alur OAuth.
// Session ID TIDAK dikirim lewat URL. Di Vercel set SESSION_DRIVER=db supaya session konsisten.
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

session_write_close();

$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'prompt'        => 'select_account',
];

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit;
