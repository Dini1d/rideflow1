<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_login();

$bookingId = (int)($_GET['booking'] ?? $_POST['booking_id'] ?? 0);
$gateway   = trim($_GET['gateway'] ?? $_POST['gateway'] ?? 'cash');

$booking = db_row("
    SELECT b.*, s.departure_date, s.departure_time, r.origin, r.destination, t.type_name
    FROM bookings b
    JOIN schedules s ON s.id=b.schedule_id
    JOIN routes r    ON r.id=s.route_id
    JOIN transport_types t ON t.id=r.type_id
    WHERE b.id=? AND b.user_id=?", [$bookingId, current_uid()]);

if (!$booking) { flash('bad','Booking not found.'); redirect(SITE_URL.'/user/bookings.php'); }
if ($booking['payment_status'] === 'paid') { redirect(SITE_URL.'/user/ticket.php?booking='.$bookingId); }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
    csrf_guard();
    $gatewayMap = [
        'card' => 'stripe',
        'stripe' => 'stripe',
        'paypal' => 'paypal',
        'ezCash' => 'ezCash',
        'genie' => 'genie',
        'bank_transfer' => 'bank_transfer',
        'cash' => 'cash',
    ];
    $selectedGateway = trim($_POST['gateway'] ?? 'cash');
    if (!isset($gatewayMap[$selectedGateway])) {
        $err = 'Please select a valid payment method.';
    } else {
    $gw = $gatewayMap[$selectedGateway];
    // Simulate payment processing
    $ref    = 'TXN-'.strtoupper(bin2hex(random_bytes(5)));
    $status = in_array($gw, ['cash','bank_transfer']) ? 'pending' : 'completed';
    // For Stripe/PayPal demo — mark completed directly (real: use webhook)
    try {
        db_exec("INSERT INTO payments(booking_id,user_id,gateway,amount,currency,status,transaction_ref,paid_at)
                 VALUES(?,?,?,?,'LKR',?,?,?)",
            [$bookingId, current_uid(), $gw, $booking['total_fare'], $status, $ref,
             $status==='completed'?date('Y-m-d H:i:s'):null]);
    } catch (Exception $e) {
        db_exec("UPDATE payments SET gateway=?,status=?,transaction_ref=?,paid_at=? WHERE booking_id=?",
            [$gw,$status,$ref,$status==='completed'?date('Y-m-d H:i:s'):null,$bookingId]);
    }
    if ($status === 'completed') {
        db_exec("UPDATE bookings SET payment_status='paid', booking_status='confirmed' WHERE id=?", [$bookingId]);
        notify(current_uid(),'Booking Confirmed!',
            'Your booking '.$booking['booking_ref'].' is confirmed. View your e-ticket.',
            'booking', SITE_URL.'/user/ticket.php?booking='.$bookingId);
        flash('ok','Payment successful! Your booking is confirmed.');
        redirect(SITE_URL.'/user/ticket.php?booking='.$bookingId);
    } else {
        db_exec("UPDATE bookings SET payment_status='unpaid' WHERE id=?", [$bookingId]);
        flash('ok','Cash/Transfer payment recorded. Show your booking ref at departure.');
        redirect(SITE_URL.'/user/ticket.php?booking='.$bookingId);
    }
    }
}

$pageTitle = 'Payment — '.$booking['booking_ref'];
require_once RF_ROOT.'/includes/header.php';
?>
<div class="main-content">
<div class="container-sm" style="max-width:580px">

  <div class="steps-nav">
    <div class="step-item done"><div class="step-num">✓</div><div class="step-lbl">Search</div></div>
    <div class="step-line"></div>
    <div class="step-item done"><div class="step-num">✓</div><div class="step-lbl">Book</div></div>
    <div class="step-line"></div>
    <div class="step-item on"><div class="step-num">3</div><div class="step-lbl">Payment</div></div>
    <div class="step-line"></div>
    <div class="step-item"><div class="step-num">4</div><div class="step-lbl">Ticket</div></div>
  </div>

  <?php if ($err): ?><div class="alert a-bad"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div><?php endif; ?>

  <div class="card card-pad" style="margin-bottom:1.25rem">
    <div class="label" style="margin-bottom:.6rem">Order Summary</div>
    <div style="display:flex;justify-content:space-between;align-items:center">
      <div>
        <div style="font-weight:700;color:var(--chalk)"><?= e($booking['origin']) ?> → <?= e($booking['destination']) ?></div>
        <div style="font-size:.82rem;color:var(--chalk3)"><?= date('D, M j',strtotime($booking['departure_date'])) ?> · <?= $booking['seats_booked'] ?> seat(s) · <?= e($booking['booking_ref']) ?></div>
      </div>
      <div style="font-family:var(--ff-disp);font-size:1.6rem;font-weight:900;color:var(--fire)">
        Rs.<?= number_format($booking['total_fare'],2) ?>
      </div>
    </div>
  </div>

  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
    <input type="hidden" name="pay" value="1">
    <input type="hidden" name="gateway" id="gwInput" value="<?= e($gateway) ?>">

    <div class="card card-pad" style="margin-bottom:1.25rem">
      <div class="label" style="margin-bottom:.85rem">Select Payment Method</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem">
        <?php
        $gws=[
          ['id'=>'card',          'ico'=>'credit-card',  'fab'=>false,'label'=>'Credit / Debit Card','sub'=>'Visa, Mastercard · Stripe'],
          ['id'=>'paypal',        'ico'=>'paypal',       'fab'=>true, 'label'=>'PayPal','sub'=>'Fast & secure'],
          ['id'=>'ezCash',        'ico'=>'mobile-alt',   'fab'=>false,'label'=>'Dialog eZ Cash','sub'=>'Sri Lanka mobile wallet'],
          ['id'=>'genie',         'ico'=>'mobile-alt',   'fab'=>false,'label'=>'Genie by HNB','sub'=>'Sri Lanka mobile wallet'],
          ['id'=>'bank_transfer', 'ico'=>'university',   'fab'=>false,'label'=>'Bank Transfer','sub'=>'Pay within 24h'],
          ['id'=>'cash',          'ico'=>'money-bill-wave','fab'=>false,'label'=>'Cash on Board','sub'=>'Pay driver/conductor'],
        ];
        foreach ($gws as $gw):
          $sel = ($gw['id'] === $gateway);
        ?>
        <div class="gw-option" data-gw="<?= $gw['id'] ?>" onclick="selectGw('<?= $gw['id'] ?>')"
          style="border:1.5px solid <?= $sel?'var(--fire)':'var(--rim)' ?>;border-radius:var(--r3);padding:.85rem;cursor:pointer;transition:all .18s;background:<?= $sel?'var(--fire-soft)':'transparent' ?>">
          <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.35rem">
            <i class="<?= $gw['fab']?'fab':'fas' ?> fa-<?= $gw['ico'] ?>" style="color:var(--fire);font-size:1.1rem;width:22px;text-align:center"></i>
            <span style="font-size:.85rem;font-weight:700;color:var(--chalk)"><?= $gw['label'] ?></span>
          </div>
          <div style="font-size:.74rem;color:var(--chalk4);padding-left:28px"><?= $gw['sub'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Gateway-specific fields -->
    <div id="gw-card" class="gw-fields card card-pad" style="margin-bottom:1.25rem;<?= $gateway!=='card'?'display:none':'' ?>">
      <div class="label" style="margin-bottom:.75rem">Card Details <span style="color:var(--chalk4);font-weight:400">(Demo — no real charge)</span></div>
      <div class="fld">
        <label>Card Number</label>
        <div class="fw"><i class="fas fa-credit-card fc-icon"></i>
          <input type="text" class="fc has-icon" placeholder="4242 4242 4242 4242" maxlength="19" oninput="this.value=this.value.replace(/[^0-9]/g,'').replace(/(.{4})/g,'$1 ').trim()">
        </div>
      </div>
      <div class="field-group">
        <div class="fld" style="margin-bottom:0"><label>Expiry</label><input type="text" class="fc" placeholder="MM / YY" maxlength="7"></div>
        <div class="fld" style="margin-bottom:0"><label>CVV</label><input type="text" class="fc" placeholder="123" maxlength="4"></div>
      </div>
      <div class="alert a-info" style="margin-top:.85rem;margin-bottom:0"><i class="fas fa-info-circle"></i> Demo mode — enter any values. Real Stripe integration requires STRIPE_SK in config.</div>
    </div>

    <div id="gw-ezCash" class="gw-fields card card-pad" style="margin-bottom:1.25rem;display:none">
      <div class="label" style="margin-bottom:.75rem">eZ Cash</div>
      <div class="fld"><label>eZ Cash Mobile Number</label>
        <div class="fw"><i class="fas fa-mobile-alt fc-icon"></i>
          <input type="tel" class="fc has-icon" placeholder="07X XXX XXXX">
        </div>
      </div>
      <div class="alert a-info" style="margin-bottom:0"><i class="fas fa-info-circle"></i> A USSD prompt will be sent to your phone to confirm payment. (Simulated in demo.)</div>
    </div>

    <div id="gw-genie" class="gw-fields card card-pad" style="margin-bottom:1.25rem;display:none">
      <div class="label" style="margin-bottom:.75rem">Genie by HNB</div>
      <div class="fld"><label>Genie Account Mobile Number</label>
        <div class="fw"><i class="fas fa-mobile-alt fc-icon"></i>
          <input type="tel" class="fc has-icon" placeholder="07X XXX XXXX">
        </div>
      </div>
      <div class="alert a-info" style="margin-bottom:0"><i class="fas fa-info-circle"></i> A push notification will be sent to your Genie app. (Simulated in demo.)</div>
    </div>

    <div id="gw-bank_transfer" class="gw-fields card card-pad" style="margin-bottom:1.25rem;display:none">
      <div class="label" style="margin-bottom:.75rem">Bank Transfer Details</div>
      <div style="font-size:.85rem;color:var(--chalk2);line-height:1.8">
        Bank: <strong style="color:var(--chalk)">Commercial Bank of Ceylon</strong><br>
        Account: <strong style="color:var(--chalk)">8001234567</strong><br>
        Branch: <strong style="color:var(--chalk)">Colombo 03</strong><br>
        Reference: <strong style="color:var(--fire)"><?= e($booking['booking_ref']) ?></strong>
      </div>
      <div class="alert a-warn" style="margin-top:.85rem;margin-bottom:0"><i class="fas fa-exclamation-triangle"></i> Transfer within 24 hours. Your seat is held for 24h pending payment confirmation.</div>
    </div>

    <div id="gw-cash" class="gw-fields card card-pad" style="margin-bottom:1.25rem;display:none">
      <div class="alert a-info" style="margin-bottom:0"><i class="fas fa-info-circle"></i> Show your booking reference <strong><?= e($booking['booking_ref']) ?></strong> to the conductor/driver. Pay in cash on board.</div>
    </div>

    <div id="gw-paypal" class="gw-fields card card-pad" style="margin-bottom:1.25rem;display:none">
      <div class="alert a-info" style="margin-bottom:0"><i class="fab fa-paypal"></i> You will be redirected to PayPal to complete payment. (Demo — configure PAYPAL_CLIENT_ID in config.)</div>
    </div>

    <button type="submit" class="btn btn-fire btn-full btn-lg" id="payBtn">
      <i class="fas fa-lock"></i> Pay Rs.<?= number_format($booking['total_fare'],2) ?> Securely
    </button>
    <div style="text-align:center;font-size:.75rem;color:var(--chalk4);margin-top:.55rem">
      <i class="fas fa-shield-alt"></i> 256-bit SSL encrypted · <i class="fas fa-undo"></i> Cancellation policy applies
    </div>
  </form>
</div>
</div>

<script>
function selectGw(gw) {
  document.getElementById('gwInput').value = gw;
  document.querySelectorAll('.gw-option').forEach(function(el){
    var sel = el.dataset.gw === gw;
    el.style.borderColor = sel ? 'var(--fire)' : 'var(--rim)';
    el.style.background  = sel ? 'var(--fire-soft)' : 'transparent';
  });
  document.querySelectorAll('.gw-fields').forEach(function(el){ el.style.display='none'; });
  var f = document.getElementById('gw-'+gw);
  if (f) f.style.display = 'block';
}
selectGateway = selectGw; // alias for app.js
</script>
<?php require_once RF_ROOT.'/includes/footer.php'; ?>
