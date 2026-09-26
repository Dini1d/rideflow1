<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $keys = ['site_name','site_tagline','site_email','site_phone','site_address',
             'smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from','smtp_from_name',
             'maintenance_mode'];
    foreach ($keys as $k) {
        $val = trim($_POST[$k] ?? '');
        if ($k === 'maintenance_mode') $val = isset($_POST['maintenance_mode']) ? '1' : '0';
        db_exec("INSERT INTO site_settings(setting_key,setting_value) VALUES(?,?) ON CONFLICT (setting_key) DO UPDATE SET setting_value=EXCLUDED.setting_value", [$k,$val]);
    }
    flash('ok','Settings saved.');
    redirect(SITE_URL.'/admin/settings.php');
}

$s = [];
$rows = db_all("SELECT setting_key, setting_value FROM site_settings");
foreach ($rows as $r) $s[$r['setting_key']] = $r['setting_value'];

$pageTitle = 'General Settings';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> — <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=20260923-professional">
</head><body>
<div class="admin-layout">
<?php require_once RF_ROOT.'/admin/admin_sidebar.php'; ?>
<div style="overflow-y:auto">
<?php require_once RF_ROOT.'/admin/admin_topbar.php'; ?>
<div class="admin-main">

<div class="page-hd"><h1>General Settings</h1><p>Configure site information, email, and system options.</p></div>
<?= flash_html() ?>

<form method="POST">
<?= csrf_field() ?>
<div style="display:flex;flex-direction:column;gap:1.25rem;max-width:800px">

  <!-- Site Info -->
  <div class="card card-pad">
    <h3 style="font-size:1rem;margin-bottom:1.1rem"><i class="fas fa-globe" style="color:var(--fire)"></i> Site Information</h3>
    <div class="field-group">
      <div class="fld"><label>Site Name</label>
        <input type="text" name="site_name" class="fc" value="<?= e($s['site_name']??'RideFlow') ?>">
      </div>
      <div class="fld"><label>Tagline</label>
        <input type="text" name="site_tagline" class="fc" value="<?= e($s['site_tagline']??'') ?>">
      </div>
    </div>
    <div class="field-group">
      <div class="fld"><label>Contact Email</label>
        <div class="fw"><i class="fas fa-envelope fc-icon"></i>
          <input type="email" name="site_email" class="fc has-icon" value="<?= e($s['site_email']??'') ?>">
        </div>
      </div>
      <div class="fld"><label>Phone Number</label>
        <div class="fw"><i class="fas fa-phone fc-icon"></i>
          <input type="text" name="site_phone" class="fc has-icon" value="<?= e($s['site_phone']??'') ?>">
        </div>
      </div>
    </div>
    <div class="fld"><label>Address</label>
      <textarea name="site_address" class="fc" rows="2"><?= e($s['site_address']??'') ?></textarea>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:.8rem 1rem;background:rgba(239,68,68,.05);border:1px solid rgba(239,68,68,.15);border-radius:var(--r2)">
      <div>
        <div style="font-weight:600;color:var(--chalk);font-size:.9rem">Maintenance Mode</div>
        <div style="font-size:.78rem;color:var(--chalk4)">Show maintenance page to all non-admin visitors</div>
      </div>
      <label style="position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0">
        <input type="checkbox" name="maintenance_mode" <?= ($s['maintenance_mode']??'0')==='1'?'checked':'' ?> style="opacity:0;width:0;height:0">
        <span style="position:absolute;cursor:pointer;inset:0;background:var(--ink4);border-radius:12px;transition:.2s;border:1px solid var(--rim)"></span>
      </label>
    </div>
  </div>

  <!-- SMTP -->
  <div class="card card-pad">
    <h3 style="font-size:1rem;margin-bottom:.5rem"><i class="fas fa-paper-plane" style="color:var(--info)"></i> Email / SMTP</h3>
    <div class="alert a-info" style="margin-bottom:1rem"><i class="fas fa-info-circle"></i> Install PHPMailer via <code style="background:var(--ink3);padding:.1em .4em;border-radius:4px">composer require phpmailer/phpmailer</code> then set <code style="background:var(--ink3);padding:.1em .4em;border-radius:4px">MAIL_ENABLED=true</code> in config.php.</div>
    <div class="field-group">
      <div class="fld"><label>SMTP Host</label>
        <input type="text" name="smtp_host" class="fc" placeholder="smtp.gmail.com" value="<?= e($s['smtp_host']??'') ?>">
      </div>
      <div class="fld"><label>SMTP Port</label>
        <input type="number" name="smtp_port" class="fc" placeholder="587" value="<?= e($s['smtp_port']??'587') ?>">
      </div>
    </div>
    <div class="field-group">
      <div class="fld"><label>SMTP Username</label>
        <input type="email" name="smtp_user" class="fc" placeholder="you@gmail.com" value="<?= e($s['smtp_user']??'') ?>">
      </div>
      <div class="fld"><label>SMTP Password / App Key</label>
        <div class="fw"><i class="fas fa-lock fc-icon"></i>
          <input type="password" id="smtpPw" name="smtp_pass" class="fc has-icon" style="padding-right:2.5rem" placeholder="App password" value="<?= e($s['smtp_pass']??'') ?>">
          <button type="button" class="fc-eye" onclick="tglPw('smtpPw','smtpPwIco')"><i class="fas fa-eye" id="smtpPwIco"></i></button>
        </div>
      </div>
    </div>
    <div class="field-group">
      <div class="fld"><label>From Email</label>
        <input type="email" name="smtp_from" class="fc" placeholder="noreply@rideflow.lk" value="<?= e($s['smtp_from']??'') ?>">
      </div>
      <div class="fld"><label>From Name</label>
        <input type="text" name="smtp_from_name" class="fc" placeholder="RideFlow" value="<?= e($s['smtp_from_name']??'RideFlow') ?>">
      </div>
    </div>
  </div>

  <div>
    <button type="submit" class="btn btn-fire btn-lg"><i class="fas fa-save"></i> Save Settings</button>
  </div>
</div>
</form>

</div></div></div>
<script>function tglPw(id,ico){var el=document.getElementById(id),ic=document.getElementById(ico);if(!el)return;el.type=el.type==='password'?'text':'password';if(ic)ic.className=el.type==='password'?'fas fa-eye':'fas fa-eye-slash';}</script>
</body></html>
