<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';

if (is_logged_in()) redirect(role_home_url());

$gcid = GOOGLE_CLIENT_ID ?: setting('google_client_id');
$gcs  = GOOGLE_CLIENT_SECRET ?: setting('google_client_secret');

// CSRF check
$state = $_GET['state'] ?? '';
if (!$state || empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state)) {
    flash('bad', 'Security check failed. Please try again.'); redirect(SITE_URL.'/login.php');
}
unset($_SESSION['oauth_state']);

$code = $_GET['code'] ?? '';
if (!$code) { flash('bad', 'Google authorisation failed.'); redirect(SITE_URL.'/login.php'); }

// Show loading page then process via POST
$_SESSION['_goauth'] = ['code' => $code, 'ts' => time()];
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Signing in…</title>
<style>body{background:#0c0d10;display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:'DM Sans',sans-serif;color:#f0ede6}
.box{background:#14161b;border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:2.5rem 2rem;text-align:center;max-width:360px;width:90%}
.ring{width:60px;height:60px;border-radius:50%;border:3px solid rgba(255,255,255,.07);border-top-color:#ff6a00;animation:sp .8s linear infinite;margin:0 auto 1.2rem}
@keyframes sp{to{transform:rotate(360deg)}}</style>
</head><body>
<div class="box">
  <div class="ring"></div>
  <div style="font-size:1.05rem;font-weight:700;margin-bottom:.3rem">Signing you in…</div>
  <div style="font-size:.85rem;color:#7a7670">Verifying with Google</div>
</div>
<form id="pf" method="POST" action="<?= SITE_URL ?>/auth/google_process.php"><input type="hidden" name="go" value="1"></form>
<script>setTimeout(function(){document.getElementById('pf').submit();},800);</script>
</body></html>
