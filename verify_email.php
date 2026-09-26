<?php
define('RF_ROOT', __DIR__);
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';

$token = trim($_GET['token'] ?? '');
$r = $token ? auth_verify_email($token) : ['ok'=>false,'msg'=>'No token provided.'];
$pageTitle = 'Email Verification';
require_once RF_ROOT.'/includes/header.php';
?>
<div class="main-content">
<div class="container-sm" style="max-width:460px">
  <div style="text-align:center;padding:3rem 1rem">
    <?php if ($r['ok']): ?>
    <div style="width:80px;height:80px;border-radius:50%;background:rgba(34,197,94,.12);color:var(--ok);display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 1.2rem;animation:fadeIn .5s ease">
      <i class="fas fa-check-circle"></i>
    </div>
    <h1 style="font-size:1.75rem;margin-bottom:.5rem">Email Verified!</h1>
    <p style="margin-bottom:1.75rem">Your account is now active. Welcome to RideFlow!</p>
    <a href="<?= SITE_URL ?>/login.php" class="btn btn-fire btn-lg"><i class="fas fa-sign-in-alt"></i> Sign In Now</a>
    <?php else: ?>
    <div style="width:80px;height:80px;border-radius:50%;background:rgba(239,68,68,.12);color:var(--bad);display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 1.2rem">
      <i class="fas fa-times-circle"></i>
    </div>
    <h1 style="font-size:1.75rem;margin-bottom:.5rem">Verification Failed</h1>
    <p style="margin-bottom:1.75rem"><?= e($r['msg']) ?></p>
    <a href="<?= SITE_URL ?>/register.php" class="btn btn-ghost">← Register Again</a>
    <?php endif; ?>
  </div>
</div>
</div>
<?php require_once RF_ROOT.'/includes/footer.php'; ?>
