<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';

if (is_logged_in()) redirect(role_home_url());
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(SITE_URL.'/login.php');

$proc = $_SESSION['_goauth'] ?? null;
if (!$proc || (time()-$proc['ts']) > 300) {
    flash('bad','Session expired. Please try again.'); redirect(SITE_URL.'/login.php');
}
unset($_SESSION['_goauth']);

$gcid = GOOGLE_CLIENT_ID ?: setting('google_client_id');
$gcs  = GOOGLE_CLIENT_SECRET ?: setting('google_client_secret');

// Exchange code for token
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>12,CURLOPT_SSL_VERIFYPEER=>true,
    CURLOPT_POSTFIELDS=>http_build_query(['code'=>$proc['code'],'client_id'=>$gcid,'client_secret'=>$gcs,
    'redirect_uri'=>SITE_URL.'/auth/google_callback.php','grant_type'=>'authorization_code'])]);
$tok = json_decode(curl_exec($ch),true); curl_close($ch);

if (empty($tok['access_token'])) {
    flash('bad','Google authentication failed. Please try again.'); redirect(SITE_URL.'/login.php?err=google_token_failed');
}

// Get user info
$ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tok['access_token']]]);
$u = json_decode(curl_exec($ch),true); curl_close($ch);

if (empty($u['sub'])) {
    flash('bad','Could not retrieve Google profile.'); redirect(SITE_URL.'/login.php');
}

$r = auth_social_login('google', $u['sub'], $u['name']??'', $u['email']??'', $u['picture']??'');
if (!$r['ok']) { flash('bad',$r['msg']); redirect(SITE_URL.'/login.php'); }

flash('ok','Welcome, '.explode(' ',$r['user']['name'])[0].'! Signed in with Google.');
$redir = $_SESSION['_after_login'] ?? role_home_url($r['user']['role'] ?? 'customer');
unset($_SESSION['_after_login']);
redirect($redir);
