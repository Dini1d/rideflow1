<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_admin();

$period = $_GET['period'] ?? 'month'; // month | year | custom
$from   = $_GET['from'] ?? date('Y-m-01');
$to     = $_GET['to']   ?? date('Y-m-d');
if ($period === 'month') { $from = date('Y-m-01'); $to = date('Y-m-d'); }
if ($period === 'year')  { $from = date('Y-01-01'); $to = date('Y-m-d'); }

// Revenue by day
$daily = db_all("SELECT DATE(paid_at) d, SUM(amount) total, COUNT(*) cnt
    FROM payments WHERE status='completed' AND DATE(paid_at) BETWEEN ? AND ?
    GROUP BY DATE(paid_at) ORDER BY d", [$from, $to]);

// Summary stats
$summary = db_row("SELECT
    SUM(p.amount) revenue, COUNT(DISTINCT b.id) bookings, COUNT(DISTINCT b.user_id) passengers,
    AVG(b.total_fare) avg_fare
    FROM payments p JOIN bookings b ON b.id=p.booking_id
    WHERE p.status='completed' AND DATE(p.paid_at) BETWEEN ? AND ?", [$from,$to]);

// Revenue by type
$byType = db_all("SELECT t.type_name, COUNT(b.id) cnt, SUM(b.total_fare) rev
    FROM bookings b JOIN schedules s ON s.id=b.schedule_id JOIN routes r ON r.id=s.route_id JOIN transport_types t ON t.id=r.type_id
    JOIN payments p ON p.booking_id=b.id
    WHERE p.status='completed' AND DATE(p.paid_at) BETWEEN ? AND ?
    GROUP BY t.id ORDER BY rev DESC", [$from,$to]);

// Top routes
$topRoutes = db_all("SELECT r.origin, r.destination, COUNT(b.id) cnt, SUM(b.total_fare) rev
    FROM bookings b JOIN schedules s ON s.id=b.schedule_id JOIN routes r ON r.id=s.route_id
    JOIN payments p ON p.booking_id=b.id
    WHERE p.status='completed' AND DATE(p.paid_at) BETWEEN ? AND ?
    GROUP BY r.id ORDER BY cnt DESC LIMIT 8", [$from,$to]);

// Recent payments
$payments = db_all("SELECT p.*, b.booking_ref, u.name uname, r.origin, r.destination
    FROM payments p JOIN bookings b ON b.id=p.booking_id JOIN users u ON u.id=p.user_id
    JOIN schedules s ON s.id=b.schedule_id JOIN routes r ON r.id=s.route_id
    WHERE p.status='completed' AND DATE(p.paid_at) BETWEEN ? AND ?
    ORDER BY p.paid_at DESC LIMIT 20", [$from,$to]);

$pageTitle = 'Revenue Reports';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> — <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700&family=DM+Sans:wght@400;500;600;700&family=DM+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=20260923-professional">
</head><body>
<div class="admin-layout">
<?php require_once RF_ROOT.'/admin/admin_sidebar.php'; ?>
<div style="overflow-y:auto">
<?php require_once RF_ROOT.'/admin/admin_topbar.php'; ?>
<div class="admin-main">

<div class="page-hd">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
    <div><h1>Revenue Reports</h1><p><?= date('M j',strtotime($from)) ?> – <?= date('M j, Y',strtotime($to)) ?></p></div>
    <button onclick="window.print()" class="btn btn-ghost btn-sm"><i class="fas fa-print"></i> Print</button>
  </div>
</div>

<!-- Period filter -->
<div class="card card-pad" style="margin-bottom:1.5rem">
  <form method="GET" style="display:flex;gap:.65rem;flex-wrap:wrap;align-items:end">
    <div style="display:flex;gap:.4rem">
      <?php foreach (['month'=>'This Month','year'=>'This Year','custom'=>'Custom'] as $k=>$lbl): ?>
      <a href="?period=<?= $k ?>" class="btn btn-<?= $period===$k?'fire':'dark' ?> btn-sm"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
    <?php if ($period==='custom'): ?>
    <div class="fw"><i class="fas fa-calendar fc-icon"></i><input type="date" name="from" class="fc has-icon" value="<?= e($from) ?>"></div>
    <div class="fw"><i class="fas fa-calendar fc-icon"></i><input type="date" name="to"   class="fc has-icon" value="<?= e($to) ?>"></div>
    <input type="hidden" name="period" value="custom">
    <button type="submit" class="btn btn-fire btn-sm"><i class="fas fa-filter"></i> Apply</button>
    <?php endif; ?>
  </form>
</div>

<!-- Summary KPIs -->
<div class="grid-4" style="margin-bottom:1.5rem">
  <?php $kpis=[
    ['ico'=>'coins',      'col'=>'var(--ok)',  'bg'=>'rgba(34,197,94,.1)', 'val'=>'Rs.'.number_format((int)num_or_zero($summary['revenue'] ?? 0),0),'lbl'=>'Total Revenue'],
    ['ico'=>'ticket-alt', 'col'=>'var(--fire)','bg'=>'var(--fire-soft)',   'val'=>number_format((int)num_or_zero($summary['bookings'] ?? 0)),'lbl'=>'Bookings'],
    ['ico'=>'users',      'col'=>'var(--info)','bg'=>'rgba(56,189,248,.1)','val'=>number_format((int)num_or_zero($summary['passengers'] ?? 0)),'lbl'=>'Passengers'],
    ['ico'=>'chart-line', 'col'=>'var(--warn)','bg'=>'rgba(245,158,11,.1)','val'=>'Rs.'.number_format((int)num_or_zero($summary['avg_fare'] ?? 0),0),'lbl'=>'Avg Fare'],
  ];
  foreach ($kpis as $k): ?>
  <div class="stat-card"><div class="stat-ico" style="background:<?= $k['bg'] ?>;color:<?= $k['col'] ?>"><i class="fas fa-<?= $k['ico'] ?>"></i></div>
    <div><div class="stat-num"><?= $k['val'] ?></div><div class="stat-lbl"><?= $k['lbl'] ?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts row -->
<div class="grid-2" style="gap:1.5rem;margin-bottom:1.5rem">
  <!-- Revenue chart -->
  <div class="card card-pad">
    <h3 style="font-size:1rem;margin-bottom:1rem">Daily Revenue</h3>
    <canvas id="revenueChart" height="200"></canvas>
  </div>
  <!-- Type breakdown -->
  <div class="card card-pad">
    <h3 style="font-size:1rem;margin-bottom:1rem">Revenue by Transport Type</h3>
    <?php $maxRev = $byType[0]['rev'] ?? 1; foreach ($byType as $bt):
      $tc = strtolower(str_replace([' ','-'],'',$bt['type_name']));
      $tc = $tc==='threewheel'?'wheel':$tc;
      $pct = round($bt['rev']/$maxRev*100);
    ?>
    <div style="margin-bottom:.85rem">
      <div style="display:flex;justify-content:space-between;margin-bottom:.3rem;font-size:.84rem">
        <span class="type-pill tp-<?= $tc ?>"><?= e($bt['type_name']) ?></span>
        <span style="color:var(--chalk2)">Rs.<?= number_format((int)num_or_zero($bt['rev']),0) ?> (<?= $bt['cnt'] ?> trips)</span>
      </div>
      <div style="background:var(--ink3);border-radius:4px;height:8px;overflow:hidden">
        <div style="width:<?= $pct ?>%;background:var(--fire);height:100%;border-radius:4px"></div>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Top routes -->
    <h3 style="font-size:.95rem;margin-top:1.5rem;margin-bottom:.75rem">Top Routes</h3>
    <?php foreach ($topRoutes as $rt): ?>
    <div style="display:flex;justify-content:space-between;padding:.42rem 0;border-bottom:1px solid var(--rim);font-size:.83rem">
      <span style="color:var(--chalk3)"><?= e($rt['origin']) ?> → <?= e($rt['destination']) ?></span>
      <span style="color:var(--chalk)"><?= $rt['cnt'] ?> trips · Rs.<?= number_format((int)num_or_zero($rt['rev']),0) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Payments table -->
<div class="card" style="margin-bottom:1.5rem">
  <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--rim)"><h3 style="font-size:1rem;margin:0">Recent Payments</h3></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Booking</th><th>Passenger</th><th>Route</th><th>Gateway</th><th>Amount</th><th>Date</th></tr></thead>
      <tbody>
        <?php if (!$payments): ?>
        <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--chalk4)">No payments in this period</td></tr>
        <?php endif; ?>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td><span class="mono" style="color:var(--fire);font-size:.8rem"><?= e($p['booking_ref']) ?></span></td>
          <td style="font-size:.86rem"><?= e($p['uname']) ?></td>
          <td style="font-size:.84rem;color:var(--chalk3)"><?= e($p['origin']) ?> → <?= e($p['destination']) ?></td>
          <td><?= status_badge($p['gateway']) ?></td>
          <td style="color:var(--ok);font-weight:700">Rs.<?= number_format((int)num_or_zero($p['amount']),0) ?></td>
          <td style="font-size:.8rem;color:var(--chalk3)"><?= date('M j, H:i',strtotime($p['paid_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
(function(){
  var raw=<?= json_encode($daily) ?>;
  var labels=raw.map(function(r){var d=new Date(r.d);return d.toLocaleDateString('en-US',{month:'short',day:'numeric'});});
  var vals=raw.map(function(r){return parseFloat(r.total)||0;});
  var ctx=document.getElementById('revenueChart').getContext('2d');
  var grad=ctx.createLinearGradient(0,0,0,200);
  grad.addColorStop(0,'rgba(255,106,0,.3)');grad.addColorStop(1,'rgba(255,106,0,.02)');
  new Chart(ctx,{type:'bar',data:{labels:labels,datasets:[{label:'Revenue (Rs.)',data:vals,backgroundColor:'rgba(255,106,0,.7)',borderColor:'#ff6a00',borderWidth:1.5,borderRadius:4}]},
    options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{color:'rgba(255,255,255,.04)'},ticks:{color:'#7a7670',font:{size:10}}},y:{grid:{color:'rgba(255,255,255,.04)'},ticks:{color:'#7a7670',font:{size:10},callback:function(v){return 'Rs.'+v.toLocaleString();}}}}}});
})();
</script>
</body></html>
