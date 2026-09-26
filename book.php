<?php
define('RF_ROOT', __DIR__);
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_login();

$scheduleId = (int)($_GET['schedule'] ?? $_POST['schedule_id'] ?? 0);
$seats      = max(1, min(10, (int)($_GET['seats'] ?? $_POST['seats_booked'] ?? 1)));

$schedule = db_row("
    SELECT s.*, r.origin, r.destination, r.route_name, r.estimated_duration, r.distance_km,
           t.type_name, v.vehicle_name, v.capacity
    FROM schedules s
    JOIN routes r   ON r.id=s.route_id
    JOIN transport_types t ON t.id=r.type_id
    JOIN vehicles v ON v.id=s.vehicle_id
    WHERE s.id=? AND s.status='scheduled' AND s.departure_date>=CURRENT_DATE", [$scheduleId]);

if (!$schedule) { flash('bad','This trip is not available.'); redirect(SITE_URL.'/search.php'); }
if ($schedule['available_seats'] < $seats) {
    flash('bad','Only '.$schedule['available_seats'].' seat(s) available.');
    redirect(SITE_URL.'/search.php');
}

$err = '';
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    csrf_guard();
    $pName    = trim($_POST['passenger_name']  ?? $user['name']);
    $pPhone   = trim($_POST['passenger_phone'] ?? $user['phone']);
    $pEmail   = trim($_POST['passenger_email'] ?? $user['email']);
    $gateway  = trim($_POST['payment_gateway'] ?? 'cash');
    $seatsBk  = max(1, min(10, (int)($_POST['seats_booked'] ?? 1)));

    if (!$pName || !$pPhone) { $err = 'Passenger name and phone are required.'; }
    else {
        db()->beginTransaction();
        try {
            // Lock schedule row
            $sch = db_row("SELECT available_seats FROM schedules WHERE id=? FOR UPDATE", [$scheduleId]);
            if ($sch['available_seats'] < $seatsBk) throw new Exception('Only '.$sch['available_seats'].' seat(s) left.');
            $total = $schedule['fare'] * $seatsBk;
            $ref   = booking_ref();
            $bkId  = db_insert("
                INSERT INTO bookings(user_id,schedule_id,booking_ref,seats_booked,total_fare,passenger_name,passenger_phone,passenger_email,booking_status,payment_status)
                VALUES(?,?,?,?,?,'pending','unpaid',?,?,?)",
                [current_uid(),$scheduleId,$ref,$seatsBk,$total,$pName,$pPhone,$pEmail]);
            db_exec("UPDATE schedules SET available_seats=available_seats-? WHERE id=?", [$seatsBk,$scheduleId]);
            db()->commit();
            // Route to payment
            flash('ok','Booking '.$ref.' created! Complete payment to confirm.');
            redirect(SITE_URL.'/payment/checkout.php?booking='.$bkId.'&gateway='.$gateway);
        } catch (Exception $e) {
            db()->rollBack();
            $err = $e->getMessage();
        }
    }
}

$total = $schedule['fare'] * $seats;
$tc    = strtolower(str_replace([' ','-'],'',$schedule['type_name']));
$tc    = $tc==='threewheel'?'wheel':$tc;
$pageTitle = 'Book — '.$schedule['origin'].' → '.$schedule['destination'];
require_once RF_ROOT.'/includes/header.php';
?>
<div class="main-content">
<div class="container-sm">
  <!-- Step nav -->
  <div class="steps-nav">
    <div class="step-item done"><div class="step-num">✓</div><div class="step-lbl">Search</div></div>
    <div class="step-line"></div>
    <div class="step-item on"><div class="step-num">2</div><div class="step-lbl">Book</div></div>
    <div class="step-line"></div>
    <div class="step-item"><div class="step-num">3</div><div class="step-lbl">Payment</div></div>
    <div class="step-line"></div>
    <div class="step-item"><div class="step-num">4</div><div class="step-lbl">Ticket</div></div>
  </div>

  <?php if ($err): ?><div class="alert a-bad"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div><?php endif; ?>

  <div class="grid-2" style="gap:1.5rem;align-items:start">

    <!-- Trip summary -->
    <div>
      <h2 style="font-size:1.2rem;margin-bottom:1rem">Trip Summary</h2>
      <div class="card card-pad">
        <div style="margin-bottom:.75rem"><span class="type-pill tp-<?= $tc ?>"><?= e($schedule['type_name']) ?></span></div>
        <div style="font-size:1.2rem;font-weight:700;margin-bottom:.65rem">
          <?= e($schedule['origin']) ?> <span style="color:var(--fire)">→</span> <?= e($schedule['destination']) ?>
        </div>
        <?php $rows=[
          ['<i class="fas fa-calendar"></i> Date', date('D, M j, Y',strtotime($schedule['departure_date']))],
          ['<i class="fas fa-clock"></i> Departs', substr($schedule['departure_time'],0,5)],
          ['<i class="fas fa-flag-checkered"></i> Arrives', $schedule['arrival_time']?substr($schedule['arrival_time'],0,5):'—'],
          ['<i class="fas fa-hourglass-half"></i> Duration', $schedule['estimated_duration']],
          ['<i class="fas fa-bus"></i> Vehicle', $schedule['vehicle_name']],
          ['<i class="fas fa-chair"></i> Your Seats', '<strong>'.$seats.'</strong>'],
          ['<i class="fas fa-tag"></i> Fare/Seat', 'Rs.'.number_format($schedule['fare'],2)],
        ];
        foreach ($rows as $row): ?>
        <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--rim);font-size:.85rem">
          <span style="color:var(--chalk3)"><?= $row[0] ?></span>
          <span style="color:var(--chalk)"><?= $row[1] ?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;padding:.75rem 0 0;font-size:1.1rem;font-weight:700">
          <span>Total</span>
          <span style="color:var(--fire)" id="totalFare" data-fare="<?= $schedule['fare'] ?>">Rs.<?= number_format($total,2) ?></span>
        </div>
      </div>

      <!-- Seat count -->
      <div class="card card-pad" style="margin-top:1rem">
        <div class="label" style="margin-bottom:.6rem">Number of Seats</div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
          <?php for ($i=1; $i<=min(6,$schedule['available_seats']); $i++): ?>
          <button type="button" class="seat-btn" data-val="<?= $i ?>"
            style="width:40px;height:40px;border-radius:50%;background:<?= $i===$seats?'var(--fire)':'var(--ink3)' ?>;border:1.5px solid <?= $i===$seats?'var(--fire)':'var(--rim)' ?>;color:<?= $i===$seats?'#fff':'var(--chalk3)' ?>;cursor:pointer;font-weight:700;transition:all .15s">
            <?= $i ?>
          </button>
          <?php endfor; ?>
        </div>
        <div style="font-size:.78rem;color:var(--chalk4);margin-top:.5rem"><?= $schedule['available_seats'] ?> seats available on this trip</div>
      </div>
    </div>

    <!-- Booking form -->
    <div>
      <h2 style="font-size:1.2rem;margin-bottom:1rem">Passenger Details</h2>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="schedule_id" value="<?= $scheduleId ?>">
        <input type="hidden" name="seats_booked" id="seatsHidden" value="<?= $seats ?>">
        <input type="hidden" name="confirm" value="1">

        <div class="card card-pad" style="margin-bottom:1rem">
          <div class="fld">
            <label>Passenger Name</label>
            <div class="fw"><i class="fas fa-user fc-icon"></i>
              <input type="text" name="passenger_name" class="fc has-icon" required value="<?= e($user['name']??'') ?>" placeholder="Full name as on NIC">
            </div>
          </div>
          <div class="field-group">
            <div class="fld" style="margin-bottom:0">
              <label>Phone Number</label>
              <div class="fw"><i class="fas fa-phone fc-icon"></i>
                <input type="tel" name="passenger_phone" class="fc has-icon" required value="<?= e($user['phone']??'') ?>">
              </div>
            </div>
            <div class="fld" style="margin-bottom:0">
              <label>Email (optional)</label>
              <div class="fw"><i class="fas fa-envelope fc-icon"></i>
                <input type="email" name="passenger_email" class="fc has-icon" value="<?= e($user['email']??'') ?>">
              </div>
            </div>
          </div>
        </div>

        <!-- Payment gateway -->
        <div class="card card-pad" style="margin-bottom:1rem">
          <div class="label" style="margin-bottom:.85rem">Payment Method</div>
          <input type="hidden" name="payment_gateway" id="payment_gateway" value="card">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem">
            <?php
            $gateways = [
              ['id'=>'card',          'label'=>'Card / Stripe',  'ico'=>'credit-card', 'col'=>'var(--info)'],
              ['id'=>'paypal',        'label'=>'PayPal',         'ico'=>'paypal',       'col'=>'#003087','fab'=>true],
              ['id'=>'ezCash',        'label'=>'eZ Cash',        'ico'=>'mobile-alt',   'col'=>'var(--ok)'],
              ['id'=>'genie',         'label'=>'Genie',          'ico'=>'mobile-alt',   'col'=>'var(--warn)'],
              ['id'=>'bank_transfer', 'label'=>'Bank Transfer',  'ico'=>'university',   'col'=>'var(--chalk3)'],
              ['id'=>'cash',          'label'=>'Cash on Board',  'ico'=>'money-bill',   'col'=>'var(--chalk3)'],
            ];
            foreach ($gateways as $gw): ?>
            <div class="gw-option" data-gw="<?= $gw['id'] ?>" onclick="selectGateway('<?= $gw['id'] ?>')"
              style="border:1.5px solid <?= $gw['id']==='card'?'var(--fire)':'var(--rim)' ?>;border-radius:var(--r2);padding:.65rem;cursor:pointer;display:flex;align-items:center;gap:.55rem;transition:all .18s;background:<?= $gw['id']==='card'?'var(--fire-soft)':'transparent' ?>">
              <i class="<?= isset($gw['fab'])?'fab':'fas' ?> fa-<?= $gw['ico'] ?>" style="color:<?= $gw['col'] ?>;font-size:1rem;width:20px;text-align:center"></i>
              <span style="font-size:.82rem;font-weight:600;color:var(--chalk2)"><?= $gw['label'] ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <button type="submit" class="btn btn-fire btn-full btn-lg">
          <i class="fas fa-lock"></i> Confirm & Pay <span id="btnTotal">Rs.<?= number_format($total,2) ?></span>
        </button>
        <div style="text-align:center;font-size:.75rem;color:var(--chalk4);margin-top:.6rem"><i class="fas fa-shield-alt"></i> Secure 256-bit SSL encrypted payment</div>
      </form>
    </div>
  </div>
</div>
</div>

<script>
document.querySelectorAll('.seat-btn').forEach(function(btn){
  btn.addEventListener('click',function(){
    var n=parseInt(btn.dataset.val);
    document.getElementById('seatsHidden').value=n;
    document.querySelectorAll('.seat-btn').forEach(function(b){
      var sel=parseInt(b.dataset.val)===n;
      b.style.background=sel?'var(--fire)':'var(--ink3)';
      b.style.borderColor=sel?'var(--fire)':'var(--rim)';
      b.style.color=sel?'#fff':'var(--chalk3)';
    });
    var fare=parseFloat(document.getElementById('totalFare').dataset.fare);
    var total=fare*n;
    document.getElementById('totalFare').textContent='Rs.'+total.toLocaleString('en-LK',{minimumFractionDigits:2});
    document.getElementById('btnTotal').textContent='Rs.'+total.toLocaleString('en-LK',{minimumFractionDigits:2});
  });
});
</script>
<?php require_once RF_ROOT.'/includes/footer.php'; ?>
