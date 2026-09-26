<?php
define('RF_ROOT', dirname(__DIR__));
$pageTitle = 'My Profile';
require_once RF_ROOT.'/includes/header.php';
require_login();

$uid  = current_uid();
$user = db_row("SELECT * FROM users WHERE id=?", [$uid]);
$err  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $act = $_POST['action'] ?? '';

  if ($act === 'avatar') {
    $file = $_FILES['avatar'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      $err = 'No file uploaded.';
    } else {
      $res = avatar_upload($file, $uid);
      if ($res) {
        // Refresh session user data
        $_SESSION['udata']['avatar_url'] = $res;
        flash('ok', 'Profile photo updated.');
        redirect(SITE_URL.'/user/profile.php');
      } else {
        $err = upload_error_message($file['error'] ?? 0);
      }
    }
  }

  if ($act === 'avatar_delete') {
    avatar_delete($uid);
    $_SESSION['udata']['avatar_url'] = null;
    flash('ok', 'Profile photo removed.');
    redirect(SITE_URL.'/user/profile.php');
  }

    if ($act === 'profile') {
        $name  = trim($_POST['name']    ?? '');
        $email = trim($_POST['email']   ?? '');
        $phone = trim($_POST['phone']   ?? '');
        $addr  = trim($_POST['address'] ?? '');
        if (!$name) { $err = 'Name is required.'; }
        elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $err = 'Invalid email.'; }
        else {
            // Check email uniqueness
            if ($email && $email !== $user['email']) {
                if (db_val("SELECT id FROM users WHERE email=? AND id!=?", [$email,$uid])) {
                    $err = 'Email already in use.';
                }
            }
            if (!$err) {
                db_exec("UPDATE users SET name=?,email=?,phone=?,address=? WHERE id=?",
                    [$name, $email?:null, normalize_phone($phone)?:$phone, $addr, $uid]);
                $_SESSION['uname']  = $name;
                $_SESSION['uemail'] = $email;
                flash('ok','Profile updated.');
                redirect(SITE_URL.'/user/profile.php');
            }
        }
    }

    if ($act === 'password') {
        $cur  = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password']     ?? '';
        $new2 = $_POST['new_password2']    ?? '';
        if (!verify_password($cur, stored_password_hash($user))) { $err = 'Current password is incorrect.'; }
        elseif (strlen($new) < 6) { $err = 'New password must be at least 6 characters.'; }
        elseif ($new !== $new2)   { $err = 'New passwords do not match.'; }
        else {
            update_user_password($uid, hash_password($new));
            flash('ok','Password changed successfully.');
            redirect(SITE_URL.'/user/profile.php');
        }
    }
}
?>
<div class="main-content">
<div class="container-sm">

  <h1 style="margin-bottom:1.5rem">My Profile</h1>
  <?php if ($err): ?><div class="alert a-bad"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div><?php endif; ?>

  <div class="grid-2" style="gap:1.5rem;align-items:start">

    <!-- Profile info -->
    <div>
      <!-- Avatar card -->
      <div class="card card-pad" style="text-align:center;margin-bottom:1.25rem">
        <div style="width:80px;height:80px;border-radius:50%;background:var(--fire);display:flex;align-items:center;justify-content:center;font-size:1.9rem;font-weight:700;color:#fff;margin:0 auto .85rem;overflow:hidden;box-shadow:var(--shadow-fire)">
          <?php if ($user['avatar_url']): ?>
          <img src="<?= e($user['avatar_url']) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
          <?php else: ?>
          <?= strtoupper(substr($user['name'],0,1)) ?>
          <?php endif; ?>
        </div>
        <div style="font-size:1.1rem;font-weight:700;color:var(--chalk)"><?= e($user['name']) ?></div>
        <div style="font-size:.83rem;color:var(--chalk3);margin-top:.2rem"><?= e($user['email']??'No email set') ?></div>
        <div style="margin-top:.75rem;display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap">
          <?php if ($user['email_verified']): ?><span class="badge b-ok"><i class="fas fa-check"></i> Email Verified</span><?php endif; ?>
          <?php if (!empty($user['phone_verified'])): ?><span class="badge b-ok"><i class="fas fa-check"></i> Phone Verified</span><?php endif; ?>
          <?php if ($user['social_provider']): ?><span class="badge b-info"><i class="fab fa-<?= $user['social_provider'] ?>"></i> <?= ucfirst($user['social_provider']) ?></span><?php endif; ?>
        </div>
        <div style="margin-top:.85rem;padding-top:.85rem;border-top:1px solid var(--rim);font-size:.8rem;color:var(--chalk4)">
          Member since <?= date('M Y',strtotime($user['created_at'])) ?>
        </div>
        <!-- Avatar upload/delete -->
        <div style="margin-top:.85rem;text-align:center">
          <form method="POST" enctype="multipart/form-data" style="display:inline-block"> 
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="avatar">
            <label class="btn btn-dark btn-sm" style="margin-right:.45rem">
              <i class="fas fa-upload"></i> Choose File
              <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp,image/*" style="display:none">
            </label>
            <button type="submit" class="btn btn-fire btn-sm">Upload</button>
          </form>
          <?php if ($user['avatar_url']): ?>
          <form method="POST" style="display:inline-block;margin-left:.5rem"> <?= csrf_field() ?> <input type="hidden" name="action" value="avatar_delete"> <button class="btn btn-bad btn-sm">Delete</button></form>
          <?php endif; ?>
        </div>
      </div>

      <!-- Edit profile form -->
      <div class="card card-pad">
        <h3 style="font-size:1rem;margin-bottom:1rem">Edit Profile</h3>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="profile">
          <div class="fld"><label>Full Name</label>
            <div class="fw"><i class="fas fa-user fc-icon"></i>
              <input type="text" name="name" class="fc has-icon" required value="<?= e($user['name']) ?>">
            </div>
          </div>
          <div class="fld"><label>Email Address</label>
            <div class="fw"><i class="fas fa-envelope fc-icon"></i>
              <input type="email" name="email" class="fc has-icon" value="<?= e($user['email']??'') ?>" placeholder="you@example.com">
            </div>
          </div>
          <div class="fld"><label>Phone Number</label>
            <div class="fw"><span class="fc-icon" style="font-size:.95rem">🇱🇰</span>
              <input type="tel" name="phone" class="fc has-icon" value="<?= e($user['phone']) ?>">
            </div>
          </div>
          <div class="fld"><label>Address (optional)</label>
            <textarea name="address" class="fc" rows="2" placeholder="Your address…"><?= e($user['address']??'') ?></textarea>
          </div>
          <button type="submit" class="btn btn-fire"><i class="fas fa-save"></i> Save Changes</button>
        </form>
      </div>
    </div>

    <!-- Password change -->
    <div>
      <?php if (!$user['social_provider'] || $user['password']): ?>
      <div class="card card-pad" style="margin-bottom:1.25rem">
        <h3 style="font-size:1rem;margin-bottom:1rem"><i class="fas fa-lock" style="color:var(--warn)"></i> Change Password</h3>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="password">
          <div class="fld"><label>Current Password</label>
            <div class="fw"><i class="fas fa-lock fc-icon"></i>
              <input type="password" id="cpw" name="current_password" class="fc has-icon" style="padding-right:2.5rem" required placeholder="Your current password">
              <button type="button" class="fc-eye" onclick="tglPw('cpw','cpwi')"><i class="fas fa-eye" id="cpwi"></i></button>
            </div>
          </div>
          <div class="fld"><label>New Password</label>
            <div class="fw"><i class="fas fa-key fc-icon"></i>
              <input type="password" id="npw" name="new_password" class="fc has-icon" style="padding-right:2.5rem" required placeholder="Min 6 characters">
              <button type="button" class="fc-eye" onclick="tglPw('npw','npwi')"><i class="fas fa-eye" id="npwi"></i></button>
            </div>
          </div>
          <div class="fld"><label>Confirm New Password</label>
            <div class="fw"><i class="fas fa-key fc-icon"></i>
              <input type="password" id="cpw2" name="new_password2" class="fc has-icon" style="padding-right:2.5rem" required placeholder="Repeat new password">
              <button type="button" class="fc-eye" onclick="tglPw('cpw2','cpw2i')"><i class="fas fa-eye" id="cpw2i"></i></button>
            </div>
          </div>
          <button type="submit" class="btn btn-warn"><i class="fas fa-key"></i> Change Password</button>
        </form>
      </div>
      <?php endif; ?>

      <!-- Quick links -->
      <div class="card card-pad">
        <h3 style="font-size:1rem;margin-bottom:.85rem">Quick Actions</h3>
        <div style="display:flex;flex-direction:column;gap:.5rem">
          <a href="<?= SITE_URL ?>/user/bookings.php" class="btn btn-dark" style="justify-content:flex-start"><i class="fas fa-ticket-alt" style="color:var(--fire)"></i> My Bookings</a>
          <a href="<?= SITE_URL ?>/user/dashboard.php" class="btn btn-dark" style="justify-content:flex-start"><i class="fas fa-tachometer-alt" style="color:var(--info)"></i> Dashboard</a>
          <a href="<?= SITE_URL ?>/search.php" class="btn btn-dark" style="justify-content:flex-start"><i class="fas fa-search" style="color:var(--ok)"></i> Search Trips</a>
          <a href="<?= SITE_URL ?>/logout.php" class="btn btn-dark" style="justify-content:flex-start;color:var(--bad)"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
<script>function tglPw(id,ico){var el=document.getElementById(id),ic=document.getElementById(ico);if(!el)return;el.type=el.type==='password'?'text':'password';if(ic)ic.className=el.type==='password'?'fas fa-eye':'fas fa-eye-slash';}</script>
<?php require_once RF_ROOT.'/includes/footer.php'; ?>
