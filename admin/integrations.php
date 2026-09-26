<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $keys = ['google_client_id','google_client_secret','anthropic_api_key','ai_enabled',
             'stripe_pk','stripe_sk','paypal_client_id','sms_provider'];
    foreach ($keys as $k) {
        $val = trim($_POST[$k] ?? '');
        if ($k === 'ai_enabled') $val = isset($_POST['ai_enabled']) ? '1' : '0';
        db_exec("INSERT INTO site_settings(setting_key,setting_value) VALUES(?,?) ON CONFLICT (setting_key) DO UPDATE SET setting_value=EXCLUDED.setting_value", [$k,$val]);
    }
    flash('ok', 'Integration settings saved.');
    redirect(SITE_URL.'/admin/integrations.php');
}

$s = [];
$rows = db_all("SELECT setting_key, setting_value FROM site_settings");
foreach ($rows as $r) $s[$r['setting_key']] = $r['setting_value'];

$googleOk  = !empty($s['google_client_id']) && !empty($s['google_client_secret']);
$aiOk      = !empty($s['anthropic_api_key']);
$stripeOk  = !empty($s['stripe_sk']);
$paypalOk  = !empty($s['paypal_client_id']);

$pageTitle = 'Integrations';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> — <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=20260923-professional">
<style>
.int-card{background:var(--ink2);border:1px solid var(--rim);border-radius:var(--r4);overflow:hidden;margin-bottom:1.25rem}
.int-head{padding:1rem 1.4rem;border-bottom:1px solid var(--rim);display:flex;align-items:center;gap:.75rem}
.int-ico{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.int-body{padding:1.25rem 1.4rem}
.status-dot{width:9px;height:9px;border-radius:50%;margin-left:auto;flex-shrink:0}
.sd-ok{background:var(--ok)} .sd-off{background:var(--rim2)}
.copy-url{font-family:var(--ff-mono);font-size:.78rem;background:var(--ink3);border:1px solid var(--rim);border-radius:var(--r2);padding:.45rem .85rem;color:var(--info);display:block;margin:.4rem 0;word-break:break-all;cursor:pointer}
.copy-url:hover{border-color:rgba(56,189,248,.3)}
</style>
</head><body>
<div class="admin-layout">
<?php require_once RF_ROOT.'/admin/admin_sidebar.php'; ?>
<div style="overflow-y:auto">
<?php require_once RF_ROOT.'/admin/admin_topbar.php'; ?>
<div class="admin-main">

<div class="page-hd"><h1><i class="fas fa-plug" style="color:var(--fire)"></i> Integrations</h1>
<p>Configure third-party services — Google Login, AI Chatbot, Stripe, and PayPal.</p></div>
<?= flash_html() ?>

<form method="POST">
<?= csrf_field() ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;max-width:1000px">

  <!-- Google OAuth -->
  <div class="int-card">
    <div class="int-head">
      <div class="int-ico" style="background:rgba(66,133,244,.12)">
        <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/></svg>
      </div>
      <div><div style="font-weight:700;color:var(--chalk);font-size:.92rem">Google Sign-In</div>
        <div style="font-size:.75rem;color:var(--chalk4)">OAuth 2.0</div></div>
      <div class="status-dot <?= $googleOk?'sd-ok':'sd-off' ?>"></div>
    </div>
    <div class="int-body">
      <div class="fld"><label>Client ID</label>
        <div class="fw"><input type="password" id="gcid" name="google_client_id" class="fc" placeholder="xxx.apps.googleusercontent.com" value="<?= e($s['google_client_id']??'') ?>" style="padding-right:2.5rem">
          <button type="button" class="fc-eye" onclick="tglPw('gcid','gcidi')"><i class="fas fa-eye" id="gcidi"></i></button></div>
      </div>
      <div class="fld"><label>Client Secret</label>
        <div class="fw"><input type="password" id="gcs" name="google_client_secret" class="fc" placeholder="GOCSPX-..." value="<?= e($s['google_client_secret']??'') ?>" style="padding-right:2.5rem">
          <button type="button" class="fc-eye" onclick="tglPw('gcs','gcsi')"><i class="fas fa-eye" id="gcsi"></i></button></div>
      </div>
      <div class="fld" style="margin-bottom:0"><label>Callback URL (add to Google Console)</label>
        <span class="copy-url" onclick="copyText(this)"><?= SITE_URL ?>/auth/google_callback.php</span>
        <div class="form-hint"><a href="https://console.cloud.google.com" target="_blank">console.cloud.google.com</a> → Credentials → OAuth 2.0 → Redirect URIs</div>
      </div>
    </div>
  </div>

  <!-- AI Chatbot -->
  <div class="int-card">
    <div class="int-head">
      <div class="int-ico" style="background:var(--fire-soft)"><i class="fas fa-robot" style="color:var(--fire)"></i></div>
      <div><div style="font-weight:700;color:var(--chalk);font-size:.92rem">AI Chatbot</div>
        <div style="font-size:.75rem;color:var(--chalk4)">Claude by Anthropic</div></div>
      <div class="status-dot <?= $aiOk?'sd-ok':'sd-off' ?>"></div>
    </div>
    <div class="int-body">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.9rem;padding:.7rem 1rem;background:var(--veil);border:1px solid var(--rim);border-radius:var(--r2)">
        <div><div style="font-size:.88rem;font-weight:600;color:var(--chalk)">Enable AI Chatbot</div>
          <div style="font-size:.74rem;color:var(--chalk4)">Floating assistant on all pages</div></div>
        <label style="position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0">
          <input type="checkbox" name="ai_enabled" <?= ($s['ai_enabled']??'0')==='1'?'checked':'' ?>>
          <span style="position:absolute;cursor:pointer;inset:0;background:var(--ink4);border-radius:12px;border:1px solid var(--rim);transition:.2s"></span>
        </label>
      </div>
      <div class="fld" style="margin-bottom:0"><label>Anthropic API Key</label>
        <div class="fw"><input type="password" id="akey" name="anthropic_api_key" class="fc" placeholder="sk-ant-api03-..." value="<?= e($s['anthropic_api_key']??'') ?>" style="padding-right:2.5rem">
          <button type="button" class="fc-eye" onclick="tglPw('akey','akeyi')"><i class="fas fa-eye" id="akeyi"></i></button></div>
        <div class="form-hint"><a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a> → API Keys → Create Key (sk-ant-...)</div>
      </div>
    </div>
  </div>

  <!-- Stripe -->
  <div class="int-card">
    <div class="int-head">
      <div class="int-ico" style="background:rgba(99,91,255,.12)"><i class="fas fa-credit-card" style="color:#6366f1"></i></div>
      <div><div style="font-weight:700;color:var(--chalk);font-size:.92rem">Stripe Payments</div>
        <div style="font-size:.75rem;color:var(--chalk4)">Cards & Digital Wallets</div></div>
      <div class="status-dot <?= $stripeOk?'sd-ok':'sd-off' ?>"></div>
    </div>
    <div class="int-body">
      <div class="fld"><label>Publishable Key (pk_)</label>
        <div class="fw"><input type="password" id="spk" name="stripe_pk" class="fc" placeholder="pk_test_..." value="<?= e($s['stripe_pk']??'') ?>" style="padding-right:2.5rem">
          <button type="button" class="fc-eye" onclick="tglPw('spk','spki')"><i class="fas fa-eye" id="spki"></i></button></div>
      </div>
      <div class="fld" style="margin-bottom:0"><label>Secret Key (sk_)</label>
        <div class="fw"><input type="password" id="ssk" name="stripe_sk" class="fc" placeholder="sk_test_..." value="<?= e($s['stripe_sk']??'') ?>" style="padding-right:2.5rem">
          <button type="button" class="fc-eye" onclick="tglPw('ssk','sski')"><i class="fas fa-eye" id="sski"></i></button></div>
        <div class="form-hint"><a href="https://dashboard.stripe.com" target="_blank">dashboard.stripe.com</a> → Developers → API Keys</div>
      </div>
    </div>
  </div>

  <!-- PayPal -->
  <div class="int-card">
    <div class="int-head">
      <div class="int-ico" style="background:rgba(0,48,135,.15)"><i class="fab fa-paypal" style="color:#003087"></i></div>
      <div><div style="font-weight:700;color:var(--chalk);font-size:.92rem">PayPal</div>
        <div style="font-size:.75rem;color:var(--chalk4)">PayPal Payments</div></div>
      <div class="status-dot <?= $paypalOk?'sd-ok':'sd-off' ?>"></div>
    </div>
    <div class="int-body">
      <div class="fld" style="margin-bottom:0"><label>PayPal Client ID</label>
        <div class="fw"><input type="password" id="ppid" name="paypal_client_id" class="fc" placeholder="A..." value="<?= e($s['paypal_client_id']??'') ?>" style="padding-right:2.5rem">
          <button type="button" class="fc-eye" onclick="tglPw('ppid','ppidi')"><i class="fas fa-eye" id="ppidi"></i></button></div>
        <div class="form-hint"><a href="https://developer.paypal.com" target="_blank">developer.paypal.com</a> → Apps & Credentials → Create App</div>
      </div>
    </div>
  </div>

  <!-- SMS -->
  <div class="int-card" style="grid-column:1/-1">
    <div class="int-head">
      <div class="int-ico" style="background:rgba(34,197,94,.1)"><i class="fas fa-sms" style="color:var(--ok)"></i></div>
      <div><div style="font-weight:700;color:var(--chalk);font-size:.92rem">SMS Provider</div>
        <div style="font-size:.75rem;color:var(--chalk4)">OTP & Notifications</div></div>
    </div>
    <div class="int-body">
      <div class="fld" style="margin-bottom:0"><label>Provider</label>
        <select name="sms_provider" class="fc" style="max-width:300px">
          <?php foreach (['demo'=>'Demo (show code on screen)','twilio'=>'Twilio','esms'=>'eSMS.lk (Sri Lanka)','dialog'=>'Dialog Axiata','vonage'=>'Vonage'] as $k=>$v): ?>
          <option value="<?= $k ?>" <?= ($s['sms_provider']??'demo')===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">Edit constants in <code style="background:var(--ink3);padding:.1em .4em;border-radius:4px">config/config.php</code> to set API keys for your chosen SMS provider.</div>
      </div>
    </div>
  </div>

</div>

<div style="margin-top:1.25rem">
  <button type="submit" class="btn btn-fire btn-lg"><i class="fas fa-save"></i> Save Integration Settings</button>
</div>
</form>

</div></div></div>
<script>
function tglPw(id,ico){var el=document.getElementById(id),ic=document.getElementById(ico);if(!el)return;el.type=el.type==='password'?'text':'password';if(ic)ic.className=el.type==='password'?'fas fa-eye':'fas fa-eye-slash';}
function copyText(el){navigator.clipboard.writeText(el.textContent.trim()).then(function(){var orig=el.style.color;el.style.color='var(--ok)';setTimeout(function(){el.style.color=orig;},1500);});}
</script>
</body></html>
