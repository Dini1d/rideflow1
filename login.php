<?php
define('RF_ROOT', __DIR__);
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_once RF_ROOT.'/includes/smart_tips.php';

if (is_logged_in()) {
    redirect(role_home_url());
}

$gcid = GOOGLE_CLIENT_ID ?: setting('google_client_id');
$hasGoogle = !empty($gcid);

$role   = 'customer';
$method = 'email';
$err    = '';
$allowedRoles = ['customer','driver','admin'];

if (isset($_GET['role'])  && in_array($_GET['role'],  $allowedRoles, true)) $role = $_GET['role'];
if (isset($_POST['role']) && in_array($_POST['role'], $allowedRoles, true)) $role = $_POST['role'];
if (isset($_POST['method']) && in_array($_POST['method'], ['email','phone','otp'], true)) $method = $_POST['method'];

function rf_lock_check(string $key): ?string {
    $lockUntil = (int)($_SESSION[$key.'_lock'] ?? 0);
    if (time() < $lockUntil) {
        return 'Too many attempts. Try again in '.ceil(($lockUntil - time()) / 60).' min.';
    }
    return null;
}
function rf_lock_fail(string $key): string {
    $tries = (int)($_SESSION[$key.'_tries'] ?? 0) + 1;
    $_SESSION[$key.'_tries'] = $tries;
    if ($tries >= 5) {
        $_SESSION[$key.'_lock'] = time() + 900;
        $_SESSION[$key.'_tries'] = 0;
        return 'Too many attempts. Locked for 15 minutes.';
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin') {
    csrf_guard();
    $lock = rf_lock_check('adm');
    if ($lock) { $err = $lock; }
    else {
        $r = auth_login_admin(trim($_POST['adm_email'] ?? ''), $_POST['adm_password'] ?? '');
        if ($r['ok']) {
            flash('ok', greeting_for_now().', '.explode(' ', $r['user']['name'])[0].'. Command centre is live.');
            redirect(role_home_url('admin'));
        }
        $lockMsg = rf_lock_fail('adm');
        $err = $lockMsg ?: ($r['msg'].' ('.(5 - (int)$_SESSION['adm_tries']).' tries left)');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'driver') {
    csrf_guard();
    $lock = rf_lock_check('drv');
    if ($lock) { $err = $lock; }
    else {
        $r = auth_login_driver(trim($_POST['drv_email'] ?? ''), $_POST['drv_password'] ?? '');
        if (!empty($r['wrong_role'])) $role = $r['wrong_role'];
        if ($r['ok']) {
            flash('ok', greeting_for_now().', '.explode(' ', $r['user']['name'])[0].'. Your route board is ready.');
            redirect(role_home_url('driver'));
        }
        $lockMsg = rf_lock_fail('drv');
        $err = $lockMsg ?: ($r['msg'].' ('.(5 - (int)$_SESSION['drv_tries']).' tries left)');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'customer' && $method === 'email') {
    csrf_guard();
    $r = auth_login_email(trim($_POST['email'] ?? ''), $_POST['password'] ?? '', 'customer');
    if (!empty($r['wrong_role'])) $role = $r['wrong_role'];
    if ($r['ok']) {
        $redir = $_SESSION['_after_login'] ?? role_home_url('customer');
        unset($_SESSION['_after_login']);
        flash('ok', greeting_for_now().', '.explode(' ', $r['user']['name'])[0].'.');
        redirect($redir);
    }
    $err = $r['msg'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'customer' && $method === 'phone') {
    csrf_guard();
    $r = auth_login_phone(trim($_POST['phone'] ?? ''), $_POST['password'] ?? '');
    if ($r['ok']) {
        flash('ok', greeting_for_now().', '.explode(' ', $r['user']['name'])[0].'.');
        redirect(role_home_url('customer'));
    }
    $err = $r['msg'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'customer' && $method === 'otp') {
    csrf_guard();
    $ph   = trim($_POST['otp_phone'] ?? '');
    $norm = normalize_phone($ph);
    $user = db_row("SELECT id FROM users WHERE (phone=? OR phone=?) AND status='active' LIMIT 1", [$norm, $ph]);
    if (!$user) { $err = 'No active account with this number.'; }
    else {
        $r = otp_create($norm, 'login');
        if (!$r['ok']) { $err = $r['msg']; }
        else {
            $_SESSION['demo_otp']  = $r['code'];
            $_SESSION['otp_phone'] = $norm;
            flash('ok', 'OTP sent to '.mask_phone($norm).($r['code'] ? ' (Demo: '.$r['code'].')' : ''));
            redirect(SITE_URL.'/otp_verify.php?purpose=login');
        }
    }
}

$oauthErrMap = [
    'google_not_configured' => 'Google Sign-In not configured.',
    'google_token_failed'   => 'Google auth failed. Try again.',
    'account_inactive'      => 'Account deactivated.',
    'state_mismatch'        => 'Security check failed.',
    'session_expired'       => 'Session expired.',
];
$oauthErr = isset($_GET['err'], $oauthErrMap[$_GET['err']]) ? $oauthErrMap[$_GET['err']] : '';
$isDev = defined('APP_ENV') && APP_ENV === 'development';
$loginTips = [
    'customer' => smart_login_tips('customer'),
    'driver'   => smart_login_tips('driver'),
    'admin'    => smart_login_tips('admin'),
];
$hero = [
    'customer' => [
        'kicker' => 'Passenger portal',
        'title'  => 'Travel Sri Lanka<br>like it is <em>2026.</em>',
        'copy'   => 'Live seats, instant QR tickets, and payments that feel native — from Colombo to Jaffna.',
        'points' => ['Google, email, phone, or OTP','Book in under 60 seconds','QR e-ticket on your phone','Secure multi-gateway payments'],
    ],
    'driver' => [
        'kicker' => 'Crew portal',
        'title'  => 'Your route.<br>Your <em>command.</em>',
        'copy'   => 'See today’s trips, passenger lists, and status controls — designed for the cab, not a desk.',
        'points' => ['Today’s assigned vehicle','Passenger manifest in one tap','Start and complete trips live','Safety tips timed to your shift'],
    ],
    'admin' => [
        'kicker' => 'Operations centre',
        'title'  => 'Run the network<br>in <em>real time.</em>',
        'copy'   => 'Bookings, fleet, payments, and people — a professional cockpit for RideFlow staff.',
        'points' => ['Live booking & revenue pulse','Fleet, routes, and schedules','Staff-grade access logging','Smart ops tips every shift'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign In — <?= SITE_NAME ?></title>
<?php if ($hasGoogle): ?><script src="https://accounts.google.com/gsi/client" async defer></script><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,900&family=DM+Sans:wght@400;500;600;700&family=DM+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=20260923-professional">
<style>
.login-page{min-height:100vh;display:grid;grid-template-columns:1.12fr .88fr;background:#090b10}
.hero-panel{position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;padding:2.2rem clamp(1.4rem,3vw,3rem) 1.8rem;border-right:1px solid rgba(255,255,255,.08);background:radial-gradient(circle at 18% 12%,rgba(255,106,0,.22),transparent 28%),radial-gradient(circle at 88% 80%,rgba(56,189,248,.16),transparent 26%),linear-gradient(160deg,#07080c,#12161e)}
.login-page[data-role="driver"] .hero-panel{background:radial-gradient(circle at 18% 12%,rgba(56,189,248,.24),transparent 28%),radial-gradient(circle at 88% 18%,rgba(255,106,0,.12),transparent 22%),linear-gradient(160deg,#061018,#0e1822)}
.login-page[data-role="admin"] .hero-panel{background:radial-gradient(circle at 18% 12%,rgba(239,68,68,.22),transparent 28%),radial-gradient(circle at 88% 80%,rgba(249,115,22,.14),transparent 26%),linear-gradient(160deg,#100808,#1a1010)}
.mesh{position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.035) 1px,transparent 1px);background-size:28px 28px;mask-image:radial-gradient(circle at center,black 46%,transparent 86%);opacity:.7}
.hero-top,.hero-copy,.hero-bottom{position:relative;z-index:1}
.nav-brand{display:inline-flex;align-items:center;gap:.7rem;padding:.4rem .75rem .4rem .4rem;border-radius:999px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08)}
.clock-chip{margin-left:auto;font-size:.72rem;color:rgba(240,237,230,.7);font-family:var(--ff-mono);letter-spacing:.04em}
.hero-kicker{display:inline-flex;align-items:center;gap:.7rem;letter-spacing:.18em;text-transform:uppercase;font-size:.68rem;font-weight:800;color:var(--fire);margin-bottom:.85rem}
.login-page[data-role="driver"] .hero-kicker{color:#38bdf8}
.login-page[data-role="admin"] .hero-kicker{color:#f87171}
.hero-title{max-width:540px;font-size:clamp(2.4rem,4.8vw,4rem);line-height:.94;letter-spacing:-.055em;margin-bottom:.9rem}
.hero-title em{font-style:normal;color:var(--fire)}
.login-page[data-role="driver"] .hero-title em{color:#38bdf8}
.login-page[data-role="admin"] .hero-title em{color:#f87171}
.hero-copy p{max-width:420px;font-size:1.02rem;line-height:1.75;color:rgba(233,236,243,.7);margin-bottom:1.5rem}
.feature-list{list-style:none;display:grid;gap:.7rem;max-width:440px}
.feature-list li{display:flex;align-items:center;gap:.75rem;padding:.65rem .8rem;border-radius:14px;border:1px solid rgba(255,255,255,.06);background:rgba(255,255,255,.025);font-size:.83rem;color:rgba(237,240,245,.82)}
.feature-icon{width:28px;height:28px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;background:rgba(255,106,0,.14);color:var(--fire);flex-shrink:0}
.login-page[data-role="driver"] .feature-icon{background:rgba(56,189,248,.14);color:#38bdf8}
.login-page[data-role="admin"] .feature-icon{background:rgba(239,68,68,.14);color:#f87171}
.tip-stage{margin-top:1.4rem;max-width:440px;min-height:86px;padding:1rem 1.1rem;border-radius:16px;border:1px solid rgba(255,255,255,.08);background:rgba(8,10,14,.45);backdrop-filter:blur(10px)}
.tip-k{font-size:.62rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--fire);margin-bottom:.3rem}
.login-page[data-role="driver"] .tip-k{color:#38bdf8}
.login-page[data-role="admin"] .tip-k{color:#f87171}
.tip-t{font-weight:700;color:#fff;font-size:.95rem}
.tip-b{font-size:.8rem;color:rgba(233,236,243,.68);margin-top:.2rem}
.form-panel{position:relative;display:flex;align-items:center;justify-content:center;padding:2rem 1.2rem;overflow-y:auto;background:rgba(10,11,16,.94)}
.form-box{position:relative;z-index:1;width:100%;max-width:440px}
.role-sw{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.3rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:.28rem;margin-bottom:1.35rem}
.rs{border:none;border-radius:14px;padding:.7rem .4rem;font-family:var(--ff-body);font-size:.78rem;font-weight:700;cursor:pointer;background:transparent;color:var(--chalk4);display:flex;align-items:center;justify-content:center;gap:.4rem}
.rs:hover{color:var(--chalk);background:rgba(255,255,255,.04)}
.rs.on-c{background:linear-gradient(135deg,var(--fire),#ff8e34);color:#fff;box-shadow:0 10px 22px rgba(255,106,0,.28)}
.rs.on-d{background:linear-gradient(135deg,#0ea5e9,#38bdf8);color:#061018;box-shadow:0 10px 22px rgba(56,189,248,.25)}
.rs.on-a{background:linear-gradient(135deg,#ef4444,#f97316);color:#fff;box-shadow:0 10px 22px rgba(239,68,68,.25)}
.ficon{width:62px;height:62px;border-radius:20px;margin:0 auto .85rem;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#fff}
.fic-c{background:linear-gradient(135deg,#ff6a00,#ff9d38);box-shadow:0 16px 30px rgba(255,106,0,.22)}
.fic-d{background:linear-gradient(135deg,#0ea5e9,#38bdf8);box-shadow:0 16px 30px rgba(56,189,248,.22);color:#061018}
.fic-a{background:linear-gradient(135deg,#ef4444,#f97316);box-shadow:0 16px 30px rgba(239,68,68,.22)}
.mtabs{display:flex;gap:.35rem;margin-bottom:1.05rem}
.mtab{flex:1;padding:.52rem .3rem;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.02);border-radius:12px;font-size:.76rem;font-weight:700;cursor:pointer;color:var(--chalk4);display:flex;align-items:center;justify-content:center;gap:.35rem}
.mtab.on{background:linear-gradient(135deg,rgba(255,106,0,.2),rgba(255,106,0,.08));border-color:rgba(255,106,0,.5);color:#fff}
.fpanel{display:none;animation:pIn .22s ease both}
.fpanel.on{display:block}
@keyframes pIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.divider{display:flex;align-items:center;gap:1rem;color:var(--chalk4);font-size:.7rem;text-transform:uppercase;letter-spacing:.12em;margin:1rem 0}
.divider:before,.divider:after{content:"";height:1px;flex:1;background:rgba(255,255,255,.08)}
.sec{display:none}
.sec.on{display:block}
.guard-note{margin-top:.85rem;padding:.7rem 1rem;border-radius:12px;font-size:.75rem;display:flex;align-items:center;gap:.5rem}
.demo-box{margin-top:.85rem;background:var(--veil);border:1px solid var(--rim);border-radius:12px;padding:.75rem 1rem;font-size:.78rem;cursor:pointer}
@media(max-width:900px){.login-page{grid-template-columns:1fr}.hero-panel{display:none}.form-panel{align-items:flex-start;padding-top:2.4rem}}
</style>
</head>
<body>
<div class="login-page" id="loginPage" data-role="<?= e($role) ?>">

<div class="hero-panel">
  <div class="mesh"></div>
  <div class="hero-top" style="display:flex;align-items:center;gap:1rem">
    <a href="<?= SITE_URL ?>" class="nav-brand">
      <div class="nav-logo"><i class="fas fa-route"></i></div>
      <span class="nav-name">Ride<em>Flow</em></span>
    </a>
    <div class="clock-chip" id="liveClock"><?= date('D · H:i') ?> Colombo</div>
  </div>

  <div class="hero-copy">
    <div class="hero-kicker" id="heroKicker"><?= $hero[$role]['kicker'] ?></div>
    <h1 class="hero-title" id="heroTitle"><?= $hero[$role]['title'] ?></h1>
    <p id="heroCopy"><?= e($hero[$role]['copy']) ?></p>
    <ul class="feature-list" id="heroPoints">
      <?php foreach ($hero[$role]['points'] as $f): ?>
      <li><span class="feature-icon"><i class="fas fa-check"></i></span><span><?= e($f) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <div class="tip-stage" id="tipStage">
      <div class="tip-k">Smart tip</div>
      <div class="tip-t" id="tipTitle"><?= e($loginTips[$role][0]['title']) ?></div>
      <div class="tip-b" id="tipBody"><?= e($loginTips[$role][0]['body']) ?></div>
    </div>
  </div>

  <div class="hero-bottom" style="display:flex;flex-wrap:wrap;gap:.6rem">
    <span class="hero-badges"><span style="font-size:.72rem;color:rgba(216,221,230,.7)">Live schedules</span></span>
    <span style="font-size:.72rem;color:rgba(216,221,230,.55)">Encrypted sessions · Role-aware access</span>
  </div>
</div>

<div class="form-panel">
<div class="form-box">

  <div class="role-sw">
    <button type="button" class="rs <?= $role==='customer'?'on-c':'' ?>" id="btnC" onclick="switchRole('customer')"><i class="fas fa-user"></i> Rider</button>
    <button type="button" class="rs <?= $role==='driver'?'on-d':'' ?>" id="btnD" onclick="switchRole('driver')"><i class="fas fa-id-card"></i> Driver</button>
    <button type="button" class="rs <?= $role==='admin'?'on-a':'' ?>" id="btnA" onclick="switchRole('admin')"><i class="fas fa-shield-alt"></i> Admin</button>
  </div>

  <div style="text-align:center;margin-bottom:1.35rem">
    <div class="ficon fic-<?= $role==='admin'?'a':($role==='driver'?'d':'c') ?>" id="ficon"><i class="fas <?= $role==='admin'?'fa-user-shield':($role==='driver'?'fa-car':'fa-user') ?>" id="fIco"></i></div>
    <div style="font-family:var(--ff-disp);font-size:1.5rem;font-weight:700;letter-spacing:-.02em" id="ftitle">
      <?= $role==='admin'?'Admin sign in':($role==='driver'?'Driver sign in':'Welcome back') ?>
    </div>
    <div style="font-size:.83rem;color:var(--chalk3);margin-top:.2rem" id="fsub">
      <?= $role==='admin'?'Authorized staff only':($role==='driver'?'Assigned crew access':'Sign in to book, pay, and ride') ?>
    </div>
  </div>

  <?php if ($err): ?>
  <div class="alert a-bad"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div>
  <?php endif; ?>
  <?php if ($oauthErr): ?>
  <div class="alert a-bad"><i class="fas fa-times-circle"></i> <?= e($oauthErr) ?></div>
  <?php endif; ?>
  <?php echo flash_html(); ?>

  <div class="sec <?= $role==='customer'?'on':'' ?>" id="secC">
    <?php if ($hasGoogle): ?>
    <a href="<?= SITE_URL ?>/auth/google_login.php" class="google-btn" style="margin-bottom:1rem">
      <div class="google-btn-ico">
        <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/></svg>
      </div>
      <span class="google-btn-txt">Continue with Google</span>
    </a>
    <div class="divider">or sign in with</div>
    <?php endif; ?>

    <div class="mtabs">
      <button type="button" class="mtab <?= $method==='email'?'on':'' ?>" id="tE" onclick="switchMethod('E')"><i class="fas fa-envelope"></i> Email</button>
      <button type="button" class="mtab <?= $method==='phone'?'on':'' ?>" id="tP" onclick="switchMethod('P')"><i class="fas fa-phone"></i> Phone</button>
      <button type="button" class="mtab <?= $method==='otp'?'on':'' ?>" id="tO" onclick="switchMethod('O')"><i class="fas fa-mobile-alt"></i> OTP</button>
    </div>

    <div class="fpanel <?= $method==='email'?'on':'' ?>" id="pE">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="role" value="customer">
        <input type="hidden" name="method" value="email">
        <div class="fld"><label>Email</label>
          <div class="fw"><i class="fas fa-envelope fc-icon"></i>
            <input type="email" name="email" class="fc has-icon" placeholder="you@example.com" required autocomplete="email" value="<?= e($_POST['email']??'') ?>">
          </div>
        </div>
        <div class="fld"><label>Password</label>
          <div class="fw"><i class="fas fa-lock fc-icon"></i>
            <input type="password" id="epw" name="password" class="fc has-icon" style="padding-right:2.5rem" placeholder="••••••••" required autocomplete="current-password">
            <button type="button" class="fc-eye" onclick="tglPw('epw','epi')"><i class="fas fa-eye" id="epi"></i></button>
          </div>
          <div class="form-hint" style="text-align:right"><a href="<?= SITE_URL ?>/forgot_password.php">Forgot password?</a></div>
        </div>
        <button type="submit" class="btn btn-fire btn-full btn-lg"><i class="fas fa-arrow-right"></i> Enter passenger portal</button>
      </form>
    </div>

    <div class="fpanel <?= $method==='phone'?'on':'' ?>" id="pP">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="role" value="customer">
        <input type="hidden" name="method" value="phone">
        <div class="fld"><label>Phone</label>
          <div class="fw"><span class="fc-icon">🇱🇰</span>
            <input type="tel" name="phone" class="fc has-icon" placeholder="077 123 4567" required autocomplete="tel">
          </div>
        </div>
        <div class="fld"><label>Password</label>
          <div class="fw"><i class="fas fa-lock fc-icon"></i>
            <input type="password" id="ppw" name="password" class="fc has-icon" style="padding-right:2.5rem" placeholder="••••••••" required>
            <button type="button" class="fc-eye" onclick="tglPw('ppw','ppi')"><i class="fas fa-eye" id="ppi"></i></button>
          </div>
        </div>
        <button type="submit" class="btn btn-fire btn-full btn-lg"><i class="fas fa-sign-in-alt"></i> Sign in with phone</button>
      </form>
    </div>

    <div class="fpanel <?= $method==='otp'?'on':'' ?>" id="pO">
      <div class="alert a-info" style="margin-bottom:1rem"><i class="fas fa-bolt"></i> Passwordless login — we send a 6-digit code to your registered number.</div>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="role" value="customer">
        <input type="hidden" name="method" value="otp">
        <div class="fld"><label>Registered phone</label>
          <div class="fw"><span class="fc-icon">🇱🇰</span>
            <input type="tel" name="otp_phone" class="fc has-icon" placeholder="077 123 4567" required>
          </div>
        </div>
        <button type="submit" class="btn btn-fire btn-full btn-lg"><i class="fas fa-paper-plane"></i> Send smart OTP</button>
      </form>
    </div>

    <div class="divider">new here?</div>
    <a href="<?= SITE_URL ?>/register.php" class="btn btn-ghost btn-full"><i class="fas fa-user-plus"></i> Create a free rider account</a>
  </div>

  <div class="sec <?= $role==='driver'?'on':'' ?>" id="secD">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="role" value="driver">
      <div class="fld"><label>Driver email</label>
        <div class="fw"><i class="fas fa-envelope fc-icon"></i>
          <input type="email" name="drv_email" class="fc has-icon" placeholder="driver@rideflow.lk" required value="<?= e($_POST['drv_email']??'') ?>">
        </div>
      </div>
      <div class="fld"><label>Password</label>
        <div class="fw"><i class="fas fa-key fc-icon"></i>
          <input type="password" id="dpw" name="drv_password" class="fc has-icon" style="padding-right:2.5rem" placeholder="••••••••" required>
          <button type="button" class="fc-eye" onclick="tglPw('dpw','dpi')"><i class="fas fa-eye" id="dpi"></i></button>
        </div>
      </div>
      <button type="submit" class="btn btn-full btn-lg" style="background:#0ea5e9;color:#061018;font-weight:800"><i class="fas fa-car-side"></i> Open driver board</button>
    </form>
    <div class="guard-note" style="background:rgba(56,189,248,.08);border:1px solid rgba(56,189,248,.18);color:#7dd3fc">
      <i class="fas fa-id-badge"></i> Driver accounts are assigned by operations. Wrong portal? Switch to Rider or Admin.
    </div>
    <?php if ($isDev): ?>
    <div class="demo-box" onclick="fillDemo('drv_email','dpw','driver@rideflow.lk')">
      <div style="font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:#38bdf8;margin-bottom:.35rem"><i class="fas fa-flask"></i> Dev — click to fill</div>
      <div style="display:flex;justify-content:space-between;color:var(--chalk3)"><span>Email</span><span class="mono" style="color:var(--chalk)">driver@rideflow.lk</span></div>
      <div style="display:flex;justify-content:space-between;color:var(--chalk3)"><span>Password</span><span class="mono" style="color:var(--chalk)">password</span></div>
    </div>
    <?php endif; ?>
  </div>

  <div class="sec <?= $role==='admin'?'on':'' ?>" id="secA">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="role" value="admin">
      <div class="fld"><label>Admin email</label>
        <div class="fw"><i class="fas fa-envelope fc-icon"></i>
          <input type="email" name="adm_email" class="fc has-icon" placeholder="admin@rideflow.lk" required value="<?= e($_POST['adm_email']??'') ?>">
        </div>
      </div>
      <div class="fld"><label>Password</label>
        <div class="fw"><i class="fas fa-key fc-icon"></i>
          <input type="password" id="apw" name="adm_password" class="fc has-icon" style="padding-right:2.5rem" placeholder="••••••••" required>
          <button type="button" class="fc-eye" onclick="tglPw('apw','api2')"><i class="fas fa-eye" id="api2"></i></button>
        </div>
      </div>
      <button type="submit" class="btn btn-full btn-lg" style="background:var(--bad);color:#fff;font-family:var(--ff-disp)"><i class="fas fa-unlock-alt"></i> Enter operations centre</button>
    </form>
    <div class="guard-note" style="background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.16);color:var(--bad)">
      <i class="fas fa-shield-alt"></i> Staff access is rate-limited and monitored.
    </div>
    <?php if ($isDev): ?>
    <div class="demo-box" onclick="fillDemo('adm_email','apw','admin@rideflow.lk')">
      <div style="font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--bad);margin-bottom:.35rem"><i class="fas fa-flask"></i> Dev — click to fill</div>
      <div style="display:flex;justify-content:space-between;color:var(--chalk3)"><span>Email</span><span class="mono" style="color:var(--chalk)">admin@rideflow.lk</span></div>
      <div style="display:flex;justify-content:space-between;color:var(--chalk3)"><span>Password</span><span class="mono" style="color:var(--chalk)">password</span></div>
    </div>
    <?php endif; ?>
  </div>

</div>
</div>
</div>

<?php if ($hasGoogle): ?>
<div id="g_id_onload" data-client_id="<?= e($gcid) ?>" data-login_uri="<?= SITE_URL ?>/auth/google_onetap.php" data-ux_mode="redirect" data-auto_prompt="false"></div>
<?php endif; ?>

<script>
window.SITE_URL = '<?= SITE_URL ?>';
var HERO = <?= json_encode($hero, JSON_UNESCAPED_UNICODE) ?>;
var TIPS = <?= json_encode($loginTips, JSON_UNESCAPED_UNICODE) ?>;
var tipIdx = 0, tipTimer;

function applyHero(role){
  var h = HERO[role];
  document.getElementById('loginPage').dataset.role = role;
  document.getElementById('heroKicker').textContent = h.kicker;
  document.getElementById('heroTitle').innerHTML = h.title;
  document.getElementById('heroCopy').textContent = h.copy;
  document.getElementById('heroPoints').innerHTML = h.points.map(function(p){
    return '<li><span class="feature-icon"><i class="fas fa-check"></i></span><span>'+p+'</span></li>';
  }).join('');
  tipIdx = 0;
  showTip(role);
  clearInterval(tipTimer);
  tipTimer = setInterval(function(){ tipIdx++; showTip(role); }, 5200);
}
function showTip(role){
  var list = TIPS[role] || TIPS.customer;
  var t = list[tipIdx % list.length];
  document.getElementById('tipTitle').textContent = t.title;
  document.getElementById('tipBody').textContent = t.body;
}
function switchRole(r){
  ['customer','driver','admin'].forEach(function(k){
    var sec = document.getElementById(k==='customer'?'secC':(k==='driver'?'secD':'secA'));
    var btn = document.getElementById(k==='customer'?'btnC':(k==='driver'?'btnD':'btnA'));
    if (sec) sec.className = 'sec'+(k===r?' on':'');
    if (btn) btn.className = 'rs'+(k===r?' on-'+(k==='customer'?'c':(k==='driver'?'d':'a')):'');
  });
  var map = {
    customer:{cls:'fic-c',ico:'fa-user',t:'Welcome back',s:'Sign in to book, pay, and ride'},
    driver:{cls:'fic-d',ico:'fa-car',t:'Driver sign in',s:'Assigned crew access'},
    admin:{cls:'fic-a',ico:'fa-user-shield',t:'Admin sign in',s:'Authorized staff only'}
  };
  var m = map[r];
  document.getElementById('ficon').className = 'ficon '+m.cls;
  document.getElementById('fIco').className = 'fas '+m.ico;
  document.getElementById('ftitle').textContent = m.t;
  document.getElementById('fsub').textContent = m.s;
  applyHero(r);
  history.replaceState(null,'','?role='+r);
  setTimeout(function(){
    var sel = r==='admin'?'[name=adm_email]':(r==='driver'?'[name=drv_email]':'#pE input[type=email]');
    var f = document.querySelector(sel); if(f && !f.value) f.focus();
  }, 50);
}
function switchMethod(m){
  ['E','P','O'].forEach(function(k){
    var t=document.getElementById('t'+k), p=document.getElementById('p'+k);
    if(t) t.className='mtab'+(k===m?' on':'');
    if(p) p.className='fpanel'+(k===m?' on':'');
  });
}
function fillDemo(emName, pwId, email){
  var em=document.querySelector('[name='+emName+']'), pw=document.getElementById(pwId);
  if(em) em.value=email; if(pw) pw.value='password';
}
function tglPw(id,ico){
  var el=document.getElementById(id), ic=document.getElementById(ico);
  if(!el) return; el.type = el.type==='password'?'text':'password';
  if(ic) ic.className = el.type==='password'?'fas fa-eye':'fas fa-eye-slash';
}
function tickClock(){
  var d=new Date();
  var opts={timeZone:'Asia/Colombo',weekday:'short',hour:'2-digit',minute:'2-digit',hour12:false};
  document.getElementById('liveClock').textContent = d.toLocaleString('en-GB',opts).replace(',',' ·')+' Colombo';
}
tickClock(); setInterval(tickClock, 30000);
applyHero('<?= $role ?>');
<?php if ($method!=='email'): ?>switchMethod('<?= $method==='phone'?'P':($method==='otp'?'O':'E') ?>');<?php endif; ?>
</script>
</body>
</html>
