<?php
/**
 * Avatar Upload Handler — /user/avatar_upload.php
 * Handles: upload new photo, replace existing, delete photo.
 * Validates type/size, strips EXIF, resizes to a square, saves as JPG.
 */
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'message'=>'Method not allowed.']); exit;
}
if (!csrf_verify()) {
    echo json_encode(['ok'=>false,'message'=>'Security check failed. Refresh and try again.']); exit;
}

$uid  = current_uid();
$act  = trim($_POST['action'] ?? 'upload');
$dir  = RF_ROOT.'/assets/uploads/avatars';
$user = db_row("SELECT avatar_url FROM users WHERE id=?", [$uid]);

/* ── Helper: remove an existing local avatar file ── */
function rf_delete_avatar_file(?string $url, string $dir): void {
    if (!$url) return;
    // Only delete files that live in our own uploads folder (never touch Google/Facebook URLs)
    if (strpos($url, '/assets/uploads/avatars/') === false) return;
    $filename = basename(parse_url($url, PHP_URL_PATH));
    $path = $dir.'/'.$filename;
    if (is_file($path)) @unlink($path);
}

/* ═══════════════════════════════════
   DELETE PHOTO
═══════════════════════════════════ */
if ($act === 'delete') {
    rf_delete_avatar_file($user['avatar_url'] ?? null, $dir);
    db_exec("UPDATE users SET avatar_url=NULL WHERE id=?", [$uid]);
    $_SESSION['udata']['avatar_url'] = null;
    echo json_encode(['ok'=>true,'message'=>'Photo removed.','avatar_url'=>null]);
    exit;
}

/* ═══════════════════════════════════
   UPLOAD / REPLACE PHOTO
═══════════════════════════════════ */
if (empty($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['ok'=>false,'message'=>'No file selected.']); exit;
}

$file = $_FILES['photo'];

// Basic upload error check
if ($file['error'] !== UPLOAD_ERR_OK) {
    $map = [
        UPLOAD_ERR_INI_SIZE => 'File is too large (server limit).',
        UPLOAD_ERR_FORM_SIZE=> 'File is too large.',
        UPLOAD_ERR_PARTIAL  => 'Upload was interrupted. Try again.',
    ];
    echo json_encode(['ok'=>false,'message'=>$map[$file['error']] ?? 'Upload failed.']); exit;
}

// Size limit — 4MB
if ($file['size'] > 4 * 1024 * 1024) {
    echo json_encode(['ok'=>false,'message'=>'Image must be under 4MB.']); exit;
}

// Verify it's really an image (not just a renamed file) via getimagesize
$info = @getimagesize($file['tmp_name']);
if ($info === false) {
    echo json_encode(['ok'=>false,'message'=>'That file is not a valid image.']); exit;
}
$mime = $info['mime'];
$allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
if (!isset($allowed[$mime])) {
    echo json_encode(['ok'=>false,'message'=>'Only JPG, PNG, or WEBP images are allowed.']); exit;
}

// Load source image based on type
switch ($mime) {
    case 'image/jpeg': $src = @imagecreatefromjpeg($file['tmp_name']); break;
    case 'image/png':  $src = @imagecreatefrompng($file['tmp_name']);  break;
    case 'image/webp': $src = @imagecreatefromwebp($file['tmp_name']); break;
    default: $src = false;
}
if (!$src) {
    echo json_encode(['ok'=>false,'message'=>'Could not process this image.']); exit;
}

// ── Crop to square + resize to 320x320 (strips EXIF/GPS by re-encoding) ──
$srcW = imagesx($src); $srcH = imagesy($src);
$side = min($srcW, $srcH);
$srcX = (int)(($srcW - $side) / 2);
$srcY = (int)(($srcH - $side) / 2);

$size = 320;
$dst  = imagecreatetruecolor($size, $size);
// Preserve transparency for PNG source, flatten to white otherwise
$white = imagecolorallocate($dst, 255, 255, 255);
imagefill($dst, 0, 0, $white);
imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $size, $size, $side, $side);
imagedestroy($src);

// Ensure upload dir exists
if (!is_dir($dir)) @mkdir($dir, 0755, true);

// Unique filename
$filename = 'u'.$uid.'_'.bin2hex(random_bytes(6)).'.jpg';
$fullPath = $dir.'/'.$filename;

if (!imagejpeg($dst, $fullPath, 85)) {
    imagedestroy($dst);
    echo json_encode(['ok'=>false,'message'=>'Failed to save image.']); exit;
}
imagedestroy($dst);

// Remove old local avatar (if any) now that new one is saved
rf_delete_avatar_file($user['avatar_url'] ?? null, $dir);

$publicUrl = SITE_URL.'/assets/uploads/avatars/'.$filename;
db_exec("UPDATE users SET avatar_url=? WHERE id=?", [$publicUrl, $uid]);
$_SESSION['udata']['avatar_url'] = $publicUrl;

echo json_encode(['ok'=>true,'message'=>'Profile photo updated.','avatar_url'=>$publicUrl]);
