<?php
define('RF_ROOT', dirname(__DIR__));
$pageTitle = 'My Dashboard';
require_once RF_ROOT.'/includes/header.php';
require_login();

$uid    = current_uid();
$user   = db_row("SELECT * FROM users WHERE id=?", [$uid]);
$stats  = db_row("SELECT COUNT(*) total, SUM(CASE WHEN booking_status='confirmed' THEN 1 ELSE 0 END) confirmed,
                  SUM(CASE WHEN booking_status='cancelled' THEN 1 ELSE 0 END) cancelled,
                  SUM(CASE WHEN payment_status='paid' THEN total_fare ELSE 0 END) spent
                  FROM bookings WHERE user_id=?", [$uid]);
$stats = array_merge([
  'total' => 0,
  'confirmed' => 0,
  'cancelled' => 0,
  'spent' => 0,
], $stats ?: []);
$recent = db_all("SELECT b.*, r.origin, r.destination, t.type_name, s.departure_date, s.departure_time
                  FROM bookings b JOIN schedules s ON s.id=b.schedule_id
                  JOIN routes r ON r.id=s.route_id JOIN transport_types t ON t.id=r.type_id
                    WHERE b.user_id=? ORDER BY b.created_at DESC LIMIT 5", [$uid]);
          $notifs = db_table_exists('notifications')
            ? db_all("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5", [$uid])
            : [];
// Mark as read
          if (db_table_exists('notifications')) {
            db_exec("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0", [$uid]);
          }
require_once RF_ROOT.'/includes/smart_tips.php';
$upcoming = null;
foreach ($recent as $b) {
    if (in_array($b['booking_status'], ['confirmed','pending'], true) && $b['departure_date'] >= date('Y-m-d')) {
        $upcoming = $b;
        break;
    }
}
$unpaidCount = (int) db_val("SELECT COUNT(*) FROM bookings WHERE user_id=? AND payment_status='unpaid' AND booking_status!='cancelled'", [$uid]);
$userTips = smart_dashboard_tips('customer', [
  'unpaid'   => $unpaidCount,
  'upcoming' => $upcoming,
  'total'    => (int)($stats['total'] ?? 0),
]);
?>
<div class="main-content">
<div class="container">

  <!-- Welcome header -->
  <div style="margin-bottom:2rem;display:flex;align-items:center;gap:1rem">
    <div style="width:56px;height:56px;border-radius:50%;background:var(--fire);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:#fff;box-shadow:var(--shadow-fire)">
      <?php if ($user['avatar_url']): ?>
      <img src="<?= e($user['avatar_url']) ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
      <?php else: ?>
      <?= strtoupper(substr($user['name'],0,1)) ?>
      <?php endif; ?>
    </div>
    <div>
      <h1 style="font-size:1.6rem"><?= e(greeting_for_now()) ?>, <?= e(explode(' ',$user['name'])[0]) ?></h1>
      <p style="margin:0;font-size:.88rem">Your travel pulse — tips, tickets, and what’s next</p>
    </div>
    <div style="margin-left:auto">
      <a href="<?= SITE_URL ?>/search.php" class="btn btn-fire"><i class="fas fa-search"></i> Find a Trip</a>
    </div>
  </div>

  <?= render_smart_tips($userTips) ?>

  <!-- Stats -->
  <div class="grid-4" style="margin-bottom:2rem">
    <?php $statItems=[
      ['ico'=>'ticket-alt','col'=>'var(--fire)','bg'=>'var(--fire-soft)','num'=>(int)$stats['total'],'lbl'=>'Total Bookings'],
      ['ico'=>'check-circle','col'=>'var(--ok)','bg'=>'rgba(34,197,94,.1)','num'=>(int)$stats['confirmed'],'lbl'=>'Confirmed'],
      ['ico'=>'times-circle','col'=>'var(--bad)','bg'=>'rgba(239,68,68,.1)','num'=>(int)$stats['cancelled'],'lbl'=>'Cancelled'],
      ['ico'=>'rupee-sign','col'=>'var(--warn)','bg'=>'rgba(245,158,11,.1)','num'=>'Rs.'.number_format((float)$stats['spent'],0),'lbl'=>'Total Spent'],
    ];
    foreach ($statItems as $s): ?>
    <div class="stat-card">
      <div class="stat-ico" style="background:<?= $s['bg'] ?>;color:<?= $s['col'] ?>"><i class="fas fa-<?= $s['ico'] ?>"></i></div>
      <div><div class="stat-num" style="font-size:1.4rem"><?= $s['num'] ?></div><div class="stat-lbl"><?= $s['lbl'] ?></div></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="grid-2" style="gap:1.5rem;align-items:start">

    <!-- Recent bookings -->
    <div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
        <h2 style="font-size:1.15rem">Recent Bookings</h2>
        <a href="<?= SITE_URL ?>/user/bookings.php" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <?php if (!$recent): ?>
      <div class="card card-pad" style="text-align:center;padding:2.5rem">
        <div style="font-size:2.5rem;margin-bottom:.75rem">🎟️</div>
        <h3 style="font-size:1rem;margin-bottom:.4rem">No bookings yet</h3>
        <p style="margin-bottom:1rem;font-size:.88rem">Book your first trip now!</p>
        <a href="<?= SITE_URL ?>/search.php" class="btn btn-fire btn-sm">Search Trips</a>
      </div>
      <?php else: ?>
      <?php foreach ($recent as $b):
        $tc = strtolower(str_replace([' ','-'],'',$b['type_name']));
        $tc = $tc==='threewheel'?'wheel':$tc;
      ?>
      <div class="card card-pad" style="margin-bottom:.75rem">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">
          <div>
            <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:.4rem;flex-wrap:wrap">
              <span class="type-pill tp-<?= $tc ?>"><?= e($b['type_name']) ?></span>
              <?= status_badge($b['booking_status']) ?>
            </div>
            <div style="font-weight:700;color:var(--chalk)"><?= e($b['origin']) ?> → <?= e($b['destination']) ?></div>
            <div style="font-size:.8rem;color:var(--chalk3)"><?= date('M j, Y',strtotime($b['departure_date'])) ?> · <?= substr($b['departure_time'],0,5) ?></div>
          </div>
          <div style="text-align:right">
            <div style="font-family:var(--ff-mono);font-size:.78rem;color:var(--chalk4)"><?= e($b['booking_ref']) ?></div>
            <div style="color:var(--fire);font-weight:700">Rs.<?= number_format($b['total_fare'],0) ?></div>
            <?php if ($b['booking_status']==='confirmed'): ?>
            <a href="<?= SITE_URL ?>/user/ticket.php?booking=<?= $b['id'] ?>" class="btn btn-fire btn-sm" style="margin-top:.4rem"><i class="fas fa-ticket-alt"></i> Ticket</a>
            <?php elseif ($b['payment_status']==='unpaid'): ?>
            <a href="<?= SITE_URL ?>/payment/checkout.php?booking=<?= $b['id'] ?>" class="btn btn-ok btn-sm" style="margin-top:.4rem"><i class="fas fa-credit-card"></i> Pay Now</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Notifications & Quick links -->
    <div>
      <h2 style="font-size:1.15rem;margin-bottom:1rem">Notifications</h2>
      <?php if (!$notifs): ?>
      <div class="card card-pad" style="text-align:center;color:var(--chalk3);padding:2rem"><i class="fas fa-bell-slash" style="font-size:1.8rem;margin-bottom:.5rem"></i><br>No new notifications</div>
      <?php else: ?>
      <?php foreach ($notifs as $n): ?>
      <div class="card card-pad" style="margin-bottom:.65rem;border-left:3px solid var(--<?= $n['type']==='booking'?'fire':($n['type']==='payment'?'ok':'info') ?>)">
        <div style="font-size:.85rem;font-weight:600;color:var(--chalk);margin-bottom:.25rem"><?= e($n['title']) ?></div>
        <div style="font-size:.8rem;color:var(--chalk3)"><?= e($n['message']) ?></div>
        <div style="font-size:.72rem;color:var(--chalk4);margin-top:.3rem"><?= date('M j, H:i',strtotime($n['created_at'])) ?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

      <!-- Quick links -->
      <h2 style="font-size:1.15rem;margin-top:1.5rem;margin-bottom:1rem">Quick Links</h2>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem">
        <?php $links=[
          ['href'=>'/search.php',         'ico'=>'search',        'lbl'=>'Search Trips', 'col'=>'var(--fire)'],
          ['href'=>'/user/bookings.php',   'ico'=>'ticket-alt',    'lbl'=>'My Bookings',  'col'=>'var(--ok)'],
          ['href'=>'/user/profile.php',    'ico'=>'user-circle',   'lbl'=>'Edit Profile', 'col'=>'var(--info)'],
          ['href'=>'/contact.php',         'ico'=>'headset',       'lbl'=>'Get Support',  'col'=>'var(--warn)'],
        ];
        foreach ($links as $l): ?>
        <a href="<?= SITE_URL.$l['href'] ?>" class="card card-pad" style="display:flex;align-items:center;gap:.65rem;text-decoration:none;transition:border-color .18s" onmouseover="this.style.borderColor='var(--rim2)'" onmouseout="this.style.borderColor='var(--rim)'">
          <div style="width:36px;height:36px;border-radius:10px;background:var(--veil2);display:flex;align-items:center;justify-content:center;color:<?= $l['col'] ?>;font-size:.9rem;flex-shrink:0"><i class="fas fa-<?= $l['ico'] ?>"></i></div>
          <span style="font-size:.85rem;font-weight:600;color:var(--chalk)"><?= $l['lbl'] ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
</div>
<?php require_once RF_ROOT.'/includes/footer.php'; ?>
