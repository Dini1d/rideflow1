<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_admin();
$gcid = GOOGLE_CLIENT_ID ?: setting('google_client_id');
$gcs  = GOOGLE_CLIENT_SECRET ?: setting('google_client_secret');
$configured = !empty($gcid) && !empty($gcs);
$callbackUrl = SITE_URL.'/auth/google_callback.php';
$pageTitle = 'Google Login Setup';
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
<div style="overflow-y:auto"><?php require_once RF_ROOT.'/admin/admin_topbar.php'; ?>
<div class="admin-main" style="max-width:920px">
<div class="setup-shell">
  <div class="setup-hero">
    <div style="display:flex;align-items:center;gap:.9rem;min-width:0">
      <div class="setup-logo" aria-hidden="true">
        <svg width="24" height="24" viewBox="0 0 48 48"><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/></svg>
      </div>
      <div>
        <div class="label">Admin Configuration</div>
        <h1 style="margin-top:.2rem">Google Login Setup</h1>
      </div>
    </div>
    <div class="status-chip <?= $configured ? 'ok' : 'warn' ?>">
      <i class="fas <?= $configured ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
      <?= $configured ? 'Configured & active' : 'Needs setup' ?>
    </div>
  </div>

  <div class="card card-pad">
    <div class="section-kicker">Setup checklist</div>
    <div class="step-grid">
      <?php $steps=[
        ['n'=>1,'title'=>'Create Google Cloud Project','desc'=>'Go to <a href="https://console.cloud.google.com" target="_blank" style="color:var(--fire)">console.cloud.google.com</a> → New Project → Name it "RideFlow" → Create'],
        ['n'=>2,'title'=>'Enable Google+ / People API','desc'=>'APIs & Services → Library → Search "Google+ API" or "People API" → Enable'],
        ['n'=>3,'title'=>'Configure OAuth Consent Screen','desc'=>'APIs & Services → OAuth consent screen → External → Fill App name "RideFlow" + email → Save'],
        ['n'=>4,'title'=>'Create OAuth 2.0 Credentials','desc'=>'Credentials → Create Credentials → OAuth 2.0 Client → Web application'],
        ['n'=>5,'title'=>'Add Authorized Redirect URI','desc'=>'Under "Authorized redirect URIs" add exactly:'],
        ['n'=>6,'title'=>'Copy Credentials to Config','desc'=>'Paste the Client ID and Client Secret in <code style="background:var(--ink3);padding:.1em .4em;border-radius:4px">config/config.php</code> or Admin → Integrations'],
      ];
      foreach($steps as $s): ?>
      <div class="step-card <?= $s['n']===5 ? 'focus' : '' ?>">
        <div class="step-card-top">
          <div class="step-badge"><?= $configured && $s['n'] < 6 ? '<i class="fas fa-check"></i>' : $s['n'] ?></div>
          <div class="step-title"><?= $s['title'] ?></div>
        </div>
        <div class="step-desc"><?= $s['desc'] ?></div>
        <?php if($s['n']===5): ?>
        <div onclick="navigator.clipboard.writeText('<?= e($callbackUrl) ?>')" class="copy-box" title="Click to copy">
          <span><?= e($callbackUrl) ?></span>
          <i class="fas fa-copy"></i>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="action-row">
    <a href="<?= SITE_URL ?>/admin/integrations.php" class="btn btn-fire"><i class="fas fa-key"></i> Enter Credentials → Integrations</a>
    <?php if($configured): ?>
    <a href="<?= SITE_URL ?>/login.php" target="_blank" class="btn btn-ghost"><i class="fas fa-external-link-alt"></i> Test Login Page</a>
    <?php endif; ?>
  </div>
</div>

</div></div></div>
</body></html>
