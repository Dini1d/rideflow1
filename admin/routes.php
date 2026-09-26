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
        $name  = trim($_POST['route_name']   ?? '');
        $typeId= (int)($_POST['type_id']    ?? 0);
        $orig  = trim($_POST['origin']       ?? '');
        $dest  = trim($_POST['destination']  ?? '');
        $dist  = trim($_POST['distance_km']  ?? '') ?: null;
        $dur   = trim($_POST['estimated_duration'] ?? '') ?: null;
        if ($orig && $dest && $typeId) {
            if (!$name) $name = "$orig – $dest";
            db_insert("INSERT INTO routes(type_id,route_name,origin,destination,distance_km,estimated_duration) VALUES(?,?,?,?,?,?)",
                [$typeId,$name,$orig,$dest,$dist,$dur]);
            flash('ok','Route added.');
        } else { flash('bad','Origin, destination and type are required.'); }
    }
    if ($act === 'toggle') {
        $id  = (int)($_POST['id'] ?? 0);
        $cur = db_val("SELECT status FROM routes WHERE id=?", [$id]);
        db_exec("UPDATE routes SET status=? WHERE id=?", [$cur==='active'?'inactive':'active',$id]);
        flash('ok','Route status updated.');
    }
    if ($act === 'delete') {
        try { db_exec("DELETE FROM routes WHERE id=?", [(int)($_POST['id']??0)]); flash('ok','Route deleted.'); }
        catch (Exception $e) { flash('bad','Cannot delete — route has schedules.'); }
    }
    redirect(SITE_URL.'/admin/routes.php');
}

$search = trim($_GET['q'] ?? '');
$type   = trim($_GET['type'] ?? '');
$where  = ['1=1']; $params = [];
if ($search) { $where[] = '(r.origin LIKE ? OR r.destination LIKE ? OR r.route_name LIKE ?)'; $params = array_merge($params,["%$search%","%$search%","%$search%"]); }
if ($type)   { $where[] = 't.type_name=?'; $params[] = $type; }

$routes = db_all("SELECT r.*, t.type_name, (SELECT COUNT(*) FROM schedules WHERE route_id=r.id AND departure_date>=CURRENT_DATE) upcoming FROM routes r JOIN transport_types t ON t.id=r.type_id WHERE ".implode(' AND ',$where)." ORDER BY t.type_name, r.origin", $params);
$types  = db_all("SELECT * FROM transport_types WHERE is_active=1 ORDER BY type_name");
$pageTitle = 'Routes';
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
    <div><h1>Routes</h1><p><?= count($routes) ?> routes configured</p></div>
    <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-fire btn-sm"><i class="fas fa-plus"></i> Add Route</button>
  </div>
</div>
<?= flash_html() ?>

<!-- Filter bar -->
<div class="card card-pad" style="margin-bottom:1.25rem">
  <form method="GET" style="display:flex;gap:.65rem;flex-wrap:wrap;align-items:end">
    <div style="flex:1;min-width:180px">
      <div class="fw"><i class="fas fa-search fc-icon"></i>
        <input type="text" name="q" class="fc has-icon" placeholder="Search routes…" value="<?= e($search) ?>">
      </div>
    </div>
    <select name="type" class="fc" style="width:auto">
      <option value="">All Types</option>
      <?php foreach ($types as $t): ?>
      <option value="<?= e($t['type_name']) ?>" <?= $type===$t['type_name']?'selected':'' ?>><?= e($t['type_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-fire btn-sm"><i class="fas fa-filter"></i> Filter</button>
    <a href="<?= SITE_URL ?>/admin/routes.php" class="btn btn-ghost btn-sm">Reset</a>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Route</th><th>Type</th><th>Distance</th><th>Duration</th><th>Upcoming Trips</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (!$routes): ?><tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--chalk4)">No routes found</td></tr><?php endif; ?>
        <?php foreach ($routes as $r):
          $tc = strtolower(str_replace([' ','-'],'',$r['type_name']));
          $tc = $tc==='threewheel'?'wheel':$tc;
        ?>
        <tr>
          <td>
            <div style="font-weight:700;color:var(--chalk)"><?= e($r['origin']) ?> → <?= e($r['destination']) ?></div>
            <div style="font-size:.75rem;color:var(--chalk4)"><?= e($r['route_name']) ?></div>
          </td>
          <td><span class="type-pill tp-<?= $tc ?>"><?= e($r['type_name']) ?></span></td>
          <td style="color:var(--chalk3)"><?= $r['distance_km'] ? number_format($r['distance_km'],1).' km' : '—' ?></td>
          <td style="color:var(--chalk3)"><?= e($r['estimated_duration']) ?: '—' ?></td>
          <td style="text-align:center;color:<?= $r['upcoming']>0?'var(--ok)':'var(--chalk4)' ?>;font-weight:700"><?= $r['upcoming'] ?></td>
          <td><?= status_badge($r['status']) ?></td>
          <td>
            <form method="POST" style="display:flex;gap:.3rem">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button name="action" value="toggle" class="btn btn-dark btn-sm" title="Toggle status"><i class="fas fa-exchange-alt"></i></button>
              <button name="action" value="delete" class="btn btn-bad btn-sm" title="Delete" onclick="return confirm('Delete route <?= e(addslashes($r['origin'])) ?> → <?= e(addslashes($r['destination'])) ?>?')"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div></div></div>

<!-- Add route modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1100;align-items:center;justify-content:center">
  <div style="background:var(--ink2);border:1px solid var(--rim2);border-radius:var(--r4);padding:1.75rem;width:90%;max-width:540px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.1rem">
      <h3 style="margin:0">Add Route</h3>
      <button onclick="document.getElementById('addModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--chalk3);font-size:1.1rem"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="fld"><label>Transport Type</label>
        <select name="type_id" class="fc" required>
          <option value="">Select type…</option>
          <?php foreach ($types as $t): ?>
          <option value="<?= $t['id'] ?>"><?= e($t['type_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field-group">
        <div class="fld"><label>Origin City</label><input type="text" name="origin" class="fc" placeholder="Colombo" required></div>
        <div class="fld"><label>Destination City</label><input type="text" name="destination" class="fc" placeholder="Kandy" required></div>
      </div>
      <div class="fld"><label>Route Name (optional)</label>
        <input type="text" name="route_name" class="fc" placeholder="Colombo–Kandy Express">
      </div>
      <div class="field-group">
        <div class="fld"><label>Distance (km)</label><input type="number" name="distance_km" class="fc" placeholder="120" min="0" step="0.1"></div>
        <div class="fld"><label>Estimated Duration</label><input type="text" name="estimated_duration" class="fc" placeholder="3h 30m"></div>
      </div>
      <button type="submit" class="btn btn-fire"><i class="fas fa-plus"></i> Add Route</button>
    </form>
  </div>
</div>
</body></html>
