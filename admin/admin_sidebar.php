<?php
/**
 * Admin Sidebar Navigation
 */
$cur = basename($_SERVER['PHP_SELF']);
$pending_bk = (int) db_val("SELECT COUNT(*) FROM bookings WHERE booking_status='pending'");
$unread_msg = (int) db_val("SELECT COUNT(*) FROM contact_messages WHERE is_read=0");
$pending_users = (int) db_val("SELECT COUNT(*) FROM users WHERE status='pending' AND role='customer'");
?>
<div class="admin-sidebar" id="adminSidebar">
  <!-- Brand -->
  <div class="sidebar-brand">
    <div class="nav-logo" style="width:34px;height:34px;border-radius:10px;font-size:.9rem"><i class="fas fa-route"></i></div>
    <span style="font-family:var(--ff-disp);font-size:1.1rem;font-weight:700;color:var(--chalk)">Ride<em style="color:var(--fire);font-style:normal">Flow</em></span>
  </div>

  <!-- Overview -->
  <div class="sidebar-sec-label">Overview</div>
  <a href="<?= SITE_URL ?>/admin/dashboard.php" class="sidebar-link <?= $cur==='dashboard.php'?'on':'' ?>">
    <i class="fas fa-tachometer-alt"></i> Dashboard
  </a>
  <a href="<?= SITE_URL ?>/admin/reports.php" class="sidebar-link <?= $cur==='reports.php'?'on':'' ?>">
    <i class="fas fa-chart-bar"></i> Reports
  </a>

  <!-- Transport -->
  <div class="sidebar-sec-label">Transport</div>
  <a href="<?= SITE_URL ?>/admin/vehicles.php" class="sidebar-link <?= $cur==='vehicles.php'?'on':'' ?>">
    <i class="fas fa-bus"></i> Vehicles
  </a>
  <a href="<?= SITE_URL ?>/admin/routes.php" class="sidebar-link <?= $cur==='routes.php'?'on':'' ?>">
    <i class="fas fa-route"></i> Routes
  </a>
  <a href="<?= SITE_URL ?>/admin/schedules.php" class="sidebar-link <?= $cur==='schedules.php'?'on':'' ?>">
    <i class="fas fa-calendar-alt"></i> Schedules
  </a>
  <a href="<?= SITE_URL ?>/admin/drivers.php" class="sidebar-link <?= $cur==='drivers.php'?'on':'' ?>">
    <i class="fas fa-id-card"></i> Drivers
  </a>

  <!-- Bookings -->
  <div class="sidebar-sec-label">Bookings</div>
  <a href="<?= SITE_URL ?>/admin/bookings.php" class="sidebar-link <?= $cur==='bookings.php'?'on':'' ?>">
    <i class="fas fa-ticket-alt"></i> Bookings
    <?php if ($pending_bk > 0): ?>
    <span class="badge b-warn" style="font-size:.62rem;padding:.15rem .55rem"><?= $pending_bk ?></span>
    <?php endif; ?>
  </a>
  <a href="<?= SITE_URL ?>/admin/payments.php" class="sidebar-link <?= $cur==='payments.php'?'on':'' ?>">
    <i class="fas fa-credit-card"></i> Payments
  </a>

  <!-- Users -->
  <div class="sidebar-sec-label">Users</div>
  <a href="<?= SITE_URL ?>/admin/users.php" class="sidebar-link <?= $cur==='users.php'?'on':'' ?>">
    <i class="fas fa-users"></i> Users
    <?php if ($pending_users > 0): ?>
    <span class="badge b-fire" style="font-size:.62rem;padding:.15rem .55rem"><?= $pending_users ?></span>
    <?php endif; ?>
  </a>
  <a href="<?= SITE_URL ?>/admin/drivers.php" class="sidebar-link <?= $cur==='drivers.php'?'on':'' ?>">
    <i class="fas fa-car"></i> Drivers
  </a>
  <a href="<?= SITE_URL ?>/admin/feedback.php" class="sidebar-link <?= $cur==='feedback.php'?'on':'' ?>">
    <i class="fas fa-star"></i> Feedback
  </a>

  <!-- Communications -->
  <div class="sidebar-sec-label">Communications</div>
  <a href="<?= SITE_URL ?>/admin/chatbot.php" class="sidebar-link <?= $cur==='chatbot.php'?'on':'' ?>">
    <i class="fas fa-robot"></i> Chatbot FAQ
  </a>
  <a href="<?= SITE_URL ?>/admin/messages.php" class="sidebar-link <?= $cur==='messages.php'?'on':'' ?>">
    <i class="fas fa-envelope"></i> Messages
    <?php if ($unread_msg > 0): ?>
    <span class="badge b-bad" style="font-size:.62rem;padding:.15rem .55rem"><?= $unread_msg ?></span>
    <?php endif; ?>
  </a>
  <a href="<?= SITE_URL ?>/admin/newsletter.php" class="sidebar-link <?= $cur==='newsletter.php'?'on':'' ?>">
    <i class="fas fa-paper-plane"></i> Newsletter
  </a>
  <a href="<?= SITE_URL ?>/admin/subscriptions.php" class="sidebar-link <?= $cur==='subscriptions.php'?'on':'' ?>">
    <i class="fas fa-at"></i> Subscriptions
  </a>

  <!-- Settings -->
  <div class="sidebar-sec-label">Settings</div>
  <a href="<?= SITE_URL ?>/admin/settings.php" class="sidebar-link <?= $cur==='settings.php'?'on':'' ?>">
    <i class="fas fa-cog"></i> General Settings
  </a>
  <a href="<?= SITE_URL ?>/admin/integrations.php" class="sidebar-link <?= $cur==='integrations.php'?'on':'' ?>">
    <i class="fas fa-plug"></i> Integrations
  </a>
  <a href="<?= SITE_URL ?>/admin/google_setup.php" class="sidebar-link <?= $cur==='google_setup.php'?'on':'' ?>">
    <svg style="width:16px;height:16px;vertical-align:middle;margin-right:.1rem" viewBox="0 0 48 48"><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/></svg>
    Google Login
  </a>

  <!-- Bottom: view site + logout -->
  <div style="margin-top:auto;padding:1rem;border-top:1px solid var(--rim)">
    <a href="<?= SITE_URL ?>/" target="_blank" class="sidebar-link" style="font-size:.82rem"><i class="fas fa-external-link-alt"></i> View Site</a>
    <a href="<?= SITE_URL ?>/logout.php" class="sidebar-link" style="color:var(--bad);font-size:.82rem"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
  </div>
</div>
