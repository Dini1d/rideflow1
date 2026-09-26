<?php
define('RF_ROOT', dirname(__DIR__));
$pageTitle = 'Driver Dashboard';
require_once RF_ROOT.'/includes/header.php';
require_once RF_ROOT.'/includes/smart_tips.php';
require_driver();

$uid = current_uid();
$driver = db_row("SELECT u.id AS user_id, u.name, u.email, u.phone, u.status AS user_status,
                         v.id AS vehicle_id, v.vehicle_name, v.vehicle_number, v.capacity,
                         COALESCE(d.rating,0) AS rating, COALESCE(d.experience,0) AS experience,
                         COALESCE(d.status,'inactive') AS driver_status
                  FROM users u
                  LEFT JOIN drivers d ON d.user_id=u.id
                  LEFT JOIN vehicles v ON v.id=d.vehicle_id
                  WHERE u.id=? AND u.role='driver' LIMIT 1", [$uid]);
if (!$driver) { flash('bad','Driver profile not found. Contact admin.'); redirect(SITE_URL.'/index.php'); }

// Handle trip status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $schId  = (int)($_POST['schedule_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    if ($schId && in_array($status, ['departed','arrived'], true)) {
        // Verify this schedule is assigned to driver's vehicle
        if ($driver['vehicle_id']) {
            $ok = db_val("SELECT id FROM schedules WHERE id=? AND vehicle_id=?", [$schId, $driver['vehicle_id']]);
            if ($ok) {
                db_exec("UPDATE schedules SET status=? WHERE id=?", [$status, $schId]);
                flash('ok', 'Trip status updated to '.ucfirst($status).'.');
            }
        }
    }
    redirect(SITE_URL.'/driver/dashboard.php');
}

// Today's trips
$today = date('Y-m-d');
$trips = db_all("
    SELECT s.*, r.origin, r.destination, r.estimated_duration, r.route_name,
           COUNT(b.id) total_bookings, SUM(b.seats_booked) total_passengers
    FROM schedules s
    JOIN routes r ON r.id=s.route_id
    LEFT JOIN bookings b ON b.schedule_id=s.id AND b.booking_status IN ('confirmed','pending')
    WHERE s.vehicle_id=? AND s.departure_date>=CURRENT_DATE - INTERVAL '1 day'
    GROUP BY s.id
    ORDER BY s.departure_date, s.departure_time
    LIMIT 10", [$driver['vehicle_id'] ?? 0]);

// Upcoming passengers for today's next trip
$nextTrip = null;
$passengers = [];
foreach ($trips as $t) {
    if ($t['departure_date'] === $today && in_array($t['status'],['scheduled','departed'])) {
        $nextTrip = $t;
        $passengers = db_all("
            SELECT b.booking_ref, b.passenger_name, b.passenger_phone, b.seats_booked, b.booking_status, b.payment_status
            FROM bookings b WHERE b.schedule_id=? AND b.booking_status IN ('confirmed','pending')
            ORDER BY b.booking_status", [$t['id']]);
        break;
    }
}

$hasDeparted = false;
foreach ($trips as $t) {
    if ($t['status'] === 'departed') { $hasDeparted = true; break; }
}
$driverTips = smart_dashboard_tips('driver', [
  'next'         => $nextTrip,
  'passengers'   => (int)($nextTrip['total_passengers'] ?? count($passengers)),
  'capacity'     => (int)($driver['capacity'] ?? 0),
  'trip_count'   => count($trips),
  'has_departed' => $hasDeparted,
]);
?>
<div class="main-content">
<div class="container">

  <!-- Driver header -->
  <div class="card card-pad" style="margin-bottom:1.5rem;display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap">
    <div style="width:56px;height:56px;border-radius:50%;background:var(--info);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:var(--ink);flex-shrink:0">
      <?= strtoupper(substr($_SESSION['uname']??'D',0,1)) ?>
    </div>
    <div style="flex:1">
      <h1 style="font-size:1.4rem;margin-bottom:.2rem"><?= e(greeting_for_now()) ?>, <?= e(explode(' ', $_SESSION['uname']??'Driver')[0]) ?></h1>
      <div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:.85rem;color:var(--chalk3)">
        <?php if ($driver['vehicle_name']): ?>
        <span><i class="fas fa-bus" style="color:var(--fire)"></i> <?= e($driver['vehicle_name']) ?> (<?= e($driver['vehicle_number']) ?>)</span>
        <?php endif; ?>
        <span><i class="fas fa-star" style="color:var(--warn)"></i> Rating: <?= number_format($driver['rating'],1) ?>/5.0</span>
        <span><i class="fas fa-road" style="color:var(--ok)"></i> <?= $driver['experience'] ?> yrs experience</span>
        <?= status_badge($driver['driver_status']) ?>
      </div>
    </div>
    <?php if ($driver['vehicle_name']): ?>
    <div style="text-align:right">
      <div style="font-size:.7rem;color:var(--chalk4);text-transform:uppercase;letter-spacing:.08em">Assigned Vehicle</div>
      <div style="font-size:1.1rem;font-weight:700;color:var(--chalk)"><?= e($driver['vehicle_name']) ?></div>
      <div style="font-size:.82rem;color:var(--chalk3)"><?= e($driver['vehicle_number']) ?> · <?= $driver['capacity'] ?> seats</div>
    </div>
    <?php endif; ?>
  </div>

  <?= render_smart_tips($driverTips) ?>

  <div class="grid-2" style="gap:1.5rem;align-items:start">

    <!-- Today's trips -->
    <div>
      <h2 style="font-size:1.15rem;margin-bottom:1rem"><i class="fas fa-calendar-day" style="color:var(--fire)"></i> My Trips</h2>
      <?php if (!$trips): ?>
      <div class="card card-pad" style="text-align:center;padding:2.5rem;color:var(--chalk3)">
        <i class="fas fa-calendar-times" style="font-size:2.5rem;color:var(--chalk4);display:block;margin-bottom:.75rem"></i>
        No trips scheduled. Contact admin if this is unexpected.
      </div>
      <?php endif; ?>
      <?php foreach ($trips as $t): ?>
      <div class="card card-pad" style="margin-bottom:.85rem;border-left:3px solid var(--<?= $t['status']==='departed'?'warn':($t['status']==='arrived'?'ok':'fire') ?>)">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;flex-wrap:wrap">
          <div>
            <div style="font-weight:700;color:var(--chalk);margin-bottom:.35rem">
              <?= e($t['origin']) ?> <span style="color:var(--fire)">→</span> <?= e($t['destination']) ?>
            </div>
            <div style="font-size:.82rem;color:var(--chalk3);display:flex;gap:1rem;flex-wrap:wrap">
              <span><i class="fas fa-calendar"></i> <?= date('D, M j',strtotime($t['departure_date'])) ?></span>
              <span><i class="fas fa-clock"></i> <?= substr($t['departure_time'],0,5) ?></span>
              <span><i class="fas fa-hourglass-half"></i> <?= e($t['estimated_duration']) ?></span>
              <span><i class="fas fa-users" style="color:var(--ok)"></i> <?= $t['total_passengers']??0 ?>/<?= $driver['capacity'] ?> passengers</span>
            </div>
          </div>
          <div>
            <?= status_badge($t['status']) ?>
            <?php if ($t['vehicle_id'] == $driver['vehicle_id'] && in_array($t['status'],['scheduled','departed'])): ?>
            <form method="POST" style="margin-top:.5rem">
              <?= csrf_field() ?>
              <input type="hidden" name="schedule_id" value="<?= $t['id'] ?>">
              <?php if ($t['status']==='scheduled'): ?>
              <button name="status" value="departed" class="btn btn-warn btn-sm" onclick="return confirm('Mark as departed?')">
                <i class="fas fa-play"></i> Start Trip
              </button>
            <?php elseif ($t['status']==='departed'): ?>
                <button name="status" value="arrived" class="btn btn-ok btn-sm" onclick="return confirm('Mark as completed?')">
                <i class="fas fa-flag-checkered"></i> Complete
              </button>
              <?php endif; ?>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Next trip passengers -->
    <div>
      <?php if ($nextTrip): ?>
      <h2 id="passengers" style="font-size:1.15rem;margin-bottom:1rem"><i class="fas fa-users" style="color:var(--ok)"></i> Next Trip Passengers</h2>
      <div class="card card-pad" style="margin-bottom:1rem;background:var(--fire-soft);border-color:rgba(255,106,0,.2)">
        <div style="font-weight:700;color:var(--chalk)"><?= e($nextTrip['origin']) ?> → <?= e($nextTrip['destination']) ?></div>
        <div style="font-size:.83rem;color:var(--chalk3);margin-top:.25rem"><?= date('D, M j · ',strtotime($nextTrip['departure_date'])) ?><?= substr($nextTrip['departure_time'],0,5) ?></div>
        <div style="margin-top:.5rem;font-size:.82rem;color:var(--chalk3)">
          <i class="fas fa-ticket-alt" style="color:var(--fire)"></i>
          <?= count($passengers) ?> booking(s) · <?= $nextTrip['total_passengers']??0 ?> total passengers
        </div>
      </div>
      <?php if ($passengers): ?>
      <div class="card">
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Ref</th><th>Passenger</th><th>Seats</th><th>Booking</th><th>Payment</th></tr></thead>
            <tbody>
              <?php foreach ($passengers as $p): ?>
              <tr>
                <td><span style="font-family:var(--ff-mono);font-size:.78rem;color:var(--fire)"><?= e($p['booking_ref']) ?></span></td>
                <td>
                  <div style="font-weight:600;font-size:.87rem"><?= e($p['passenger_name']) ?></div>
                  <div style="font-size:.75rem;color:var(--chalk4)"><?= e($p['passenger_phone']) ?></div>
                </td>
                <td style="text-align:center;font-weight:700"><?= $p['seats_booked'] ?></td>
                <td><?= status_badge($p['booking_status']) ?></td>
                <td><?= status_badge($p['payment_status']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php else: ?>
      <div class="card card-pad" style="text-align:center;color:var(--chalk3)">No confirmed passengers yet for this trip.</div>
      <?php endif; ?>
      <?php else: ?>
      <h2 style="font-size:1.15rem;margin-bottom:1rem">Quick Links</h2>
      <div style="display:flex;flex-direction:column;gap:.65rem">
        <?php $links=[
          ['href'=>'/user/profile.php', 'ico'=>'user-circle',  'lbl'=>'My Profile',     'col'=>'var(--info)'],
          ['href'=>'/contact.php',      'ico'=>'headset',       'lbl'=>'Contact Admin',   'col'=>'var(--fire)'],
          ['href'=>'/index.php',        'ico'=>'home',          'lbl'=>'View Site',       'col'=>'var(--ok)'],
        ]; foreach ($links as $l): ?>
        <a href="<?= SITE_URL.$l['href'] ?>" class="card card-pad" style="display:flex;align-items:center;gap:.8rem;text-decoration:none">
          <div style="width:38px;height:38px;border-radius:10px;background:var(--veil2);display:flex;align-items:center;justify-content:center;color:<?= $l['col'] ?>;flex-shrink:0"><i class="fas fa-<?= $l['ico'] ?>"></i></div>
          <span style="font-size:.88rem;font-weight:600;color:var(--chalk)"><?= $l['lbl'] ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>
<?php require_once RF_ROOT.'/includes/footer.php'; ?>
