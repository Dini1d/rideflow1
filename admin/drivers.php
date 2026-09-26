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
        $uid  = (int)($_POST['user_id']   ?? 0);
        $vid  = (int)($_POST['vehicle_id']?? 0) ?: null;
        $lic  = trim($_POST['license_no'] ?? '');
        $exp  = (int)($_POST['experience']?? 0);
        if ($uid && $lic) {
            try {
                db_exec("UPDATE users SET role='driver' WHERE id=?", [$uid]);
                db_insert("INSERT INTO drivers(user_id,vehicle_id,license_no,experience) VALUES(?,?,?,?)", [$uid,$vid,$lic,$exp]);
                flash('ok','Driver added.');
            } catch(Exception $e){ flash('bad','User already registered as driver.'); }
        } else { flash('bad','User and license are required.'); }
    }
    if ($act === 'toggle') {
        $id = (int)($_POST['id']??0);
        $cur= db_val("SELECT status FROM drivers WHERE id=?",[$id]);
        db_exec("UPDATE drivers SET status=? WHERE id=?",[$cur==='active'?'inactive':'active',$id]);
        flash('ok','Driver status updated.');
    }
    if ($act === 'assign') {
        $id  = (int)($_POST['id']      ?? 0);
        $vid = (int)($_POST['vehicle_id']??0) ?: null;
        db_exec("UPDATE drivers SET vehicle_id=? WHERE id=?",[$vid,$id]);
        flash('ok','Vehicle assigned.');
    }
    redirect(SITE_URL.'/admin/drivers.php');
}

$drivers  = db_all("SELECT d.*, u.id AS user_id, u.name, u.email, u.phone, v.vehicle_name, v.vehicle_number FROM users u LEFT JOIN drivers d ON d.user_id=u.id LEFT JOIN vehicles v ON v.id=d.vehicle_id WHERE u.role='driver' OR d.id IS NOT NULL ORDER BY u.name");
$customers= db_all("SELECT u.id,u.name,u.email FROM users u LEFT JOIN drivers d ON d.user_id=u.id WHERE u.role IN ('customer','driver') AND d.id IS NULL AND (u.role='driver' OR u.status='active') ORDER BY u.name");
$vehicles = db_all("SELECT id,vehicle_name,vehicle_number FROM vehicles WHERE status='active' ORDER BY vehicle_name");
$pageTitle= 'Drivers';
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
    <div><h1>Drivers</h1><p><?= count($drivers) ?> registered drivers</p></div>
    <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-fire btn-sm"><i class="fas fa-plus"></i> Add Driver</button>
  </div>
</div>
<?= flash_html() ?>
<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Driver</th><th>License</th><th>Experience</th><th>Assigned Vehicle</th><th>Rating</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if(!$drivers):?><tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--chalk4)">No drivers</td></tr><?php endif;?>
        <?php foreach($drivers as $d):?>
        <tr>
          <td><div style="font-weight:600;font-size:.88rem;color:var(--chalk)"><?= e($d['name']) ?></div><div style="font-size:.75rem;color:var(--chalk4)"><?= e($d['email']) ?></div></td>
          <td class="mono" style="font-size:.82rem;color:var(--info)"><?= e($d['license_no'] ?? '—') ?></td>
          <td style="text-align:center"><?= $d['id'] ? (int)$d['experience'].' yr'.((int)$d['experience']!==1?'s':'') : '—' ?></td>
          <td><?= $d['vehicle_name'] ? e($d['vehicle_name']).' ('.e($d['vehicle_number'] ?? '').')' : '<span style="color:var(--chalk4)">— Unassigned —</span>' ?></td>
          <td><span style="color:var(--warn)">★</span> <?= number_format(num_or_zero($d['rating']),1) ?></td>
          <td><?= $d['id'] ? status_badge($d['status']) : '<span class="badge b-warn">Profile incomplete</span>' ?></td>
          <td>
            <div style="display:flex;gap:.3rem;flex-wrap:wrap">
              <?php if (!$d['id']): ?>
              <button type="button" onclick="addDriverProfile(<?= (int)$d['user_id'] ?>)" class="btn btn-fire btn-sm"><i class="fas fa-id-card"></i> Complete Profile</button>
              <?php else: ?>
              <form method="POST" style="display:flex;gap:.3rem">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $d['id'] ?>">
                <button name="action" value="toggle" class="btn btn-dark btn-sm"><i class="fas fa-exchange-alt"></i></button>
              </form>
              <button onclick="assignVehicle(<?= $d['id'] ?>,<?= $d['vehicle_id']??'null' ?>)" class="btn btn-info btn-sm"><i class="fas fa-car"></i> Assign</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>
</div></div></div>

<!-- Add driver modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1100;align-items:center;justify-content:center">
  <div style="background:var(--ink2);border:1px solid var(--rim2);border-radius:var(--r4);padding:1.75rem;width:90%;max-width:500px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.1rem">
      <h3 style="margin:0">Add Driver</h3>
      <button onclick="document.getElementById('addModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--chalk3);font-size:1.1rem"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="fld"><label>Select Customer Account</label>
        <select name="user_id" id="driverUserSelect" class="fc" required>
          <option value="">Choose user to promote to driver…</option>
          <?php foreach($customers as $c):?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?> — <?= e($c['email']) ?></option><?php endforeach;?>
        </select>
      </div>
      <div class="field-group">
        <div class="fld"><label>License Number</label><input type="text" name="license_no" class="fc" placeholder="B1234567" required></div>
        <div class="fld"><label>Experience (years)</label><input type="number" name="experience" class="fc" value="0" min="0" max="50"></div>
      </div>
      <div class="fld"><label>Assign Vehicle (optional)</label>
        <select name="vehicle_id" class="fc">
          <option value="">— No vehicle yet —</option>
          <?php foreach($vehicles as $v):?><option value="<?= $v['id'] ?>"><?= e($v['vehicle_name']) ?> (<?= e($v['vehicle_number']) ?>)</option><?php endforeach;?>
        </select>
      </div>
      <button type="submit" class="btn btn-fire"><i class="fas fa-plus"></i> Add Driver</button>
    </form>
  </div>
</div>

<!-- Assign vehicle modal -->
<div id="assignModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1100;align-items:center;justify-content:center">
  <div style="background:var(--ink2);border:1px solid var(--rim2);border-radius:var(--r4);padding:1.75rem;width:90%;max-width:400px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.1rem">
      <h3 style="margin:0">Assign Vehicle</h3>
      <button onclick="document.getElementById('assignModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--chalk3);font-size:1.1rem"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="assign">
      <input type="hidden" name="id" id="assignDriverId">
      <div class="fld"><label>Vehicle</label>
        <select name="vehicle_id" id="assignVehicleSelect" class="fc">
          <option value="">— Unassign —</option>
          <?php foreach($vehicles as $v):?><option value="<?= $v['id'] ?>"><?= e($v['vehicle_name']) ?> (<?= e($v['vehicle_number']) ?>)</option><?php endforeach;?>
        </select>
      </div>
      <button type="submit" class="btn btn-fire"><i class="fas fa-save"></i> Save Assignment</button>
    </form>
  </div>
</div>
<script>
function assignVehicle(id, currentVid) {
  document.getElementById('assignDriverId').value = id;
  var sel = document.getElementById('assignVehicleSelect');
  sel.value = currentVid || '';
  document.getElementById('assignModal').style.display = 'flex';
}
function addDriverProfile(userId) {
  document.getElementById('driverUserSelect').value = String(userId);
  document.getElementById('addModal').style.display = 'flex';
}
</script>
</body></html>
