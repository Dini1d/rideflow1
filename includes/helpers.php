<?php
/**
 * RideFlow — Core Helpers
 * All utility functions used across the application.
 */

/* ── Session bootstrap ────────────────────────────────────── */
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    // Regenerate session ID periodically to prevent fixation
    if (!isset($_SESSION['_init'])) {
        session_regenerate_id(true);
        $_SESSION['_init'] = time();
    }
}

/* ── CSRF ─────────────────────────────────────────────────── */
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(CSRF_TOKEN_LEN));
    }
    return $_SESSION['_csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="'.csrf_token().'">';
}
function csrf_verify(): bool {
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return !empty($token) && hash_equals(csrf_token(), $token);
}
function csrf_guard(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
        http_response_code(403);
        die(json_encode(['ok'=>false,'message'=>'CSRF validation failed.']));
    }
}

/* ── XSS / Output ─────────────────────────────────────────── */
function e(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/* ── Redirects ────────────────────────────────────────────── */
function redirect(string $url, int $code = 302): never {
    header('Location: '.$url, true, $code);
    exit;
}
function redirect_back(string $fallback = '/'): never {
    redirect($_SERVER['HTTP_REFERER'] ?? $fallback);
}

/* ── Flash messages ───────────────────────────────────────── */
function flash(string $type, string $msg): void {
    $_SESSION['_flash'] = ['type' => $type, 'msg' => $msg];
}
function flash_get(): array {
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}
function flash_html(): string {
    $f = flash_get();
    if (!$f) return '';
    $icons = ['ok'=>'check-circle','bad'=>'times-circle','warn'=>'exclamation-triangle','info'=>'info-circle'];
    $ico   = $icons[$f['type']] ?? 'info-circle';
    return '<div class="alert a-'.$f['type'].'"><i class="fas fa-'.$ico.'"></i> '.e($f['msg']).'</div>';
}

/* ── Auth helpers ─────────────────────────────────────────── */
function is_logged_in(): bool { return !empty($_SESSION['uid']); }
function is_admin():     bool { return ($_SESSION['urole'] ?? '') === 'admin'; }
function is_driver():    bool { return ($_SESSION['urole'] ?? '') === 'driver'; }
function is_customer():  bool { return ($_SESSION['urole'] ?? '') === 'customer'; }
function current_uid():  int  { return (int)($_SESSION['uid'] ?? 0); }
function current_user(): array { return $_SESSION['udata'] ?? []; }

function require_login(string $redirect = ''): void {
    if (!is_logged_in()) {
        $_SESSION['_after_login'] = $_SERVER['REQUEST_URI'];
        redirect(SITE_URL.'/login.php');
    }
}
function require_admin(): void {
    require_login();
    if (!is_admin()) redirect(SITE_URL.'/index.php');
}
function require_driver(): void {
    require_login();
    if (!is_driver()) redirect(SITE_URL.'/index.php');
}

function role_home_url(?string $role = null): string {
    $role = $role ?: (string)($_SESSION['urole'] ?? 'customer');
    if ($role === 'admin')  return SITE_URL.'/admin/dashboard.php';
    if ($role === 'driver') return SITE_URL.'/driver/dashboard.php';
    return SITE_URL.'/user/dashboard.php';
}

function greeting_for_now(): string {
    $h = (int) date('G');
    if ($h < 12) return 'Good morning';
    if ($h < 17) return 'Good afternoon';
    return 'Good evening';
}

function login_user(array $user): void {
    if (!headers_sent()) {
        session_regenerate_id(true);
    }
    $_SESSION['uid']    = $user['id'];
    $_SESSION['uname']  = $user['name'];
    $_SESSION['urole']  = $user['role'];
    $_SESSION['uemail'] = $user['email'] ?? '';
    $_SESSION['udata']  = $user;
}
function logout_user(): void {
    session_unset();
    session_destroy();
    setcookie(SESSION_NAME, '', time()-3600, '/');
}

/* ── Password ─────────────────────────────────────────────── */
function hash_password(string $pw): string {
    return password_hash($pw, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

function stored_password_hash(array $user): string {
    return (string)($user['password_hash'] ?? $user['password'] ?? '');
}

function update_user_password(int $userId, string $hash): void {
    db_exec("UPDATE users SET password=?, password_hash=? WHERE id=?", [$hash, $hash, $userId]);
}

function verify_password(string $pw, string $hash): bool {
    if (password_verify($pw, $hash)) {
        return true;
    }
    return password_legacy_verify($pw, $hash);
}

function password_legacy_verify(string $pw, string $hash): bool {
    if (!ctype_xdigit($hash)) {
        return false;
    }
    if (strlen($hash) === 32) {
        return hash_equals($hash, md5($pw));
    }
    if (strlen($hash) === 40) {
        return hash_equals($hash, sha1($pw));
    }
    return false;
}

/* ── Phone normalization ──────────────────────────────────── */
function normalize_phone(string $p): string {
    $p = preg_replace('/\s+/', '', $p);
    if (preg_match('/^0[0-9]{9}$/', $p)) return '+94'.substr($p, 1);
    if (preg_match('/^94[0-9]{9}$/', $p)) return '+'.$p;
    return $p;
}
function mask_phone(string $p): string {
    if (strlen($p) < 7) return $p;
    return substr($p, 0, 4).'***'.substr($p, -3);
}

/* ── Booking ref ──────────────────────────────────────────── */
function booking_ref(): string {
    return 'RF-'.strtoupper(substr(md5(uniqid('', true)), 0, 6));
}

/* ── Token generation ─────────────────────────────────────── */
function gen_token(int $bytes = 32): string {
    return bin2hex(random_bytes($bytes));
}

/* ── Money ────────────────────────────────────────────────── */
function num_or_zero(mixed $value): float {
    if (is_int($value) || is_float($value)) return (float)$value;
    if (is_string($value) && is_numeric($value)) return (float)$value;
    return 0.0;
}

function money(mixed $amount, string $currency = 'Rs.'): string {
    return $currency.' '.number_format(num_or_zero($amount), 2);
}

/* ── Pagination ───────────────────────────────────────────── */
function paginate(string $sql, array $params, int $page, int $limit = PER_PAGE): array {
    $page   = max(1, $page);
    $offset = ($page - 1) * $limit;

    // Build the total count from the original query instead of trying to rebuild
    // the SELECT clause from the last FROM token, which breaks on joined subqueries.
    $countSql = preg_replace('/\s+ORDER\s+BY\s+.*$/is', '', $sql);
    $countSql = preg_replace('/\s+LIMIT\s+\d+(\s+OFFSET\s+\d+)?/i', '', $countSql);
    $countSql = 'SELECT COUNT(*) AS total FROM (' . $countSql . ') AS _cnt';

    $total    = (int) db_val($countSql, $params);
    $items    = db_all($sql." LIMIT $limit OFFSET $offset", $params);
    return [
        'items'  => $items,
        'total'  => $total,
        'page'   => $page,
        'limit'  => $limit,
        'pages'  => (int) ceil($total / $limit),
    ];
}

/* ── Status badge ─────────────────────────────────────────── */
function status_badge(string $status): string {
    $map = [
        'active'   =>'ok','confirmed'=>'ok','completed'=>'ok','paid'=>'ok',
        'pending'  =>'warn','scheduled'=>'info','unpaid'=>'warn',
        'cancelled'=>'bad','inactive' =>'bad','failed'=>'bad','suspended'=>'bad',
        'departed' =>'info','arrived'  =>'ok',
    ];
    $cl = $map[$status] ?? 'dim';
    return '<span class="badge b-'.$cl.'">'.ucfirst($status).'</span>';
}

/* ── Site setting helper ──────────────────────────────────── */
function setting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        $cache[$key] = $default;

        if (db_table_exists('site_settings')) {
            $cache[$key] = db_val("SELECT setting_value FROM site_settings WHERE setting_key=?", [$key]) ?: $default;
        } elseif (db_table_exists('settings')) {
            $cache[$key] = db_val("SELECT setting_value FROM settings WHERE setting_key=?", [$key]) ?: $default;
        }
    }
    return $cache[$key] ?: $default;
}

/* ── JSON API response ────────────────────────────────────── */
function api_ok(mixed $data = [], int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
    exit;
}
function api_err(int $code, string $message): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'code'=>$code,'message'=>$message], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Notification ─────────────────────────────────────────── */
function notify(int $userId, string $title, string $message, string $type = 'system', string $link = ''): void {
    if (!db_table_exists('notifications')) {
        return;
    }
    db_exec("INSERT INTO notifications(user_id,title,message,type,link) VALUES(?,?,?,?,?)",
        [$userId, $title, $message, $type, $link]);
}

/* ── Avatar upload / deletion helpers ─────────────────────── */
function avatar_upload(array $file, int $userId): string|false {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return false;
    // Limit to 2MB
    $maxSize = 2 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) return false;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    $ext = null;
    if ($mime === 'image/jpeg' || $mime === 'image/jpg' || $mime === 'image/pjpeg') $ext = 'jpg';
    elseif ($mime === 'image/png' || $mime === 'image/x-png') $ext = 'png';
    elseif ($mime === 'image/webp') $ext = 'webp';
    else return false;

    $dir = __DIR__ . '/../assets/images/avatars';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $name = $userId . '_' . time() . '.' . $ext;
    $dst  = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dst)) return false;

    // Store public URL
    $url = SITE_URL . '/assets/images/avatars/' . $name;
    db_exec("UPDATE users SET avatar_url=? WHERE id=?", [$url, $userId]);
    return $url;
}

function document_upload(array $file, int $userId, string $prefix, int $maxSize = 5 * 1024 * 1024): string|false {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return false;
    if (($file['size'] ?? 0) > $maxSize) return false;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    $ext = null;
    if ($mime === 'image/jpeg' || $mime === 'image/jpg' || $mime === 'image/pjpeg') $ext = 'jpg';
    elseif ($mime === 'image/png' || $mime === 'image/x-png') $ext = 'png';
    elseif ($mime === 'image/webp') $ext = 'webp';
    else return false;

    $dir = __DIR__ . '/../assets/images/verification';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $name = $userId . '_' . preg_replace('/[^a-z0-9_-]+/i', '_', $prefix) . '_' . time() . '.' . $ext;
    $dst  = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dst)) return false;

    return SITE_URL . '/assets/images/verification/' . $name;
}

function verification_badge(string $status): string {
    return match ($status) {
        'approved' => '<span class="badge b-ok"><i class="fas fa-check"></i> Verified</span>',
        'rejected' => '<span class="badge b-bad"><i class="fas fa-times"></i> Rejected</span>',
        default    => '<span class="badge b-warn"><i class="fas fa-hourglass-half"></i> Pending Review</span>',
    };
}

function avatar_delete(int $userId): bool {
    $row = db_row("SELECT avatar_url FROM users WHERE id=?", [$userId]);
    if (!$row || empty($row['avatar_url'])) return true;
    $basename = basename($row['avatar_url']);
    $file = __DIR__ . '/../assets/images/avatars/' . $basename;
    if (is_file($file)) @unlink($file);
    db_exec("UPDATE users SET avatar_url=NULL WHERE id=?", [$userId]);
    return true;
}

function upload_error_message(int $code): string {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Upload failed: file too large. Maximum size is 2MB.';
        case UPLOAD_ERR_PARTIAL:
            return 'Upload failed: file was only partially uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Upload failed: missing temporary folder on server.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Upload failed: cannot write temporary file.';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload failed: a server extension blocked the upload.';
        default:
            return 'Upload failed. Use JPG, PNG or WEBP under 2MB.';
    }
}
