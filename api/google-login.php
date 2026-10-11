<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('session.cookie_path', '/');
session_start();

require_once __DIR__ . "/config_google.php";

// Sisipkan Session ID ke dalam state OAuth agar tidak drop saat redirect dari Google
$random_token = bin2hex(random_bytes(8));
$current_sess_id = session_id();
$oauth_state = $random_token . '---' . $current_sess_id;

$_SESSION['google_oauth_state'] = $oauth_state;

$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $oauth_state,
    'prompt'        => 'select_account'
];

$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

session_write_close();
header("Location: " . $url);
exit;
?>