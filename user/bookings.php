<?php
define('RF_ROOT', dirname(__DIR__));
$pageTitle = 'My Bookings';
require_once RF_ROOT.'/includes/header.php';
require_login();

$uid    = current_uid();
$status = trim($_GET['status'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

// Cancel booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    csrf_guard();
    $bkId = (int)($_POST['booking_id'] ?? 0);
    $bk   = db_row("SELECT * FROM bookings WHERE id=? AND user_id=?", [$bkId, $uid]);
    if ($bk && in_array($bk['booking_status'], ['pending','confirmed'])) {
        db_exec("UPDATE bookings SET booking_status='cancelled' WHERE id=?", [$bkId]);
        db_exec("UPDATE schedules SET available_seats=available_seats+? WHERE id=?", [$bk['seats_booked'],$bk['schedule_id']]);
        flash('ok','Booking cancelled. Refunds (if applicable) are processed within 3-5 business days.');
    }
    redirect(SITE_URL.'/user/bookings.php');
}

// Submit feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback'])) {
    csrf_guard();
    $bkId    = (int)($_POST['booking_id'] ?? 0);
    $rating  = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $comment = trim($_POST['comment'] ?? '');
    $bk = db_row("SELECT id FROM bookings WHERE id=? AND user_id=? AND booking_status='confirmed'", [$bkId,$uid]);
    if ($bk) {
        try { db_insert("INSERT INTO feedback(user_id,booking_id,rating,comment) VALUES(?,?,?,?)", [$uid,$bkId,$rating,$comment]); }
        catch (Exception $e) {}
        flash('ok','Thank you for your feedback!');
    }
    redirect(SITE_URL.'/user/bookings.php');
}

$where  = ['b.user_id=?']; $params = [$uid];
if ($status) { $where[] = 'b.booking_status=?'; $params[] = $status; }
$result = paginate(
    "SELECT b.*, r.origin, r.destination, t.type_name, s.departure_date, s.departure_time, s.arrival_time,
            (SELECT COUNT(*) FROM feedback f WHERE f.booking_id=b.id) has_feedback
     FROM bookings b JOIN schedules s ON s.id=b.schedule_id
     JOIN routes r ON r.id=s.route_id JOIN transport_types t ON t.id=r.type_id
    WHERE ".implode(' AND ',$where)." ORDER BY b.created_at DESC",
    $params, $page
);
?>
<div class="main-content">
<div class="container">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem">
    <h1>My Bookings</h1>
    <a href="<?= SITE_URL ?>/search.php" class="btn btn-fire btn-sm"><i class="fas fa-search"></i> Book a Trip</a>
  </div>

  <!-- Status filter tabs -->
  <div style="display:flex;gap:.4rem;margin-bottom:1.25rem;flex-wrap:wrap">
    <?php foreach ([''=> 'All', 'pending'=>'Pending','confirmed'=>'Confirmed','cancelled'=>'Cancelled','completed'=>'Completed'] as $k=>$lbl): ?>
    <a href="?status=<?= $k ?>" class="btn btn-<?= $status===$k?'fire':'dark' ?> btn-sm"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$result['items']): ?>
  <div class="card card-pad" style="text-align:center;padding:3rem">
    <div style="font-size:3rem;margin-bottom:.75rem">🎟️</div>
    <h3>No bookings found</h3>
    <p style="margin-bottom:1.25rem;margin-top:.3rem">You haven't made any bookings yet.</p>
    <a href="<?= SITE_URL ?>/search.php" class="btn btn-fire">Search Trips</a>
  </div>
  <?php else: ?>
  <?php foreach ($result['items'] as $b):
    $tc = strtolower(str_replace([' ','-'],'',$b['type_name']));
    $tc = $tc==='threewheel'?'wheel':$tc;
    $canCancel  = in_array($b['booking_status'],['pending','confirmed']) && strtotime($b['departure_date'].' '.$b['departure_time']) > time();
    $canFeedback= $b['booking_status']==='confirmed' && !$b['has_feedback'];
  ?>
  <div class="card card-pad" style="margin-bottom:1rem">
    <div style="display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:start">
      <div>
        <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:.6rem;flex-wrap:wrap">
          <span class="type-pill tp-<?= $tc ?>"><?= e($b['type_name']) ?></span>
          <?= status_badge($b['booking_status']) ?>
          <?= status_badge($b['payment_status']) ?>
          <span class="mono" style="font-size:.75rem;color:var(--chalk4)"><?= e($b['booking_ref']) ?></span>
        </div>
        <div style="font-size:1.05rem;font-weight:700;color:var(--chalk);margin-bottom:.4rem">
          <?= e($b['origin']) ?> <span style="color:var(--fire)">→</span> <?= e($b['destination']) ?>
        </div>
        <div style="display:flex;gap:1.1rem;font-size:.82rem;color:var(--chalk3);flex-wrap:wrap">
          <span><i class="fas fa-calendar"></i> <?= date('D, M j, Y',strtotime($b['departure_date'])) ?></span>
          <span><i class="fas fa-clock"></i> <?= substr($b['departure_time'],0,5) ?><?= $b['arrival_time']?' → '.substr($b['arrival_time'],0,5):'' ?></span>
          <span><i class="fas fa-chair"></i> <?= $b['seats_booked'] ?> seat(s)</span>
          <span><i class="fas fa-coins" style="color:var(--ok)"></i> Rs.<?= number_format($b['total_fare'],2) ?></span>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:.4rem;align-items:flex-end">
        <?php if ($b['booking_status']==='confirmed'): ?>
        <a href="<?= SITE_URL ?>/user/ticket.php?booking=<?= $b['id'] ?>" class="btn btn-fire btn-sm">
          <i class="fas fa-ticket-alt"></i> E-Ticket
        </a>
        <?php elseif ($b['payment_status']==='unpaid'): ?>
        <a href="<?= SITE_URL ?>/payment/checkout.php?booking=<?= $b['id'] ?>" class="btn btn-ok btn-sm">
          <i class="fas fa-credit-card"></i> Pay Now
        </a>
        <?php endif; ?>
        <?php if ($canCancel): ?>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
          <button type="submit" name="cancel" class="btn btn-ghost btn-sm" onclick="return confirm('Cancel this booking?')">
            <i class="fas fa-times"></i> Cancel
          </button>
        </form>
        <?php endif; ?>
        <?php if ($canFeedback): ?>
        <button onclick="openFeedback(<?= $b['id'] ?>)" class="btn btn-dark btn-sm">
          <i class="fas fa-star"></i> Review
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Pagination -->
  <?php if ($result['pages'] > 1): ?>
  <div class="pagination">
    <?php for ($p=1;$p<=$result['pages'];$p++): ?>
    <a href="?page=<?= $p ?>&status=<?= urlencode($status) ?>" class="page-btn <?= $p===$page?'on':'' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
</div>

<!-- Feedback modal -->
<div id="feedbackModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1100;align-items:center;justify-content:center">
  <div style="background:var(--ink2);border:1px solid var(--rim2);border-radius:var(--r4);padding:1.75rem;width:90%;max-width:440px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.1rem">
      <h3 style="margin:0">Rate Your Trip</h3>
      <button onclick="document.getElementById('feedbackModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--chalk3);font-size:1.1rem"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="booking_id" id="fbBkId">
      <input type="hidden" name="rating"     id="fbRating" value="5">
      <div style="text-align:center;margin-bottom:1.1rem">
        <div style="display:flex;justify-content:center;gap:.35rem" id="starRow">
          <?php for ($i=1;$i<=5;$i++): ?>
          <button type="button" onclick="setRating(<?= $i ?>)" class="star-btn" data-v="<?= $i ?>"
            style="font-size:1.8rem;background:none;border:none;cursor:pointer;color:var(--warn);padding:.1rem;transition:transform .1s">★</button>
          <?php endfor; ?>
        </div>
      </div>
      <div class="fld">
        <label>Your Comment (optional)</label>
        <textarea name="comment" class="fc" rows="3" placeholder="Tell us about your experience…"></textarea>
      </div>
      <button type="submit" name="feedback" class="btn btn-fire btn-full"><i class="fas fa-paper-plane"></i> Submit Review</button>
    </form>
  </div>
</div>

<script>
function openFeedback(id){
  document.getElementById('fbBkId').value=id;
  document.getElementById('feedbackModal').style.display='flex';
  setRating(5);
}
function setRating(n){
  document.getElementById('fbRating').value=n;
  document.querySelectorAll('.star-btn').forEach(function(btn){
    btn.style.opacity=parseInt(btn.dataset.v)<=n?'1':'.3';
    btn.style.transform=parseInt(btn.dataset.v)<=n?'scale(1.15)':'scale(1)';
  });
}
document.getElementById('feedbackModal').addEventListener('click',function(e){if(e.target===this)this.style.display='none';});
</script>
<?php require_once RF_ROOT.'/includes/footer.php'; ?>
