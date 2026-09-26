<?php
/**
 * RideFlow — Public Header Template
 * Include at the top of every public page.
 * Expects $pageTitle to be set before inclusion.
 */
if (!defined('RF_ROOT')) define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
$pageTitle = isset($pageTitle) ? $pageTitle.' — '.SITE_NAME : SITE_NAME;
$_flash    = flash_get();
$_uid      = current_uid();
$_notifCount = 0;
if ($_uid && db_table_exists('notifications')) {
    $_notifCount = (int) db_val("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0", [$_uid]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="RideFlow — Sri Lanka Transport Booking">
<title><?= e($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,900&family=DM+Sans:wght@400;500;600;700&family=DM+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=20260923-professional">
<?php if (isset($extraCss)) echo $extraCss; ?>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <div class="container">
    <div class="navbar-inner">
      <a href="<?= SITE_URL ?>" class="nav-brand">
        <div class="nav-logo"><i class="fas fa-route"></i></div>
        <span class="nav-name">Ride<em>Flow</em></span>
      </a>

      <div class="nav-links">
        <a href="<?= SITE_URL ?>" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='index.php'?'active':'' ?>">Home</a>
        <a href="<?= SITE_URL ?>/search.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='search.php'?'active':'' ?>">Search</a>
        <a href="<?= SITE_URL ?>/contact.php" class="nav-link">Contact</a>
        <?php if (is_logged_in() && is_admin()): ?>
        <a href="<?= SITE_URL ?>/admin/dashboard.php" class="nav-link text-fire"><i class="fas fa-shield-alt"></i> Admin</a>
        <?php endif; ?>
        <?php if (is_logged_in() && is_driver()): ?>
        <a href="<?= SITE_URL ?>/driver/dashboard.php" class="nav-link text-fire"><i class="fas fa-car"></i> Driver</a>
        <?php endif; ?>
      </div>

      <div class="nav-actions">
        <?php if (is_logged_in()): ?>
        <?php if ($_notifCount > 0): ?>
        <a href="<?= SITE_URL ?>/user/notifications.php" class="btn btn-dark btn-icon" style="position:relative">
          <i class="fas fa-bell"></i>
          <span style="position:absolute;top:-4px;right:-4px;width:16px;height:16px;border-radius:50%;background:var(--bad);border:2px solid var(--ink);display:flex;align-items:center;justify-content:center;font-size:.55rem;font-weight:700;color:#fff"><?= $_notifCount ?></span>
        </a>
        <?php endif; ?>
        <div class="nav-user" data-user-menu>
          <button type="button" class="nav-user-toggle" aria-expanded="false" aria-label="Open user menu">
            <div class="nav-avatar">
              <?php if (!empty($_SESSION['udata']['avatar_url'])): ?>
                <img src="<?= e($_SESSION['udata']['avatar_url']) ?>" alt="">
              <?php else: ?>
                <?= strtoupper(substr($_SESSION['uname'] ?? 'U', 0, 1)) ?>
              <?php endif; ?>
            </div>
            <span style="font-size:.85rem;color:var(--chalk2)"><?= e(explode(' ',$_SESSION['uname']??'')[0]) ?></span>
            <i class="fas fa-chevron-down" style="font-size:.65rem;color:var(--chalk4)"></i>
          </button>
          <div class="nav-dropdown">
            <div class="nav-dd-header">
              <span class="nav-dd-title">System</span>
              <span class="nav-dd-sub">Quick access</span>
            </div>
            <div class="nav-dd-grid">
              <a href="<?= SITE_URL ?>/user/dashboard.php" class="nav-dd-card">
                <i class="fas fa-tachometer-alt"></i>
                <span>
                  <strong>Dashboard</strong>
                  <small>Overview</small>
                </span>
              </a>
              <a href="<?= SITE_URL ?>/user/bookings.php" class="nav-dd-card">
                <i class="fas fa-ticket-alt"></i>
                <span>
                  <strong>My Bookings</strong>
                  <small>Tickets & trips</small>
                </span>
              </a>
              <a href="<?= SITE_URL ?>/user/profile.php" class="nav-dd-card">
                <i class="fas fa-user-circle"></i>
                <span>
                  <strong>Profile</strong>
                  <small>Account details</small>
                </span>
              </a>
            </div>
            <div class="nav-dd-sep"></div>
            <a href="<?= SITE_URL ?>/logout.php" class="nav-dd-item danger"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
          </div>
        </div>
        <?php else: ?>
        <a href="<?= SITE_URL ?>/login.php"    class="btn btn-ghost btn-sm">Sign In</a>
        <a href="<?= SITE_URL ?>/register.php" class="btn btn-fire btn-sm">Get Started</a>
        <?php endif; ?>
        <button class="hamburger" onclick="toggleMobileMenu()" aria-label="Menu"><i class="fas fa-bars"></i></button>
      </div>
    </div>
  </div>
</nav>

<!-- Flash alert -->
<?php if ($_flash): ?>
<div class="container" style="padding-top:.75rem">
  <div class="alert a-<?= e($_flash['type']) ?>">
    <i class="fas fa-<?= $_flash['type']==='ok'?'check':'exclamation' ?>-circle"></i>
    <?= e($_flash['msg']) ?>
  </div>
</div>
<?php endif; ?>

<script>window.SITE_URL='<?= SITE_URL ?>';</script>
