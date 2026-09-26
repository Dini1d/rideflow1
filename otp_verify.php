<?php
define('RF_ROOT', __DIR__);
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';

$purpose = trim($_GET['purpose'] ?? 'login');
$phone   = $_SESSION['otp_phone'] ?? '';
$err     = '';

if (!$phone) { flash('bad','Session expired.'); redirect(SITE_URL.'/login.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $code = trim(implode('', $_POST['otp'] ?? []));
    if (strlen($code) < 6) { $err = 'Enter the 6-digit code.'; }
    else {
        $r = otp_verify($phone, $code, $purpose);
        if (!$r['ok']) { $err = $r['msg']; }
        else {
            if ($purpose === 'login') {
                $u = db_row("SELECT * FROM users WHERE (phone=? OR phone=?) AND status='active' LIMIT 1", [$phone,$phone]);
                if ($u) {
                    login_user($u);
                    unset($_SESSION['otp_phone']);
                    flash('ok','Signed in via OTP.');
                    redirect(role_home_url($u['role'] ?? 'customer'));
                }
            }
            redirect(role_home_url());
        }
    }
}

$demoCode = $_SESSION['demo_otp'] ?? '';
$pageTitle = 'Verify OTP';
require_once RF_ROOT.'/includes/header.php';
?>
<div class="main-content">
<div class="container-sm" style="max-width:440px">
  <div style="text-align:center;padding-top:2rem;margin-bottom:1.75rem">
    <div style="font-size:3.5rem;margin-bottom:.75rem">📱</div>
    <h1 style="font-size:1.7rem;margin-bottom:.4rem">Verify Your Phone</h1>
    <p>Enter the 6-digit code sent to <strong style="color:var(--chalk)"><?= e(mask_phone($phone)) ?></strong></p>
  </div>

  <?php if ($demoCode): ?>
  <div class="alert a-warn" style="text-align:center;margin-bottom:1.25rem">
    <div style="font-size:.8rem;font-weight:700;margin-bottom:.4rem">DEMO MODE — OTP Code:</div>
    <div style="font-size:2rem;font-family:var(--ff-mono);font-weight:900;letter-spacing:.2em;color:var(--chalk)"><?= e($demoCode) ?></div>
  </div>
  <?php endif; ?>

  <?php if ($err): ?><div class="alert a-bad"><i class="fas fa-times-circle"></i> <?= e($err) ?></div><?php endif; ?>

  <div class="form-card">
    <form method="POST" id="otpForm">
      <?= csrf_field() ?>
      <!-- 6 individual OTP input boxes -->
      <div style="display:flex;gap:.5rem;justify-content:center;margin-bottom:1.5rem">
        <?php for ($i=0; $i<6; $i++): ?>
        <input type="text" name="otp[<?= $i ?>]" maxlength="1" inputmode="numeric" pattern="[0-9]"
               class="fc" id="otp<?= $i ?>"
               style="width:50px;height:58px;text-align:center;font-size:1.5rem;font-weight:700;padding:0;border-radius:var(--r3)"
               oninput="otpNext(this,<?= $i ?>)" onkeydown="otpBack(event,<?= $i ?>)">
        <?php endfor; ?>
      </div>
      <button type="submit" class="btn btn-fire btn-full btn-lg" id="submitBtn">
        <i class="fas fa-check-circle"></i> Verify Code
      </button>
    </form>
    <div style="text-align:center;margin-top:1rem;font-size:.85rem;color:var(--chalk3)">
      Didn't receive it? <a href="<?= SITE_URL ?>/login.php?method=otp">Request new code</a>
    </div>
    <div style="text-align:center;margin-top:.4rem;font-size:.85rem">
      <a href="<?= SITE_URL ?>/login.php">← Back to Login</a>
    </div>
  </div>
</div>
</div>

<script>
function otpNext(el, idx) {
  el.value = el.value.replace(/[^0-9]/g,'').slice(-1);
  if (el.value && idx < 5) document.getElementById('otp'+(idx+1)).focus();
  // Auto-submit when all 6 filled
  var all = true;
  for (var i=0;i<6;i++) { if (!document.getElementById('otp'+i).value) { all=false; break; } }
  if (all) document.getElementById('otpForm').submit();
}
function otpBack(e, idx) {
  if (e.key==='Backspace' && !e.target.value && idx>0) {
    var prev = document.getElementById('otp'+(idx-1));
    prev.value=''; prev.focus();
  }
}
// Focus first box on load
document.getElementById('otp0').focus();
<?php if ($demoCode): ?>
// Auto-fill demo code
var code = '<?= e($demoCode) ?>';
for (var i=0;i<6;i++) { var inp=document.getElementById('otp'+i); if(inp&&code[i]) inp.value=code[i]; }
<?php endif; ?>
</script>
<?php require_once RF_ROOT.'/includes/footer.php'; ?>
