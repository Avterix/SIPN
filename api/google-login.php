<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . "/config_google.php";

$_SESSION['google_oauth_state'] = bin2hex(random_bytes(16));

$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $_SESSION['google_oauth_state'],
    'prompt'        => 'select_account'
];

$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
header("Location: " . $url);
exit;
?>