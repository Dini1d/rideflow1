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
        $name   = trim($_POST['vehicle_name']   ?? '');
        $number = trim($_POST['vehicle_number'] ?? '');
        $type   = (int)($_POST['type_id'] ?? 0);
        $cap    = max(1, (int)($_POST['capacity'] ?? 40));
        $amen   = json_encode(array_filter(array_map('trim', explode(',', $_POST['amenities'] ?? ''))));
        if ($name && $number && $type) {
            try { db_insert("INSERT INTO vehicles(type_id,vehicle_name,vehicle_number,capacity,amenities) VALUES(?,?,?,?,?)", [$type,$name,$number,$cap,$amen]); flash('ok','Vehicle added.'); }
            catch (Exception $e) { flash('bad','Vehicle number already exists.'); }
        } else { flash('bad','Name, number, and type are required.'); }
    }
    if ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $cur = db_val("SELECT status FROM vehicles WHERE id=?", [$id]);
        $new = $cur === 'active' ? 'maintenance' : 'active';
        db_exec("UPDATE vehicles SET status=? WHERE id=?", [$new,$id]);
        flash('ok','Status updated.');
    }
    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try { db_exec("DELETE FROM vehicles WHERE id=?", [$id]); flash('ok','Vehicle removed.'); }
        catch (Exception $e) { flash('bad','Cannot delete — vehicle has scheduled trips.'); }
    }
    redirect(SITE_URL.'/admin/vehicles.php');
}

$vehicles = db_all("SELECT v.*, t.type_name, (SELECT COUNT(*) FROM schedules WHERE vehicle_id=v.id AND departure_date>=CURRENT_DATE) trips FROM vehicles v JOIN transport_types t ON t.id=v.type_id ORDER BY t.type_name, v.vehicle_name");
$types    = db_all("SELECT * FROM transport_types WHERE is_active=1 ORDER BY type_name");
$pageTitle = 'Vehicles';
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
    <div><h1>Vehicles</h1><p><?= count($vehicles) ?> vehicles registered</p></div>
    <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-fire btn-sm"><i class="fas fa-plus"></i> Add Vehicle</button>
  </div>
</div>
<?= flash_html() ?>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Vehicle</th><th>Number</th><th>Type</th><th>Capacity</th><th>Amenities</th><th>Upcoming Trips</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (!$vehicles): ?><tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--chalk4)">No vehicles</td></tr><?php endif; ?>
        <?php foreach ($vehicles as $v):
          $tc = strtolower(str_replace([' ','-'],'',$v['type_name']));
          $tc = $tc==='threewheel'?'wheel':$tc;
          $amen = json_decode($v['amenities']??'[]',true);
        ?>
        <tr>
          <td style="font-weight:600;color:var(--chalk)"><?= e($v['vehicle_name']) ?></td>
          <td><span class="mono" style="color:var(--info);font-size:.82rem"><?= e($v['vehicle_number'] ?? '') ?></span></td>
          <td><span class="type-pill tp-<?= $tc ?>"><?= e($v['type_name']) ?></span></td>
          <td style="text-align:center;font-weight:700"><?= $v['capacity'] ?></td>
          <td><div style="display:flex;gap:.25rem;flex-wrap:wrap">
            <?php foreach ($amen as $a): ?>
            <span style="font-size:.7rem;padding:.15rem .5rem;background:var(--veil2);border:1px solid var(--rim);border-radius:var(--r6);color:var(--chalk3)"><?= e($a) ?></span>
            <?php endforeach; ?>
          </div></td>
          <td style="text-align:center;color:<?= $v['trips']>0?'var(--ok)':'var(--chalk4)' ?>;font-weight:700"><?= $v['trips'] ?></td>
          <td><?= status_badge($v['status']) ?></td>
          <td>
            <form method="POST" style="display:flex;gap:.3rem">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $v['id'] ?>">
              <button name="action" value="toggle" class="btn btn-dark btn-sm" title="Toggle status"><i class="fas fa-exchange-alt"></i></button>
              <button name="action" value="delete" class="btn btn-bad btn-sm" title="Delete" onclick="return confirm('Delete this vehicle?')"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div></div></div>

<!-- Add vehicle modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1100;align-items:center;justify-content:center">
  <div style="background:var(--ink2);border:1px solid var(--rim2);border-radius:var(--r4);padding:1.75rem;width:90%;max-width:500px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.1rem">
      <h3 style="margin:0">Add Vehicle</h3>
      <button onclick="document.getElementById('addModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--chalk3);font-size:1.1rem"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="field-group">
        <div class="fld"><label>Vehicle Name</label><input type="text" name="vehicle_name" class="fc" placeholder="Express Coach A1" required></div>
        <div class="fld"><label>Vehicle Number</label><input type="text" name="vehicle_number" class="fc" placeholder="NC-1234" required></div>
      </div>
      <div class="field-group">
        <div class="fld"><label>Transport Type</label>
          <select name="type_id" class="fc" required>
            <option value="">Select type…</option>
            <?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['type_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="fld"><label>Seat Capacity</label><input type="number" name="capacity" class="fc" value="40" min="1" max="500" required></div>
      </div>
      <div class="fld"><label>Amenities (comma-separated)</label>
        <input type="text" name="amenities" class="fc" placeholder="AC, WiFi, USB">
      </div>
      <button type="submit" class="btn btn-fire"><i class="fas fa-plus"></i> Add Vehicle</button>
    </form>
  </div>
</div>
</body></html>
