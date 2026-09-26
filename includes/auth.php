<?php
/**
 * RideFlow — Authentication Functions
 */
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/helpers.php';

function auth_dashboard_url($roleOrUser): string {
    $role = is_array($roleOrUser) ? ($roleOrUser['role'] ?? 'customer') : $roleOrUser;
    return role_home_url((string)$role);
}

/* ── Login ────────────────────────────────────────────────── */
function auth_login_email(string $email, string $password, ?string $expectRole = null): array {
    $user = db_row("SELECT * FROM users WHERE email=? AND role IN ('customer','admin','driver') LIMIT 1", [$email]);
    if (!$user) return ['ok'=>false,'msg'=>'No account found with this email.'];
    if ($expectRole && $user['role'] !== $expectRole) {
        $portals = ['admin'=>'Admin portal','driver'=>'Driver portal','customer'=>'Customer portal'];
        $portal  = $portals[$user['role']] ?? 'correct portal';
        return ['ok'=>false,'msg'=>'This account belongs to the '.$portal.'. Switch portals to continue.','wrong_role'=>$user['role']];
    }

    $storedHash = stored_password_hash($user);
    $plainOk  = $storedHash !== '' && password_verify($password, $storedHash);
    $legacyOk = !$plainOk && password_legacy_verify($password, $storedHash);
    if (!$plainOk && !$legacyOk) return ['ok'=>false,'msg'=>'Incorrect password.'];
    if ($legacyOk || ($plainOk && password_needs_rehash($storedHash, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]))) {
        update_user_password((int)$user['id'], hash_password($password));
    }

    if ($user['status'] === 'pending') {
        if (!$user['email_verified']) {
            return ['ok'=>false,'msg'=>'Your account is awaiting email verification.','pending'=>true];
        }
    }
    if ($user['status'] === 'inactive')   return ['ok'=>false,'msg'=>'Your account has been deactivated.'];
    if ($user['status'] === 'suspended')  return ['ok'=>false,'msg'=>'Your account is suspended. Contact support.'];
    login_user($user);
    return ['ok'=>true,'user'=>$user];
}

function auth_login_phone(string $phone, string $password): array {
    $norm = normalize_phone($phone);
    $user = db_row("SELECT * FROM users WHERE (phone=? OR phone=?) AND role='customer' LIMIT 1", [$norm,$phone]);
    if (!$user) return ['ok'=>false,'msg'=>'No account with this phone number.'];

    $storedHash = stored_password_hash($user);
    $plainOk  = $storedHash !== '' && password_verify($password, $storedHash);
    $legacyOk = !$plainOk && password_legacy_verify($password, $storedHash);
    if (!$plainOk && !$legacyOk) return ['ok'=>false,'msg'=>'Incorrect password.'];
    if ($legacyOk || ($plainOk && password_needs_rehash($storedHash, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]))) {
        update_user_password((int)$user['id'], hash_password($password));
    }

    if ($user['status'] === 'pending') {
        if (!$user['email_verified']) {
            return ['ok'=>false,'msg'=>'Your account is awaiting email verification.','pending'=>true];
        }
    }
    if ($user['status'] === 'inactive')   return ['ok'=>false,'msg'=>'Your account has been deactivated.'];
    if ($user['status'] === 'suspended')  return ['ok'=>false,'msg'=>'Your account is suspended. Contact support.'];
    login_user($user);
    return ['ok'=>true,'user'=>$user];
}

function auth_login_driver(string $email, string $password): array {
    return auth_login_email($email, $password, 'driver');
}

function auth_login_admin(string $email, string $password): array {
    $user = db_row("SELECT * FROM users WHERE email=? AND role='admin' LIMIT 1", [$email]);
    if (!$user) return ['ok'=>false,'msg'=>'Invalid credentials.'];

    $storedHash = stored_password_hash($user);
    $plainOk  = $storedHash !== '' && password_verify($password, $storedHash);
    $legacyOk = !$plainOk && password_legacy_verify($password, $storedHash);
    if (!$plainOk && !$legacyOk) return ['ok'=>false,'msg'=>'Invalid credentials.'];
    if ($legacyOk || ($plainOk && password_needs_rehash($storedHash, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]))) {
        update_user_password((int)$user['id'], hash_password($password));
    }

    if ($user['status'] !== 'active') return ['ok'=>false,'msg'=>'Account not active.'];
    login_user($user);
    return ['ok'=>true,'user'=>$user];
}

/* ── Register ─────────────────────────────────────────────── */
function auth_register(string $name, string $email, string $phone, string $password, string $role = 'customer'): array {
    if (strlen($name) < 2)   return ['ok'=>false,'msg'=>'Name must be at least 2 characters.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok'=>false,'msg'=>'Invalid email address.'];
    if (strlen($password) < 6) return ['ok'=>false,'msg'=>'Password must be at least 6 characters.'];

    // Check duplicates
    if (db_val("SELECT id FROM users WHERE email=? LIMIT 1", [$email])) {
        return ['ok'=>false,'msg'=>'This email is already registered.'];
    }
    $norm = normalize_phone($phone);
    if ($phone && db_val("SELECT id FROM users WHERE phone=? OR phone=? LIMIT 1", [$norm,$phone])) {
        return ['ok'=>false,'msg'=>'This phone number is already registered.'];
    }

    $token  = gen_token();
    $userId = db_insert(
        "INSERT INTO users(name,email,phone,password,password_hash,role,status,email_token) VALUES(?,?,?,?,?,'customer','pending',?)",
        [$name, $email, $norm ?: $phone, hash_password($password), hash_password($password), $token]
    );
    return ['ok'=>true,'user_id'=>$userId,'email_token'=>$token];
}

/* ── Email verification ───────────────────────────────────── */
function auth_verify_email(string $token): array {
    $user = db_row("SELECT * FROM users WHERE email_token=? AND status IN ('pending','inactive') LIMIT 1", [$token]);
    if (!$user) return ['ok'=>false,'msg'=>'Invalid or expired verification link.'];
    db_exec("UPDATE users SET status='active', email_verified=1, email_token=NULL WHERE id=?", [$user['id']]);
    return ['ok'=>true,'user'=>$user];
}

/* ── Forgot password ──────────────────────────────────────── */
function auth_forgot_password(string $email): array {
    $user = db_row("SELECT id,name FROM users WHERE email=? AND status='active' LIMIT 1", [$email]);
    if (!$user) return ['ok'=>false,'msg'=>'No active account with this email.'];
    $token = gen_token();
    $exp   = date('Y-m-d H:i:s', strtotime('+2 hours'));
    db_exec("UPDATE users SET reset_token=?, reset_expires=? WHERE id=?", [$token, $exp, $user['id']]);
    return ['ok'=>true,'user_id'=>$user['id'],'name'=>$user['name'],'token'=>$token];
}

function auth_reset_password(string $token, string $newPassword): array {
    if (strlen($newPassword) < 6) return ['ok'=>false,'msg'=>'Password must be at least 6 characters.'];
    $user = db_row("SELECT id FROM users WHERE reset_token=? AND reset_expires>NOW() LIMIT 1", [$token]);
    if (!$user) return ['ok'=>false,'msg'=>'Invalid or expired reset link.'];
    update_user_password((int)$user['id'], hash_password($newPassword));
    db_exec("UPDATE users SET reset_token=NULL, reset_expires=NULL WHERE id=?", [$user['id']]);
    return ['ok'=>true];
}

/* ── OTP ──────────────────────────────────────────────────── */
function otp_create(string $phone, string $purpose = 'login', ?int $userId = null): array {
    $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $exp  = date('Y-m-d H:i:s', strtotime('+'.OTP_EXPIRY_MIN.' minutes'));
    // Rate limit: max 3 per hour
    $recent = db_val("SELECT COUNT(*) FROM otp_codes WHERE phone=? AND purpose=? AND created_at>CURRENT_TIMESTAMP - INTERVAL '1 hour'",
        [$phone, $purpose]);
    if ($recent >= 3) return ['ok'=>false,'msg'=>'Too many OTP requests. Wait 1 hour.'];
    // Store in session (works without DB table)
    $_SESSION['otp_'.$purpose] = ['phone'=>$phone,'code'=>$code,'exp'=>time()+OTP_EXPIRY_MIN*60];
    // Also try DB
    try { db_exec("INSERT INTO otp_codes(phone,code,purpose,expires_at) VALUES(?,?,?,?)", [$phone,$code,$purpose,$exp]); }
    catch (Exception $e) {}
    return ['ok'=>true,'code'=>$code];
}

function otp_verify(string $phone, string $code, string $purpose = 'login'): array {
    // Check session first
    $s = $_SESSION['otp_'.$purpose] ?? null;
    if ($s && $s['phone'] === $phone && $s['code'] === $code && time() < $s['exp']) {
        unset($_SESSION['otp_'.$purpose]);
        return ['ok'=>true];
    }
    // Check DB
    $row = db_row("SELECT id FROM otp_codes WHERE phone=? AND code=? AND purpose=? AND expires_at>NOW() AND used=0 LIMIT 1",
        [$phone,$code,$purpose]);
    if ($row) {
        db_exec("UPDATE otp_codes SET used=1 WHERE id=?", [$row['id']]);
        return ['ok'=>true];
    }
    return ['ok'=>false,'msg'=>'Invalid or expired OTP code.'];
}

/* ── Social login (Google) ────────────────────────────────── */
function auth_social_login(string $provider, string $socialId, string $name, string $email = '', string $avatar = ''): array {
    // Find existing social account
    $user = db_row("SELECT * FROM users WHERE social_provider=? AND social_id=? LIMIT 1", [$provider,$socialId]);
    if (!$user && $email) {
        // Link to existing email account
        $user = db_row("SELECT * FROM users WHERE email=? LIMIT 1", [$email]);
        if ($user) {
            db_exec("UPDATE users SET social_provider=?,social_id=?,avatar_url=?,email_verified=1 WHERE id=?",
                [$provider,$socialId,$avatar,$user['id']]);
        }
    }
    if (!$user) {
        // Create new account
        if (!$name) $name = $email ? explode('@',$email)[0] : ucfirst($provider).' User';
        $uid  = db_insert(
            "INSERT INTO users(name,email,phone,password,password_hash,role,status,email_verified,social_provider,social_id,avatar_url) VALUES(?,?,?,?,?,'customer','active',1,?,?,?)",
            [$name,$email?:null,'','','',$provider,$socialId,$avatar]
        );
        $user = db_row("SELECT * FROM users WHERE id=?", [$uid]);
    }
    if (!$user || $user['status'] === 'inactive') return ['ok'=>false,'msg'=>'Account deactivated.'];
    if ($user['status'] === 'pending') db_exec("UPDATE users SET status='active' WHERE id=?", [$user['id']]);
    login_user($user);
    return ['ok'=>true,'user'=>$user];
}
