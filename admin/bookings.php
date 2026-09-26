<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_admin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $bkId = (int)($_POST['booking_id'] ?? 0);
    $act  = trim($_POST['action'] ?? '');
    if ($bkId && $act) {
        if ($act === 'confirm') {
            db_exec("UPDATE bookings SET booking_status='confirmed' WHERE id=?", [$bkId]);
            $bk = db_row("SELECT user_id, booking_ref FROM bookings WHERE id=?", [$bkId]);
            if ($bk) notify($bk['user_id'], 'Booking Confirmed', 'Your booking '.$bk['booking_ref'].' has been confirmed!', 'booking');
            flash('ok', 'Booking confirmed.');
        } elseif ($act === 'cancel') {
            $bk = db_row("SELECT * FROM bookings WHERE id=?", [$bkId]);
            if ($bk) {
                db_exec("UPDATE bookings SET booking_status='cancelled' WHERE id=?", [$bkId]);
                db_exec("UPDATE schedules SET available_seats=available_seats+? WHERE id=?", [$bk['seats_booked'],$bk['schedule_id']]);
                notify($bk['user_id'], 'Booking Cancelled', 'Booking '.$bk['booking_ref'].' was cancelled.', 'booking');
            }
            flash('ok', 'Booking cancelled.');
        } elseif ($act === 'mark_paid') {
            db_exec("UPDATE bookings SET payment_status='paid', booking_status='confirmed' WHERE id=?", [$bkId]);
            flash('ok', 'Marked as paid.');
        }
    }
    redirect(SITE_URL.'/admin/bookings.php');
}

// Filters
$status  = trim($_GET['status']  ?? '');
$payment = trim($_GET['payment'] ?? '');
$search  = trim($_GET['q']       ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];
if ($status)  { $where[] = 'b.booking_status=?';  $params[] = $status;  }
if ($payment) { $where[] = 'b.payment_status=?';  $params[] = $payment; }
if ($search)  { $where[] = '(b.booking_ref LIKE ? OR u.name LIKE ? OR u.email LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }

$result = paginate(
    "SELECT b.*, u.name uname, u.email uemail, r.origin, r.destination, t.type_name, s.departure_date, s.departure_time
     FROM bookings b JOIN users u ON u.id=b.user_id
     JOIN schedules s ON s.id=b.schedule_id JOIN routes r ON r.id=s.route_id
     JOIN transport_types t ON t.id=r.type_id
    WHERE ".implode(' AND ',$where)." ORDER BY b.created_at DESC",
    $params, $page
);

$pageTitle = 'Manage Bookings';
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
    <div><h1>Bookings</h1><p><?= number_format((int)num_or_zero($result['total'])) ?> total bookings</p></div>
    <a href="<?= SITE_URL ?>/admin/reports.php" class="btn btn-ghost btn-sm"><i class="fas fa-download"></i> Export</a>
  </div>
</div>

<?= flash_html() ?>

<!-- Filters -->
<div class="card card-pad" style="margin-bottom:1.25rem">
  <form method="GET" style="display:flex;gap:.65rem;flex-wrap:wrap;align-items:end">
    <div style="flex:1;min-width:200px">
      <div class="fw"><i class="fas fa-search fc-icon"></i>
        <input type="text" name="q" class="fc has-icon" placeholder="Search ref, name, email…" value="<?= e($search) ?>">
      </div>
    </div>
    <select name="status" class="fc" style="width:auto">
      <option value="">All Status</option>
      <?php foreach (['pending','confirmed','cancelled','completed','no_show'] as $st): ?>
      <option value="<?= $st ?>" <?= $status===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="payment" class="fc" style="width:auto">
      <option value="">All Payments</option>
      <?php foreach (['unpaid','paid','refunded','failed'] as $pm): ?>
      <option value="<?= $pm ?>" <?= $payment===$pm?'selected':'' ?>><?= ucfirst($pm) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-fire btn-sm"><i class="fas fa-filter"></i> Filter</button>
    <a href="<?= SITE_URL ?>/admin/bookings.php" class="btn btn-ghost btn-sm">Reset</a>
  </form>
</div>

<!-- Table -->
<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr>
        <th>Ref</th><th>Passenger</th><th>Route</th><th>Date</th><th>Seats</th><th>Fare</th><th>Booking</th><th>Payment</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php if (!$result['items']): ?>
        <tr><td colspan="9" style="text-align:center;padding:2.5rem;color:var(--chalk4)">No bookings found</td></tr>
        <?php endif; ?>
        <?php foreach ($result['items'] as $b): ?>
        <tr>
          <td><span class="mono" style="color:var(--fire);font-size:.8rem"><?= e($b['booking_ref']) ?></span></td>
          <td>
            <div style="font-weight:600;font-size:.88rem"><?= e($b['uname']) ?></div>
            <div style="font-size:.75rem;color:var(--chalk4)"><?= e($b['uemail']) ?></div>
          </td>
          <td style="font-size:.86rem">
            <div style="font-weight:600"><?= e($b['origin']) ?> → <?= e($b['destination']) ?></div>
            <div style="font-size:.75rem;color:var(--chalk4)"><?= e($b['type_name']) ?></div>
          </td>
          <td style="font-size:.8rem;white-space:nowrap">
            <?= date('M j, Y', strtotime($b['departure_date'])) ?><br>
            <span style="color:var(--chalk3)"><?= substr($b['departure_time'],0,5) ?></span>
          </td>
          <td style="text-align:center;font-weight:700"><?= $b['seats_booked'] ?></td>
          <td style="color:var(--ok);font-weight:700">Rs.<?= number_format((int)num_or_zero($b['total_fare']),0) ?></td>
          <td><?= status_badge($b['booking_status']) ?></td>
          <td><?= status_badge($b['payment_status']) ?></td>
          <td>
            <form method="POST" style="display:flex;gap:.3rem;flex-wrap:wrap">
              <?= csrf_field() ?>
              <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
              <?php if ($b['booking_status']==='pending'): ?>
              <button name="action" value="confirm" class="btn btn-ok btn-sm" title="Confirm" onclick="return confirm('Confirm this booking?')">
                <i class="fas fa-check"></i>
              </button>
              <?php endif; ?>
              <?php if ($b['payment_status']==='unpaid' && $b['booking_status']!=='cancelled'): ?>
              <button name="action" value="mark_paid" class="btn btn-info btn-sm" title="Mark Paid" onclick="return confirm('Mark as paid?')">
                <i class="fas fa-dollar-sign"></i>
              </button>
              <?php endif; ?>
              <?php if (!in_array($b['booking_status'],['cancelled','completed'])): ?>
              <button name="action" value="cancel" class="btn btn-bad btn-sm" title="Cancel" onclick="return confirm('Cancel this booking?')">
                <i class="fas fa-times"></i>
              </button>
              <?php endif; ?>
              <a href="<?= SITE_URL ?>/user/ticket.php?booking=<?= $b['id'] ?>" target="_blank" class="btn btn-dark btn-sm" title="View Ticket">
                <i class="fas fa-ticket-alt"></i>
              </a>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($result['pages'] > 1): ?>
<div class="pagination">
  <?php for ($p=1; $p<=$result['pages']; $p++): ?>
  <a href="?page=<?= $p ?>&status=<?= urlencode($status) ?>&payment=<?= urlencode($payment) ?>&q=<?= urlencode($search) ?>"
     class="page-btn <?= $p===$page?'on':'' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

</div></div></div>
</body></html>
