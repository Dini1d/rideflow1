<?php
define('RF_ROOT', __DIR__);
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_once RF_ROOT.'/mail/mailer.php';

if (is_logged_in()) redirect(SITE_URL.'/user/dashboard.php');

$step = 'request'; // request | sent
$err = $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Enter a valid email address.';
    } else {
        $r = auth_forgot_password($email);
        if ($r['ok']) {
            $link = SITE_URL.'/reset_password.php?token='.urlencode($r['token']);
            mail_send_reset($email, $r['name'], $link);
            $step = 'sent';
        } else {
            $err = $r['msg'];
        }
    }
}

$pageTitle = 'Forgot Password';
require_once RF_ROOT.'/includes/header.php';
?>
<div class="main-content">
<div class="container-sm" style="max-width:460px">
  <div style="text-align:center;padding-top:2rem;margin-bottom:2rem">
    <div style="width:64px;height:64px;border-radius:50%;background:rgba(56,189,248,.1);color:var(--info);display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin:0 auto .9rem">
      <i class="fas fa-key"></i>
    </div>
    <h1 style="font-size:1.6rem;margin-bottom:.3rem">Forgot Password?</h1>
    <p>Enter your email and we'll send a reset link.</p>
  </div>

  <?php if ($step === 'sent'): ?>
  <div class="alert a-ok" style="text-align:center;padding:1.5rem">
    <i class="fas fa-envelope-open-text" style="font-size:2rem;display:block;margin-bottom:.75rem"></i>
    <strong>Reset link sent!</strong><br>
    Check your inbox and click the link to reset your password.<br>
    <span style="font-size:.82rem;opacity:.8">Link expires in 2 hours.</span>
  </div>
  <div style="text-align:center;margin-top:1rem">
    <a href="<?= SITE_URL ?>/login.php" class="btn btn-ghost">← Back to Sign In</a>
  </div>
  <?php else: ?>

  <?php if ($err): ?><div class="alert a-bad"><i class="fas fa-times-circle"></i> <?= e($err) ?></div><?php endif; ?>

  <div class="form-card">
    <form method="POST">
      <?= csrf_field() ?>
      <div class="fld">
        <label>Email Address</label>
        <div class="fw"><i class="fas fa-envelope fc-icon"></i>
          <input type="email" name="email" class="fc has-icon" placeholder="you@example.com" required autofocus value="<?= e($_POST['email']??'') ?>">
        </div>
      </div>
      <button type="submit" class="btn btn-fire btn-full btn-lg">
        <i class="fas fa-paper-plane"></i> Send Reset Link
      </button>
    </form>
    <div class="divider" style="margin-top:1.25rem"></div>
    <div style="text-align:center;font-size:.87rem;color:var(--chalk3)">
      Remember it? <a href="<?= SITE_URL ?>/login.php">Sign In</a> &nbsp;·&nbsp; New here? <a href="<?= SITE_URL ?>/register.php">Register</a>
    </div>
  </div>
  <?php endif; ?>
</div>
</div>
<?php require_once RF_ROOT.'/includes/footer.php'; ?>
