<?php
/**
 * Google OAuth Login
 * /auth/google_login.php — initiates OAuth flow
 * /auth/google_callback.php — handles the callback
 */
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';

if (is_logged_in()) redirect(role_home_url());

$gcid = GOOGLE_CLIENT_ID ?: setting('google_client_id');
$gcs  = GOOGLE_CLIENT_SECRET ?: setting('google_client_secret');

if (!$gcid) {
    flash('bad', 'Google Sign-In is not configured.');
    redirect(SITE_URL.'/login.php');
}

$_SESSION['oauth_state'] = bin2hex(random_bytes(16));
$params = http_build_query([
    'client_id'     => $gcid,
    'redirect_uri'  => SITE_URL.'/auth/google_callback.php',
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $_SESSION['oauth_state'],
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);
header('Location: https://accounts.google.com/o/oauth2/v2/auth?'.$params);
exit;
