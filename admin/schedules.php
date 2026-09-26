<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $act = trim($_POST['action'] ?? '');
    if ($act === 'add') {
        $rId   = (int)($_POST['route_id']      ?? 0);
        $vId   = (int)($_POST['vehicle_id']    ?? 0);
        $date  = trim($_POST['departure_date'] ?? '');
        $dtime = trim($_POST['departure_time'] ?? '');
        $atime = trim($_POST['arrival_time']   ?? '') ?: null;
        $fare  = (float)($_POST['fare']        ?? 0);
        $seats = (int)($_POST['available_seats'] ?? 0);
        if ($rId && $vId && $date && $dtime && $fare > 0) {
            // Default seats = vehicle capacity
            if ($seats <= 0) $seats = (int)db_val("SELECT capacity FROM vehicles WHERE id=?", [$vId]);
            db_insert("INSERT INTO schedules(route_id,vehicle_id,departure_date,departure_time,arrival_time,fare,available_seats) VALUES(?,?,?,?,?,?,?)",
                [$rId,$vId,$date,$dtime,$atime,$fare,$seats]);
            flash('ok','Schedule added.');
        } else { flash('bad','All required fields must be filled.'); }
    }
    if ($act === 'auto_generate') {
        $rId   = (int)($_POST['auto_route_id']      ?? 0);
        $vId   = (int)($_POST['auto_vehicle_id']    ?? 0);
        $start = trim($_POST['auto_start_date']      ?? '');
        $end   = trim($_POST['auto_end_date']        ?? $start);
        $dtime = trim($_POST['auto_departure_time']  ?? '');
        $atime = trim($_POST['auto_arrival_time']    ?? '') ?: null;
        $fare  = (float)($_POST['auto_fare']         ?? 0);
        $seats = (int)($_POST['auto_available_seats'] ?? 0);
        if ($rId && $vId && $start && $dtime && $fare > 0 && $end >= $start) {
            $startTs = strtotime($start);
            $endTs   = strtotime($end);
            $count   = 0;
            if ($seats <= 0) $seats = (int)db_val("SELECT capacity FROM vehicles WHERE id=?", [$vId]);
            while ($startTs <= $endTs) {
                $date = date('Y-m-d', $startTs);
                $exists = db_val("SELECT id FROM schedules WHERE route_id=? AND vehicle_id=? AND departure_date=? AND departure_time=? LIMIT 1", [$rId, $vId, $date, $dtime]);
                if (!$exists) {
                    db_insert("INSERT INTO schedules(route_id,vehicle_id,departure_date,departure_time,arrival_time,fare,available_seats) VALUES(?,?,?,?,?,?,?)",
                        [$rId,$vId,$date,$dtime,$atime,$fare,$seats]);
                    $count++;
                }
                $startTs = strtotime('+1 day', $startTs);
            }
            flash('ok', $count ? 'Generated '.$count.' trip schedule(s).' : 'No new trip schedules were created.');
        } else { flash('bad','Auto-generation requires a valid route, vehicle, date range, time, and fare.'); }
    }
    if ($act === 'cancel') {
        db_exec("UPDATE schedules SET status='cancelled' WHERE id=?", [(int)($_POST['id']??0)]);
        flash('ok','Schedule cancelled.');
    }
    if ($act === 'delete') {
        try { db_exec("DELETE FROM schedules WHERE id=?", [(int)($_POST['id']??0)]); flash('ok','Deleted.'); }
        catch (Exception $e) { flash('bad','Cannot delete — has bookings.'); }
    }
    redirect(SITE_URL.'/admin/schedules.php');
}

$dateFilter = $_GET['date'] ?? date('Y-m-d');
$schedules  = db_all("
    SELECT s.*, r.origin, r.destination, r.route_name, t.type_name, v.vehicle_name, v.vehicle_number,
           COUNT(b.id) booked_seats
    FROM schedules s
    JOIN routes r   ON r.id=s.route_id
    JOIN transport_types t ON t.id=r.type_id
    JOIN vehicles v ON v.id=s.vehicle_id
    LEFT JOIN bookings b ON b.schedule_id=s.id AND b.booking_status!='cancelled'
    WHERE s.departure_date=?
    GROUP BY s.id ORDER BY s.departure_time", [$dateFilter]);

$routes   = db_all("SELECT r.id, r.origin, r.destination, t.type_name FROM routes r JOIN transport_types t ON t.id=r.type_id WHERE r.status='active' ORDER BY t.type_name, r.origin");
$vehicles = db_all("SELECT * FROM vehicles WHERE status='active' ORDER BY vehicle_name");
$pageTitle = 'Schedules';
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

<div class="page-hd">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem">
    <div><h1>Schedules</h1><p><?= count($schedules) ?> trips on <?= date('D, M j, Y',strtotime($dateFilter)) ?></p></div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
      <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-fire btn-sm"><i class="fas fa-plus"></i> Add Schedule</button>
      <button onclick="document.getElementById('autoGenerateModal').style.display='flex'" class="btn btn-dark btn-sm"><i class="fas fa-magic"></i> Auto Generate</button>
    </div>
  </div>
</div>
<?= flash_html() ?>

<!-- Date nav -->
<div style="display:flex;gap:.5rem;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap">
  <a href="?date=<?= date('Y-m-d',strtotime($dateFilter.'-1 day')) ?>" class="btn btn-dark btn-sm"><i class="fas fa-chevron-left"></i></a>
  <form method="GET" style="display:flex;gap:.4rem;align-items:center">
    <div class="fw"><i class="fas fa-calendar fc-icon"></i>
      <input type="date" name="date" class="fc has-icon" value="<?= e($dateFilter) ?>" onchange="this.form.submit()">
    </div>
  </form>
  <a href="?date=<?= date('Y-m-d',strtotime($dateFilter.'+1 day')) ?>" class="btn btn-dark btn-sm"><i class="fas fa-chevron-right"></i></a>
  <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-ghost btn-sm">Today</a>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Route</th><th>Type</th><th>Vehicle</th><th>Departs</th><th>Arrives</th><th>Fare</th><th>Seats (avail/cap)</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (!$schedules): ?><tr><td colspan="9" style="text-align:center;padding:2rem;color:var(--chalk4)">No schedules for this date</td></tr><?php endif; ?>
        <?php foreach ($schedules as $s):
          $tc = strtolower(str_replace([' ','-'],'',$s['type_name']));
          $tc = $tc==='threewheel'?'wheel':$tc;
          $fillPct = $s['booked_seats'] ? round($s['booked_seats'] / ($s['available_seats']+$s['booked_seats']) * 100) : 0;
        ?>
        <tr>
          <td>
            <div style="font-weight:600;font-size:.88rem;color:var(--chalk)"><?= e($s['origin']) ?> → <?= e($s['destination']) ?></div>
            <div style="font-size:.75rem;color:var(--chalk4)"><?= e($s['route_name']) ?></div>
          </td>
          <td><span class="type-pill tp-<?= $tc ?>"><?= e($s['type_name']) ?></span></td>
          <td style="font-size:.84rem">
            <div><?= e($s['vehicle_name']) ?></div>
            <div style="font-size:.75rem;color:var(--chalk4)"><?= e($s['vehicle_number']) ?></div>
          </td>
          <td style="font-weight:700;color:var(--chalk)"><?= substr($s['departure_time'],0,5) ?></td>
          <td style="color:var(--chalk3)"><?= $s['arrival_time']?substr($s['arrival_time'],0,5):'—' ?></td>
          <td style="color:var(--ok);font-weight:700">Rs.<?= number_format((int)num_or_zero($s['fare']),0) ?></td>
          <td>
            <div style="font-size:.82rem;color:var(--chalk2)"><?= $s['available_seats'] ?> / <?= $s['available_seats']+$s['booked_seats'] ?></div>
            <div style="background:var(--ink3);border-radius:3px;height:4px;margin-top:3px;overflow:hidden">
              <div style="width:<?= $fillPct ?>%;background:var(--fire);height:100%"></div>
            </div>
          </td>
          <td><?= status_badge($s['status']) ?></td>
          <td>
            <form method="POST" style="display:flex;gap:.3rem">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $s['id'] ?>">
              <?php if ($s['status']==='scheduled'): ?>
              <button name="action" value="cancel" class="btn btn-warn btn-sm" onclick="return confirm('Cancel this schedule?')"><i class="fas fa-ban"></i></button>
              <?php endif; ?>
              <button name="action" value="delete" class="btn btn-bad btn-sm" onclick="return confirm('Delete schedule?')"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div></div></div>

<!-- Add schedule modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1100;align-items:center;justify-content:center">
  <div style="background:var(--ink2);border:1px solid var(--rim2);border-radius:var(--r4);padding:1.75rem;width:90%;max-width:540px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.1rem">
      <h3 style="margin:0">Add Schedule</h3>
      <button onclick="document.getElementById('addModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--chalk3);font-size:1.1rem"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="fld"><label>Route</label>
        <select name="route_id" class="fc" required>
          <option value="">Select route…</option>
          <?php foreach ($routes as $r): ?>
          <option value="<?= $r['id'] ?>">[<?= e($r['type_name']) ?>] <?= e($r['origin']) ?> → <?= e($r['destination']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fld"><label>Vehicle</label>
        <select name="vehicle_id" class="fc" required>
          <option value="">Select vehicle…</option>
          <?php foreach ($vehicles as $v): ?>
          <option value="<?= $v['id'] ?>"><?= e($v['vehicle_name']) ?> (<?= e($v['vehicle_number']) ?>, cap: <?= $v['capacity'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field-group">
        <div class="fld"><label>Departure Date</label><input type="date" name="departure_date" class="fc" value="<?= $dateFilter ?>" min="<?= date('Y-m-d') ?>" required></div>
        <div class="fld"><label>Departure Time</label><input type="time" name="departure_time" class="fc" required></div>
      </div>
      <div class="field-group">
        <div class="fld"><label>Arrival Time (optional)</label><input type="time" name="arrival_time" class="fc"></div>
        <div class="fld"><label>Fare (Rs.)</label><input type="number" name="fare" class="fc" min="0" step="0.01" required placeholder="450.00"></div>
      </div>
      <div class="fld"><label>Available Seats (0 = auto from vehicle)</label><input type="number" name="available_seats" class="fc" min="0" placeholder="0 = use vehicle capacity"></div>
      <button type="submit" class="btn btn-fire"><i class="fas fa-plus"></i> Add Schedule</button>
    </form>
  </div>
</div>

<!-- Auto generate schedule modal -->
<div id="autoGenerateModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1100;align-items:center;justify-content:center">
  <div style="background:var(--ink2);border:1px solid var(--rim2);border-radius:var(--r4);padding:1.75rem;width:90%;max-width:560px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.1rem">
      <h3 style="margin:0">Auto Generate Trips</h3>
      <button onclick="document.getElementById('autoGenerateModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--chalk3);font-size:1.1rem"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="auto_generate">
      <div class="fld"><label>Route</label>
        <select name="auto_route_id" class="fc" required>
          <option value="">Select route…</option>
          <?php foreach ($routes as $r): ?>
          <option value="<?= $r['id'] ?>">[<?= e($r['type_name']) ?>] <?= e($r['origin']) ?> → <?= e($r['destination']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fld"><label>Vehicle</label>
        <select name="auto_vehicle_id" class="fc" required>
          <option value="">Select vehicle…</option>
          <?php foreach ($vehicles as $v): ?>
          <option value="<?= $v['id'] ?>"><?= e($v['vehicle_name']) ?> (<?= e($v['vehicle_number']) ?>, cap: <?= $v['capacity'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field-group">
        <div class="fld"><label>Start Date</label><input type="date" name="auto_start_date" class="fc" value="<?= $dateFilter ?>" min="<?= date('Y-m-d') ?>" required></div>
        <div class="fld"><label>End Date</label><input type="date" name="auto_end_date" class="fc" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" min="<?= date('Y-m-d') ?>" required></div>
      </div>
      <div class="field-group">
        <div class="fld"><label>Departure Time</label><input type="time" name="auto_departure_time" class="fc" required></div>
        <div class="fld"><label>Arrival Time</label><input type="time" name="auto_arrival_time" class="fc"></div>
      </div>
      <div class="field-group">
        <div class="fld"><label>Fare (Rs.)</label><input type="number" name="auto_fare" class="fc" min="0" step="0.01" required placeholder="450.00"></div>
        <div class="fld"><label>Seats (0 = auto)</label><input type="number" name="auto_available_seats" class="fc" min="0" placeholder="0 = vehicle cap"></div>
      </div>
      <button type="submit" class="btn btn-fire"><i class="fas fa-magic"></i> Generate Trips</button>
    </form>
  </div>
</div>
</body></html>
