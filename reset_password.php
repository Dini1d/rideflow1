<?php
define('RF_ROOT', __DIR__);
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';

if (is_logged_in()) redirect(SITE_URL.'/user/dashboard.php');

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$err = $done = '';

// Validate token
$valid = $token && db_val("SELECT id FROM users WHERE reset_token=? AND reset_expires>NOW()", [$token]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    csrf_guard();
    $pw  = $_POST['password']  ?? '';
    $pw2 = $_POST['password2'] ?? '';
    if ($pw !== $pw2) { $err = 'Passwords do not match.'; }
    else {
        $r = auth_reset_password($token, $pw);
        if ($r['ok']) { $done = true; }
        else { $err = $r['msg']; }
    }
}

$pageTitle = 'Reset Password';
require_once RF_ROOT.'/includes/header.php';
?>
<div class="main-content">
<div class="container-sm" style="max-width:440px">
  <div style="text-align:center;padding-top:2rem;margin-bottom:2rem">
    <div style="width:64px;height:64px;border-radius:50%;background:rgba(34,197,94,.1);color:var(--ok);display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin:0 auto .9rem">
      <i class="fas fa-lock-open"></i>
    </div>
    <h1 style="font-size:1.6rem;margin-bottom:.3rem">Reset Password</h1>
    <p>Choose a strong new password.</p>
  </div>

  <?php if ($done): ?>
  <div class="alert a-ok" style="text-align:center;padding:1.5rem">
    <i class="fas fa-check-circle" style="font-size:2rem;display:block;margin-bottom:.75rem"></i>
    <strong>Password changed!</strong><br>
    You can now sign in with your new password.
  </div>
  <div style="text-align:center;margin-top:1rem">
    <a href="<?= SITE_URL ?>/login.php" class="btn btn-fire">Sign In Now</a>
  </div>
  <?php elseif (!$valid): ?>
  <div class="alert a-bad" style="text-align:center;padding:1.5rem">
    <i class="fas fa-times-circle" style="font-size:2rem;display:block;margin-bottom:.75rem"></i>
    <strong>Invalid or expired link.</strong><br>
    Reset links are valid for 2 hours only.
  </div>
  <div style="text-align:center;margin-top:1rem">
    <a href="<?= SITE_URL ?>/forgot_password.php" class="btn btn-fire">Request New Link</a>
  </div>
  <?php else: ?>
  <?php if ($err): ?><div class="alert a-bad"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div><?php endif; ?>
  <div class="form-card">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="fld">
        <label>New Password</label>
        <div class="fw"><i class="fas fa-lock fc-icon"></i>
          <input type="password" id="pw1" name="password" class="fc has-icon" style="padding-right:2.5rem" placeholder="Min 6 characters" required autofocus>
          <button type="button" class="fc-eye" onclick="tglPw('pw1','pi1')"><i class="fas fa-eye" id="pi1"></i></button>
        </div>
      </div>
      <div class="fld">
        <label>Confirm New Password</label>
        <div class="fw"><i class="fas fa-lock fc-icon"></i>
          <input type="password" id="pw2" name="password2" class="fc has-icon" style="padding-right:2.5rem" placeholder="Repeat password" required>
          <button type="button" class="fc-eye" onclick="tglPw('pw2','pi2')"><i class="fas fa-eye" id="pi2"></i></button>
        </div>
      </div>
      <!-- Password strength indicator -->
      <div style="margin-bottom:1rem">
        <div style="height:4px;background:var(--ink3);border-radius:2px;overflow:hidden">
          <div id="strengthBar" style="height:100%;width:0;transition:width .3s,background .3s;border-radius:2px"></div>
        </div>
        <div id="strengthLabel" style="font-size:.73rem;color:var(--chalk4);margin-top:.25rem"></div>
      </div>
      <button type="submit" class="btn btn-fire btn-full btn-lg"><i class="fas fa-save"></i> Save New Password</button>
    </form>
  </div>
  <script>
  function tglPw(id,ico){var el=document.getElementById(id),ic=document.getElementById(ico);if(!el)return;el.type=el.type==='password'?'text':'password';if(ic)ic.className=el.type==='password'?'fas fa-eye':'fas fa-eye-slash';}
  document.getElementById('pw1').addEventListener('input',function(){
    var pw=this.value,score=0,bar=document.getElementById('strengthBar'),lbl=document.getElementById('strengthLabel');
    if(pw.length>=6)score++;if(pw.length>=10)score++;if(/[A-Z]/.test(pw))score++;if(/[0-9]/.test(pw))score++;if(/[^A-Za-z0-9]/.test(pw))score++;
    var pct=score*20,colors=['','var(--bad)','var(--warn)','var(--warn)','var(--ok)','var(--ok)'],labels=['','Weak','Fair','Fair','Strong','Very Strong'];
    bar.style.width=pct+'%';bar.style.background=colors[score]||'';lbl.textContent=labels[score]||'';
  });
  </script>
  <?php endif; ?>
</div>
</div>
<?php require_once RF_ROOT.'/includes/footer.php'; ?>
