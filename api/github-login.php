<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . "/config_github.php";

$redirect_login   = "login.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: " . $redirect_login);
    exit;
}

$_SESSION['oauth2state'] = bin2hex(random_bytes(16));

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