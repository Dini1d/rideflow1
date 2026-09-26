<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_driver();

$scheduleId = (int)($_GET['schedule'] ?? 0);
$schedule = db_row("
    SELECT s.*, r.origin, r.destination FROM schedules s
    JOIN routes r ON r.id=s.route_id
    JOIN drivers d ON d.vehicle_id=s.vehicle_id
    WHERE s.id=? AND d.user_id=?", [$scheduleId, current_uid()]);

if (!$schedule) { flash('bad','Trip not found or not assigned to you.'); redirect(SITE_URL.'/driver/dashboard.php'); }

$passengers = db_all("
    SELECT booking_ref, passenger_name, passenger_phone, seats_booked, booking_status
    FROM bookings WHERE schedule_id=? AND booking_status<>'cancelled'
    ORDER BY created_at", [$scheduleId]);

$pageTitle = 'Passenger Manifest';
require_once RF_ROOT.'/includes/header.php';
?>
<div class="main-content">
<div class="container">
  <a href="<?= SITE_URL ?>/driver/dashboard.php" style="font-size:.85rem"><i class="fas fa-arrow-left"></i> Back to dashboard</a>
  <h1 style="font-size:1.4rem;margin:.75rem 0 1.5rem"><?= e($schedule['origin']) ?> → <?= e($schedule['destination']) ?> — Manifest</h1>

  <?php if (!$passengers): ?>
  <div class="card card-pad" style="text-align:center;padding:2rem">No passengers booked yet.</div>
  <?php else: ?>
  <div class="card" style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.88rem">
      <thead><tr style="text-align:left;border-bottom:1px solid var(--rim)">
        <th style="padding:.75rem">Ref</th><th style="padding:.75rem">Name</th><th style="padding:.75rem">Phone</th><th style="padding:.75rem">Seats</th><th style="padding:.75rem">Status</th>
      </tr></thead>
      <tbody>
      <?php foreach ($passengers as $p): ?>
      <tr style="border-bottom:1px solid var(--rim)">
        <td style="padding:.75rem;font-family:var(--ff-mono)"><?= e($p['booking_ref']) ?></td>
        <td style="padding:.75rem"><?= e($p['passenger_name']) ?></td>
        <td style="padding:.75rem"><?= e($p['passenger_phone']) ?></td>
        <td style="padding:.75rem"><?= (int)$p['seats_booked'] ?></td>
        <td style="padding:.75rem"><?= status_badge($p['booking_status']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
</div>
<?php require_once RF_ROOT.'/includes/footer.php'; ?>
