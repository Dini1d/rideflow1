<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_once RF_ROOT.'/includes/smart_tips.php';
require_admin();

// Core stats
$today = date('Y-m-d');
$month = date('Y-m');
$s = [
  'total_bookings'  => db_val("SELECT COUNT(*) FROM bookings"),
  'today_bookings'  => db_val("SELECT COUNT(*) FROM bookings WHERE DATE(created_at)=?", [$today]),
  'month_revenue'   => db_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed' AND TO_CHAR(paid_at, 'YYYY-MM')=?", [$month]),
  'total_revenue'   => db_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'"),
  'active_users'    => db_val("SELECT COUNT(*) FROM users WHERE status='active' AND role='customer'"),
  'pending_bk'      => db_val("SELECT COUNT(*) FROM bookings WHERE booking_status='pending'"),
  'unpaid_bk'       => db_val("SELECT COUNT(*) FROM bookings WHERE payment_status='unpaid' AND booking_status!='cancelled'"),
  'schedules_today' => db_val("SELECT COUNT(*) FROM schedules WHERE departure_date=? AND status='scheduled'", [$today]),
];

// Revenue last 14 days
$revenue14 = db_all("
    SELECT DATE(paid_at) d, SUM(amount) total
    FROM payments WHERE status='completed' AND paid_at>=CURRENT_TIMESTAMP - INTERVAL '14 days'
    GROUP BY DATE(paid_at) ORDER BY d");

// Recent bookings
$recentBk = db_all("
    SELECT b.booking_ref, b.booking_status, b.total_fare, b.created_at,
           u.name AS uname, r.origin, r.destination, t.type_name
    FROM bookings b JOIN users u ON u.id=b.user_id
    JOIN schedules sc ON sc.id=b.schedule_id JOIN routes r ON r.id=sc.route_id
    JOIN transport_types t ON t.id=r.type_id
    ORDER BY b.created_at DESC LIMIT 8");

// Revenue by gateway
$byGateway = db_all("SELECT gateway, SUM(amount) total, COUNT(*) cnt FROM payments WHERE status='completed' GROUP BY gateway ORDER BY total DESC");

// Transport type stats
$typeStats = db_all("SELECT t.type_name, COUNT(b.id) bk_count, SUM(b.total_fare) revenue
    FROM transport_types t
    LEFT JOIN routes r ON r.type_id=t.id
    LEFT JOIN schedules sc ON sc.route_id=r.id
    LEFT JOIN bookings b ON b.schedule_id=sc.id AND b.booking_status='confirmed'
    WHERE t.is_active=1 GROUP BY t.id ORDER BY bk_count DESC");

$pendingUsers = (int) db_val("SELECT COUNT(*) FROM users WHERE status='pending' AND role='customer'");
$unreadMsg    = (int) db_val("SELECT COUNT(*) FROM contact_messages WHERE is_read=0");
$adminTips = smart_dashboard_tips('admin', [
  'pending_bk'      => $s['pending_bk'],
  'unpaid_bk'       => $s['unpaid_bk'],
  'pending_users'   => $pendingUsers,
  'unread_msg'      => $unreadMsg,
  'schedules_today' => $s['schedules_today'],
  'today_bookings'  => $s['today_bookings'],
]);

$pageTitle = 'Admin Dashboard';
$userName = trim((string)($_SESSION['uname'] ?? 'Admin'));
$userFirstName = explode(' ', $userName)[0] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> — <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,900&family=DM+Sans:wght@400;500;600;700&family=DM+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=20260923-professional">
</head>
<body>
<div class="admin-layout">

<!-- SIDEBAR -->
<?php require_once RF_ROOT.'/admin/admin_sidebar.php'; ?>

<!-- MAIN -->
<div style="overflow-y:auto">
  <?php require_once RF_ROOT.'/admin/admin_topbar.php'; ?>
  <div class="admin-main">

  <!-- Page header -->
  <div class="page-hd">
    <h1><?= e(greeting_for_now()) ?>, <?= e($userFirstName) ?></h1>
    <p>Operations pulse for <?= date('l, j F') ?>. Act on the tips, then scan the numbers.</p>
  </div>

  <?= render_smart_tips($adminTips) ?>

  <!-- KPI row -->
  <div class="grid-4" style="margin-bottom:2rem">
    <?php $kpis=[
      ['ico'=>'ticket-alt','col'=>'var(--fire)','bg'=>'var(--fire-soft)','val'=>number_format((int)num_or_zero($s['total_bookings'])),'lbl'=>'Total Bookings','sub'=>$s['today_bookings'].' today'],
      ['ico'=>'coins',     'col'=>'var(--ok)',  'bg'=>'rgba(34,197,94,.1)','val'=>'Rs.'.number_format((int)num_or_zero($s['month_revenue']),0),'lbl'=>'Month Revenue','sub'=>'Rs.'.number_format((int)num_or_zero($s['total_revenue']),0).' all time'],
      ['ico'=>'users',     'col'=>'var(--info)','bg'=>'rgba(56,189,248,.1)','val'=>number_format((int)num_or_zero($s['active_users'])),'lbl'=>'Active Customers','sub'=>$s['pending_bk'].' pending bookings'],
      ['ico'=>'calendar',  'col'=>'var(--warn)','bg'=>'rgba(245,158,11,.1)','val'=>$s['schedules_today'],'lbl'=>'Trips Today','sub'=>$s['unpaid_bk'].' unpaid bookings'],
    ];
    foreach ($kpis as $k): ?>
    <div class="stat-card">
      <div class="stat-ico" style="background:<?= $k['bg'] ?>;color:<?= $k['col'] ?>"><i class="fas fa-<?= $k['ico'] ?>"></i></div>
      <div>
        <div class="stat-num"><?= $k['val'] ?></div>
        <div class="stat-lbl"><?= $k['lbl'] ?></div>
        <div style="font-size:.72rem;color:var(--chalk4);margin-top:.15rem"><?= $k['sub'] ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="grid-2" style="gap:1.5rem;margin-bottom:1.5rem;align-items:start">

    <!-- Revenue chart -->
    <div class="card card-pad">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
        <h3 style="font-size:1rem">Revenue — Last 14 Days</h3>
        <span class="label">LKR</span>
      </div>
      <canvas id="revenueChart" height="180"></canvas>
    </div>

    <!-- Type breakdown -->
    <div class="card card-pad">
      <h3 style="font-size:1rem;margin-bottom:1rem">Bookings by Transport Type</h3>
      <?php foreach ($typeStats as $ts):
        $typeName = (string)($ts['type_name'] ?? 'Unknown');
        $tc = strtolower(str_replace([' ','-'],'',$typeName));
        $tc = $tc==='threewheel'?'wheel':$tc;
        $maxBk = max(1, (int)num_or_zero($typeStats[0]['bk_count'] ?? 0));
        $bkCount = (int)num_or_zero($ts['bk_count'] ?? 0);
        $pct = $bkCount > 0 ? round(($bkCount / $maxBk) * 100) : 0;
      ?>
      <div style="margin-bottom:.85rem">
        <div style="display:flex;justify-content:space-between;margin-bottom:.3rem;font-size:.83rem">
          <span><span class="type-pill tp-<?= $tc ?>"><?= e($ts['type_name']) ?></span></span>
          <span style="color:var(--chalk2)"><?= number_format($bkCount) ?> bookings · Rs.<?= number_format((int)num_or_zero($ts['revenue'] ?? 0),0) ?></span>
        </div>
        <div style="background:var(--ink3);border-radius:4px;height:8px;overflow:hidden">
          <div style="width:<?= $pct ?>%;background:var(--fire);height:100%;border-radius:4px;transition:width .6s var(--ease)"></div>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Gateway breakdown -->
      <div style="margin-top:1.5rem">
        <h3 style="font-size:.95rem;margin-bottom:.85rem">Revenue by Payment Method</h3>
        <?php foreach ($byGateway as $gw): ?>
        <div style="display:flex;justify-content:space-between;padding:.42rem 0;border-bottom:1px solid var(--rim);font-size:.83rem">
          <?php $gateway = (string)($gw['gateway'] ?? 'unknown'); ?>
          <span style="color:var(--chalk3);display:flex;align-items:center;gap:.4rem"><i class="fas fa-<?= $gateway==='card'?'credit-card':($gateway==='paypal'?'paypal':'money-bill') ?>"></i> <?= ucfirst($gateway) ?></span>
          <span style="color:var(--chalk)">Rs.<?= number_format((int)num_or_zero($gw['total'] ?? 0),0) ?> <span style="color:var(--chalk4)">(<?= (int)num_or_zero($gw['cnt'] ?? 0) ?>)</span></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Recent bookings table -->
  <div class="card" style="margin-bottom:1.5rem">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--rim);display:flex;justify-content:space-between;align-items:center">
      <h3 style="font-size:1rem;margin:0">Recent Bookings</h3>
      <a href="<?= SITE_URL ?>/admin/bookings.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr>
          <th>Ref</th><th>Passenger</th><th>Route</th><th>Type</th><th>Fare</th><th>Status</th><th>Booked</th>
        </tr></thead>
        <tbody>
          <?php foreach ($recentBk as $b): ?>
          <tr>
            <td><span class="mono" style="color:var(--fire)"><?= e($b['booking_ref']) ?></span></td>
            <td><?= e($b['uname'] ?? '—') ?></td>
            <td style="font-weight:600"><?= e($b['origin'] ?? '—') ?> → <?= e($b['destination'] ?? '—') ?></td>
            <td><?= status_badge((string)($b['type_name'] ?? 'unknown')) ?></td>
            <td style="color:var(--ok);font-weight:700">Rs.<?= number_format((int)num_or_zero($b['total_fare'] ?? 0),0) ?></td>
            <td><?= status_badge((string)($b['booking_status'] ?? 'unknown')) ?></td>
            <td style="font-size:.8rem;color:var(--chalk3)"><?= !empty($b['created_at']) ? date('M j, H:i', strtotime($b['created_at'])) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Quick actions -->
  <div class="grid-4">
    <?php $actions=[
      ['href'=>'/admin/bookings.php','ico'=>'ticket-alt','col'=>'var(--fire)','lbl'=>'Manage Bookings','cnt'=>$s['pending_bk']],
      ['href'=>'/admin/users.php',   'ico'=>'users',     'col'=>'var(--info)','lbl'=>'Manage Users',   'cnt'=>$s['active_users']],
      ['href'=>'/admin/schedules.php','ico'=>'calendar', 'col'=>'var(--ok)',  'lbl'=>'Schedules',      'cnt'=>$s['schedules_today'].' today'],
      ['href'=>'/admin/reports.php', 'ico'=>'chart-bar', 'col'=>'var(--warn)','lbl'=>'Reports',        'cnt'=>'Revenue'],
    ];
    foreach ($actions as $a): ?>
    <a href="<?= SITE_URL.$a['href'] ?>" class="card card-pad" style="display:flex;align-items:center;gap:.75rem;text-decoration:none;transition:border-color .18s" onmouseover="this.style.borderColor='var(--rim2)'" onmouseout="this.style.borderColor='var(--rim)'">
      <div style="width:42px;height:42px;border-radius:var(--r3);background:var(--veil2);display:flex;align-items:center;justify-content:center;color:<?= $a['col'] ?>;font-size:1rem;flex-shrink:0"><i class="fas fa-<?= $a['ico'] ?>"></i></div>
      <div>
        <div style="font-size:.88rem;font-weight:700;color:var(--chalk)"><?= $a['lbl'] ?></div>
        <div style="font-size:.75rem;color:var(--chalk4)"><?= $a['cnt'] ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  </div><!-- /admin-main -->
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
// Revenue chart
(function(){
  var raw = <?= json_encode($revenue14) ?>;
  // Fill missing days
  var labels=[], vals=[];
  for(var i=13;i>=0;i--){
    var d=new Date(); d.setDate(d.getDate()-i);
    var ds=d.toISOString().slice(0,10);
    labels.push(d.toLocaleDateString('en-US',{month:'short',day:'numeric'}));
    var found=raw.find(function(r){return r.d===ds;});
    vals.push(found?parseFloat(found.total):0);
  }
  var ctx=document.getElementById('revenueChart').getContext('2d');
  var grad=ctx.createLinearGradient(0,0,0,180);
  grad.addColorStop(0,'rgba(255,106,0,.25)');
  grad.addColorStop(1,'rgba(255,106,0,.02)');
  new Chart(ctx,{
    type:'line',
    data:{labels:labels,datasets:[{
      label:'Revenue (Rs.)',data:vals,
      borderColor:'#ff6a00',backgroundColor:grad,
      borderWidth:2,pointBackgroundColor:'#ff6a00',pointRadius:3,tension:.35,fill:true
    }]},
    options:{responsive:true,plugins:{legend:{display:false}},
      scales:{x:{grid:{color:'rgba(255,255,255,.05)'},ticks:{color:'#7a7670',font:{size:10}}},
              y:{grid:{color:'rgba(255,255,255,.05)'},ticks:{color:'#7a7670',font:{size:10},callback:function(v){return 'Rs.'+v.toLocaleString();}}}}}
  });
})();
// Auto-refresh every 60s
setInterval(function(){ location.reload(); }, 60000);
</script>
</body>
</html>
