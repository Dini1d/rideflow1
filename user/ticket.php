<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_login();

$bookingId = (int)($_GET['booking'] ?? 0);
$booking   = db_row("
    SELECT b.*, s.departure_date, s.departure_time, s.arrival_time, s.fare,
           r.origin, r.destination, r.route_name, r.estimated_duration,
           t.type_name, v.vehicle_name, v.vehicle_number, v.capacity,
           p.gateway, p.paid_at, p.status AS pay_status
    FROM bookings b
    JOIN schedules s ON s.id=b.schedule_id
    JOIN routes r    ON r.id=s.route_id
    JOIN transport_types t ON t.id=r.type_id
    JOIN vehicles v  ON v.id=s.vehicle_id
    LEFT JOIN payments p ON p.booking_id=b.id
    WHERE b.id=? AND b.user_id=?", [$bookingId, current_uid()]);

if (!$booking) { flash('bad','Ticket not found.'); redirect(SITE_URL.'/user/bookings.php'); }

// Generate simple QR data string
$qrData = urlencode('RF:'.$booking['booking_ref'].'|'.$booking['origin'].'|'.$booking['destination'].'|'.$booking['departure_date'].'|'.$booking['passenger_name']);
$qrUrl  = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data='.$qrData;

$pageTitle = 'E-Ticket '.$booking['booking_ref'];
require_once RF_ROOT.'/includes/header.php';
?>
<div class="main-content">
<div class="container-sm" style="max-width:680px">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem">
    <div>
      <h1 style="font-size:1.5rem;margin-bottom:.25rem">E-Ticket</h1>
      <div style="font-size:.85rem;color:var(--chalk3)">Show this QR code at the vehicle entrance</div>
    </div>
    <div style="display:flex;gap:.65rem">
      <button onclick="window.print()" class="btn btn-ghost btn-sm"><i class="fas fa-print"></i> Print</button>
      <a href="<?= SITE_URL ?>/user/bookings.php" class="btn btn-dark btn-sm"><i class="fas fa-arrow-left"></i> My Bookings</a>
    </div>
  </div>

  <!-- Ticket -->
  <div class="ticket" id="ticketCard">
    <!-- Header -->
    <div class="ticket-head">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
        <div style="font-family:var(--ff-disp);font-size:1.3rem;font-weight:700">RideFlow</div>
        <div>
          <?php if ($booking['booking_status']==='confirmed'): ?>
          <span style="background:rgba(255,255,255,.2);border-radius:var(--r6);padding:.25rem .85rem;font-size:.75rem;font-weight:700;letter-spacing:.05em">✓ CONFIRMED</span>
          <?php else: ?>
          <span style="background:rgba(255,255,255,.15);border-radius:var(--r6);padding:.25rem .85rem;font-size:.75rem;font-weight:700"><?= strtoupper($booking['booking_status']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div style="font-size:1.6rem;font-weight:900;letter-spacing:-.02em;margin-bottom:.35rem">
        <?= e($booking['origin']) ?> <span style="opacity:.6;font-size:1.2rem">→</span> <?= e($booking['destination']) ?>
      </div>
      <div style="opacity:.8;font-size:.88rem"><?= e($booking['type_name']) ?> · <?= date('D, M j, Y',strtotime($booking['departure_date'])) ?></div>
    </div>

    <!-- Perforated line -->
    <div style="height:1px;background:repeating-linear-gradient(90deg,var(--rim) 0px,var(--rim) 8px,transparent 8px,transparent 14px);position:relative">
      <div style="position:absolute;left:-18px;top:-14px;width:28px;height:28px;border-radius:50%;background:var(--ink)"></div>
      <div style="position:absolute;right:-18px;top:-14px;width:28px;height:28px;border-radius:50%;background:var(--ink)"></div>
    </div>

    <!-- Body -->
    <div class="ticket-body">
      <div>
        <?php
        $rows = [
            ['Booking Ref',      $booking['booking_ref']],
            ['Passenger',        $booking['passenger_name']],
            ['Phone',            $booking['passenger_phone']],
            ['Departure',        substr($booking['departure_time'],0,5).($booking['arrival_time']?' → '.substr($booking['arrival_time'],0,5):'')],
            ['Duration',         $booking['estimated_duration']],
            ['Vehicle',          $booking['vehicle_name'].' ('.$booking['vehicle_number'].')'],
            ['Seats Booked',     $booking['seats_booked'].' seat(s)'],
            ['Total Fare',       'Rs.'.number_format($booking['total_fare'],2)],
            ['Payment',          ucfirst($booking['gateway']??'—').' · '.($booking['pay_status']==='completed'?'Paid':'Pending')],
        ];
        foreach ($rows as $r): ?>
        <div class="ticket-info-row">
          <span class="ti-lbl"><?= $r[0] ?></span>
          <span class="ti-val"><?= e($r[1]) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="ticket-qr">
        <img src="<?= $qrUrl ?>" alt="QR Code" width="120" height="120" loading="lazy">
        <div style="text-align:center;font-size:.65rem;color:var(--chalk4);margin-top:.4rem;font-family:var(--ff-mono)"><?= e($booking['booking_ref']) ?></div>
      </div>
    </div>

    <!-- Footer -->
    <div class="ticket-foot">
      <span>Issued: <?= date('M j, Y H:i') ?></span>
      <span>Valid for single journey only</span>
      <span>rideflow.lk</span>
    </div>
  </div>

  <!-- Actions -->
  <div class="card card-pad" style="margin-top:1.25rem">
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;justify-content:center">
      <button onclick="window.print()" class="btn btn-fire"><i class="fas fa-print"></i> Print Ticket</button>
      <a href="<?= SITE_URL ?>/user/bookings.php" class="btn btn-ghost"><i class="fas fa-list"></i> All Bookings</a>
      <a href="<?= SITE_URL ?>/search.php" class="btn btn-ghost"><i class="fas fa-search"></i> Book Another</a>
      <?php if ($booking['booking_status'] === 'pending'): ?>
      <a href="<?= SITE_URL ?>/payment/checkout.php?booking=<?= $bookingId ?>" class="btn btn-ok"><i class="fas fa-credit-card"></i> Complete Payment</a>
      <?php endif; ?>
    </div>
  </div>

</div>
</div>
<?php require_once RF_ROOT.'/includes/footer.php'; ?>
