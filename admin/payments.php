<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD']==='POST'){csrf_guard();$act=trim($_POST['action']??'');
  if($act==='refund'){$pid=(int)($_POST['id']??0);db_exec("UPDATE payments SET status='refunded' WHERE id=?",[$pid]);db_exec("UPDATE bookings SET payment_status='refunded',booking_status='cancelled' WHERE id=(SELECT booking_id FROM payments WHERE id=?)",[$pid]);flash('ok','Payment refunded.');}
  redirect(SITE_URL.'/admin/payments.php');}

$gw   = trim($_GET['gw']??'');
$stat = trim($_GET['status']??'');
$page = max(1,(int)($_GET['page']??1));
$where=['1=1'];$params=[];
if($gw){$where[]='p.gateway=?';$params[]=$gw;}
if($stat){$where[]='p.status=?';$params[]=$stat;}

$result = paginate("SELECT p.*,b.booking_ref,u.name uname,r.origin,r.destination FROM payments p JOIN bookings b ON b.id=p.booking_id JOIN users u ON u.id=p.user_id JOIN schedules s ON s.id=b.schedule_id JOIN routes r ON r.id=s.route_id WHERE ".implode(' AND ',$where)." ORDER BY p.payment_date DESC",$params,$page);
$totals = db_all("SELECT gateway,SUM(amount) total,COUNT(*) cnt FROM payments WHERE status='completed' GROUP BY gateway ORDER BY total DESC");
$pageTitle='Payments';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> — <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=20260923-professional">
</head><body>
<div class="admin-layout">
<?php require_once RF_ROOT.'/admin/admin_sidebar.php'; ?>
<div style="overflow-y:auto">
<?php require_once RF_ROOT.'/admin/admin_topbar.php'; ?>
<div class="admin-main">
<div class="page-hd"><h1>Payments</h1><p><?= number_format((int)num_or_zero($result['total'])) ?> total transactions</p></div>
<?= flash_html() ?>

<!-- Gateway breakdown -->
<div style="display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap">
  <?php foreach($totals as $t):?>
  <div class="card card-pad" style="flex:1;min-width:150px">
    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--chalk4);margin-bottom:.25rem"><?= e($t['gateway']) ?></div>
    <div style="font-size:1.2rem;font-weight:900;color:var(--ok)">Rs.<?= number_format((int)num_or_zero($t['total']),0) ?></div>
    <div style="font-size:.76rem;color:var(--chalk4)"><?= $t['cnt'] ?> transactions</div>
  </div>
  <?php endforeach;?>
</div>

<!-- Filters -->
<div class="card card-pad" style="margin-bottom:1.25rem">
  <form method="GET" style="display:flex;gap:.65rem;flex-wrap:wrap;align-items:end">
    <select name="gw" class="fc" style="width:auto">
      <option value="">All Gateways</option>
      <?php foreach(['stripe','paypal','ezCash','genie','bank_transfer','cash'] as $g):?>
      <option value="<?= $g ?>" <?= $gw===$g?'selected':'' ?>><?= ucfirst($g) ?></option>
      <?php endforeach;?>
    </select>
    <select name="status" class="fc" style="width:auto">
      <option value="">All Status</option>
      <?php foreach(['pending','completed','failed','refunded'] as $s):?>
      <option value="<?= $s ?>" <?= $stat===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach;?>
    </select>
    <button type="submit" class="btn btn-fire btn-sm"><i class="fas fa-filter"></i> Filter</button>
    <a href="?" class="btn btn-ghost btn-sm">Reset</a>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Booking</th><th>Customer</th><th>Route</th><th>Gateway</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if(!$result['items']):?><tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--chalk4)">No payments found</td></tr><?php endif;?>
        <?php foreach($result['items'] as $p):?>
        <tr>
          <td><span class="mono" style="color:var(--fire);font-size:.8rem"><?= e($p['booking_ref']) ?></span></td>
          <td style="font-size:.86rem;font-weight:600"><?= e($p['uname']) ?></td>
          <td style="font-size:.83rem;color:var(--chalk3)"><?= e($p['origin']) ?> → <?= e($p['destination']) ?></td>
          <td><?= status_badge($p['gateway']) ?></td>
          <td style="color:var(--ok);font-weight:700">Rs.<?= number_format((int)num_or_zero($p['amount']),0) ?></td>
          <td><?= status_badge($p['status']) ?></td>
          <td style="font-size:.78rem;color:var(--chalk3)"><?= date('M j, H:i',strtotime($p['payment_date'])) ?></td>
          <td>
            <?php if($p['status']==='completed'):?>
            <form method="POST">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button name="action" value="refund" class="btn btn-bad btn-sm" onclick="return confirm('Issue refund for this payment?')"><i class="fas fa-undo"></i> Refund</button>
            </form>
            <?php else:?><span style="font-size:.75rem;color:var(--chalk4)">—</span><?php endif;?>
          </td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>
<?php if($result['pages']>1):?><div class="pagination"><?php for($p=1;$p<=$result['pages'];$p++):?><a href="?page=<?= $p ?>&gw=<?= urlencode($gw) ?>&status=<?= urlencode($stat) ?>" class="page-btn <?= $p===$page?'on':'' ?>"><?= $p ?></a><?php endfor;?></div><?php endif;?>
</div></div></div>
</body></html>
