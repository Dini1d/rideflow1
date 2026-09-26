<?php
define('RF_ROOT', __DIR__);
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';

if (is_logged_in()) redirect(SITE_URL.'/user/dashboard.php');

$err = $ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $name      = trim($_POST['name']       ?? '');
    $email     = trim($_POST['email']      ?? '');
    $phone     = trim($_POST['phone']      ?? '');
    $pass      = $_POST['password']        ?? '';
    $pass2     = $_POST['password2']       ?? '';

    if ($pass !== $pass2) {
        $err = 'Passwords do not match.';
    } else {
        $r = auth_register($name, $email, $phone, $pass);
        if (!$r['ok']) {
            $err = $r['msg'];
        } else {
        require_once RF_ROOT.'/mail/mailer.php';
        $link = SITE_URL.'/verify_email.php?token='.urlencode($r['email_token']);
        mail_send_verify($email, $name, $link);
        flash('ok', 'Account created! Check your email to verify your account.');
        redirect(SITE_URL.'/login.php');
        }
    }
}
$pageTitle = 'Create Account';
require_once RF_ROOT.'/includes/header.php';
?>

<div class="main-content">
<div class="container-sm register-shell">
  <div class="register-intro">
    <h1 style="font-size:1.9rem;margin-bottom:.4rem">Create Account</h1>
    <p>Book faster with one secure RideFlow account.</p>
  </div>

  <?php if ($err): ?><div class="alert a-bad"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div><?php endif; ?>

  <!-- Google sign-in shortcut -->
  <?php $gcid = GOOGLE_CLIENT_ID ?: setting('google_client_id'); if ($gcid): ?>
  <a href="<?= SITE_URL ?>/auth/google_login.php" class="google-btn" style="margin-bottom:1rem">
    <div class="google-btn-ico"><svg width="20" height="20" viewBox="0 0 48 48"><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/></svg></div>
    <span class="google-btn-txt">Sign up with Google</span>
  </a>
  <div class="divider">or register with email</div>
  <?php endif; ?>

  <div class="form-card register-card">
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="register-section-head">
        <span class="register-step">01</span>
        <div><strong>Account details</strong><small>Your contact information</small></div>
      </div>
      <div class="field-group">
        <div class="fld">
          <label>Full Name</label>
          <div class="fw"><i class="fas fa-user fc-icon"></i>
            <input type="text" name="name" class="fc has-icon" placeholder="John Perera" required value="<?= e($_POST['name']??'') ?>">
          </div>
        </div>
        <div class="fld">
          <label>Phone Number</label>
          <div class="fw"><span class="fc-icon" style="font-size:.95rem">🇱🇰</span>
            <input type="tel" name="phone" class="fc has-icon" placeholder="077 123 4567" value="<?= e($_POST['phone']??'') ?>">
          </div>
        </div>
      </div>
      <div class="fld">
        <label>Email Address</label>
        <div class="fw"><i class="fas fa-envelope fc-icon"></i>
          <input type="email" name="email" class="fc has-icon" placeholder="you@example.com" required value="<?= e($_POST['email']??'') ?>">
        </div>
      </div>
      <div class="register-section-head register-section-divider">
        <span class="register-step">02</span>
        <div><strong>Secure your account</strong><small>Choose a password you will remember</small></div>
      </div>
      <div class="field-group">
        <div class="fld">
          <label>Password</label>
          <div class="fw"><i class="fas fa-lock fc-icon"></i>
            <input type="password" id="pw1" name="password" class="fc has-icon" style="padding-right:2.5rem" placeholder="Min 6 chars" required>
            <button type="button" class="fc-eye" onclick="tglPw('pw1','pi1')"><i class="fas fa-eye" id="pi1"></i></button>
          </div>
        </div>
        <div class="fld">
          <label>Confirm Password</label>
          <div class="fw"><i class="fas fa-lock fc-icon"></i>
            <input type="password" id="pw2" name="password2" class="fc has-icon" style="padding-right:2.5rem" placeholder="Repeat password" required>
            <button type="button" class="fc-eye" onclick="tglPw('pw2','pi2')"><i class="fas fa-eye" id="pi2"></i></button>
          </div>
        </div>
      </div>
      <div style="margin-bottom:1.2rem">
        <label style="display:flex;align-items:flex-start;gap:.6rem;cursor:pointer">
          <input type="checkbox" name="agree" required style="margin-top:.15rem;accent-color:var(--fire)">
          <span style="font-size:.83rem;color:var(--chalk3)">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></span>
        </label>
      </div>
      <button type="submit" class="btn btn-fire btn-full btn-lg"><i class="fas fa-user-plus"></i> Create Account</button>
    </form>
  </div>

  <div style="text-align:center;margin-top:1.25rem;font-size:.9rem;color:var(--chalk3)">
    Already have an account? <a href="<?= SITE_URL ?>/login.php">Sign In</a>
  </div>
</div>
</div>

<script>function tglPw(id,ico){var el=document.getElementById(id),ic=document.getElementById(ico);if(!el)return;el.type=el.type==='password'?'text':'password';if(ic)ic.className=el.type==='password'?'fas fa-eye':'fas fa-eye-slash';}</script>
<?php require_once RF_ROOT.'/includes/footer.php'; ?>
